<?php

namespace App\Service;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiExchangeRateUpdater
{
    public function __construct(private AiDisplayCurrency $currency, private HttpClientInterface $http, private ClockInterface $clock)
    {
    }

    /** @return array{date:string,usdPerEur:float}|null */
    public function refresh(bool $force = false): ?array
    {
        $current = $this->currency->rate();
        $today = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
        if (!$force && ($current['date'] ?? '') === $today) {
            return $current;
        }
        $path = $this->currency->cachePath();
        $files = new Filesystem();
        $files->mkdir(dirname($path));
        $lock = fopen($path.'.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }

            return $current;
        }
        try {
            $now = $this->clock->now()->getTimestamp();
            $attempt = is_file($path.'.attempt') ? (int) @file_get_contents($path.'.attempt') : 0;
            if (!$force && $now - $attempt < 21600) {
                return $current;
            }
            // Throttle failures and weekends too. No request on a page load.
            $files->dumpFile($path.'.attempt', (string) $now);
            $xml = $this->http->request('GET', 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml', ['timeout' => 5, 'max_duration' => 10, 'max_redirects' => 0])->getContent();
            if (strlen($xml) > 65536 || false !== stripos($xml, '<!DOCTYPE')) {
                throw new \RuntimeException('Format de taux refusé.');
            }
            $document = new \DOMDocument();
            if (!@$document->loadXML($xml, LIBXML_NONET)) {
                throw new \RuntimeException('Réponse BCE illisible.');
            }
            $xpath = new \DOMXPath($document);
            $rate = $this->currency->validate(['date' => $xpath->evaluate('string(//*[local-name()="Cube"][@time]/@time)'), 'usdPerEur' => $xpath->evaluate('string(//*[local-name()="Cube"][@currency="USD"]/@rate)')]);
            if (!$rate || ($current && $rate['date'] < $current['date'])) {
                throw new \RuntimeException('Taux BCE absent, invalide, ancien ou antérieur au taux conservé.');
            }
            $files->dumpFile($path, json_encode($rate, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n");

            return $rate;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
