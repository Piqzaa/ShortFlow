<?php

namespace App\Tests\Unit;

use App\Command\RefreshAiExchangeRateCommand;
use App\EventSubscriber\AiExchangeRateListener;
use App\Service\AiDisplayCurrency;
use App\Service\AiExchangeRateUpdater;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;

final class AiDisplayCurrencyTest extends TestCase
{
    private string $directory;
    private MockClock $clock;
    private AiDisplayCurrency $currency;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/vydflow-exchange-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/config', 0700, true);
        $this->clock = new MockClock('2026-11-06 18:00:00 UTC');
        $this->currency = new AiDisplayCurrency($this->directory, $this->clock);
        $this->seed('2026-11-06', 1.1165);
    }

    protected function tearDown(): void
    {
        foreach ([$this->currency->cachePath(), $this->currency->cachePath().'.attempt', $this->currency->cachePath().'.lock', $this->directory.'/config/ai_exchange_rate.json'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach (['/var/finance', '/var', '/config', ''] as $suffix) {
            if (is_dir($this->directory.$suffix)) {
                rmdir($this->directory.$suffix);
            }
        }
    }

    private function seed(string $date, float $rate): void
    {
        file_put_contents($this->directory.'/config/ai_exchange_rate.json', json_encode(['date' => $date, 'usdPerEur' => $rate], JSON_THROW_ON_ERROR));
    }

    private function xml(string $date = '2026-11-06'): string
    {
        return '<Envelope><Cube><Cube time="'.$date.'"><Cube currency="USD" rate="1.1165"/></Cube></Cube></Envelope>';
    }

    public function testConversionUsesTheInverseRateAndRoundsTheTotalOnly(): void
    {
        self::assertSame('0,37 €', $this->currency->format(41));
        self::assertSame('0,56 €', $this->currency->format(62));
        self::assertSame('0,73 €', $this->currency->format(82));
        self::assertSame('1,10 €', $this->currency->format(123));
        self::assertSame('< 0,01 €', $this->currency->format(0.1));
        self::assertSame('< 0,001 €', $this->currency->format(0.1, 3));
        self::assertSame('0,00 €', $this->currency->format(0));
    }

    public function testStaleMissingMalformedAndFutureRatesFallBackToUsd(): void
    {
        foreach ([['date' => '2026-11-07', 'usdPerEur' => 1.1], ['date' => '2026-10-01', 'usdPerEur' => 1.1], ['date' => "2026-11-06\0", 'usdPerEur' => 1.1], ['date' => '2026-11-06', 'usdPerEur' => 0]] as $data) {
            self::assertNull($this->currency->validate($data));
        }
        $this->clock->modify('+11 days');
        self::assertNull($this->currency->rate());
        self::assertSame('1,00 $ US', $this->currency->format(100));
        file_put_contents($this->directory.'/config/ai_exchange_rate.json', '{invalid');
        self::assertSame('1,00 $ US', $this->currency->format(100));
        unlink($this->directory.'/config/ai_exchange_rate.json');
        self::assertSame('1,00 $ US', $this->currency->format(100));
    }

    public function testOnlyTheIdleBackgroundWorkerRefreshesAndDoesNotSubmitAModel(): void
    {
        $this->seed('2026-11-05', 1.1);
        $requests = 0;
        $http = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
            ++$requests;
            self::assertSame('GET', $method);
            self::assertSame('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml', $url);

            return new MockResponse($this->xml());
        });
        $listener = new AiExchangeRateListener(new AiExchangeRateUpdater($this->currency, $http, $this->clock), new NullLogger());
        $other = new Worker(['video_edits' => new InMemoryTransport()], new MessageBus());
        $background = new Worker(['background' => new InMemoryTransport()], new MessageBus());
        $listener(new WorkerRunningEvent($other, true));
        $listener(new WorkerRunningEvent($background, false));
        self::assertSame(0, $requests);
        $listener(new WorkerRunningEvent($background, true));
        self::assertSame(1, $requests);
        self::assertSame('2026-11-06', $this->currency->rate()['date']);
        self::assertSame('0,56 €', $this->currency->format(62));
        $listener(new WorkerRunningEvent($background, true));
        self::assertSame(1, $requests);
    }

    public function testFailureKeepsTheLastRateAndThrottlesRetries(): void
    {
        $this->seed('2026-11-05', 1.1);
        $requests = 0;
        $http = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse('<!DOCTYPE x [<!ENTITY x SYSTEM "file:///private">]><x>&x;</x>');
        });
        $updater = new AiExchangeRateUpdater($this->currency, $http, $this->clock);
        $tester = new CommandTester(new RefreshAiExchangeRateCommand($updater));
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('dernier taux reste conservé', $tester->getDisplay());
        self::assertSame('2026-11-05', $updater->refresh()['date']);
        self::assertSame(1, $requests);
        self::assertFileDoesNotExist($this->currency->cachePath());
        $this->clock->modify('+6 hours');
        try {
            $updater->refresh();
            self::fail('DOCTYPE must remain rejected.');
        } catch (\RuntimeException) {
            self::assertSame(2, $requests);
        }
    }
}
