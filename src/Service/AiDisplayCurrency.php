<?php

namespace App\Service;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Presentation only. Quotes, consent hashes and provider amounts remain in USD. */
final class AiDisplayCurrency
{
    public const SOURCE = 'https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html';

    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir, private ClockInterface $clock)
    {
    }

    public function cachePath(): string
    {
        return $this->projectDir.'/var/finance/ai_exchange_rate.json';
    }

    /** @return array{date:string,usdPerEur:float}|null */
    public function validate(mixed $data): ?array
    {
        if (!is_array($data) || !is_string($data['date'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data['date']) || !is_numeric($data['usdPerEur'] ?? null)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['date'], new \DateTimeZone('UTC'));
        $today = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);
        $rate = (float) $data['usdPerEur'];
        if (!$date || $date->format('Y-m-d') !== $data['date'] || $date > $today || $date < $today->modify('-10 days') || !is_finite($rate) || $rate < 0.01 || $rate > 100) {
            return null;
        }

        return ['date' => $data['date'], 'usdPerEur' => $rate];
    }

    /**
     * @return array{date:string,usdPerEur:float}|null
     *
     * @phpstan-impure Local snapshot and clock may change between calls.
     */
    public function rate(): ?array
    {
        $rates = [];
        foreach ([$this->cachePath(), $this->projectDir.'/config/ai_exchange_rate.json'] as $path) {
            $raw = is_file($path) ? @file_get_contents($path) : false;
            $rate = false !== $raw ? $this->validate(json_decode($raw, true)) : null;
            if ($rate) {
                $rates[] = $rate;
            }
        }
        usort($rates, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $rates[0] ?? null;
    }

    /** @phpstan-impure Reads the current local exchange-rate snapshot. */
    public function format(float $usdCents, int $decimals = 2): string
    {
        if (!is_finite($usdCents) || $usdCents < 0 || !in_array($decimals, [2, 3], true)) {
            throw new \InvalidArgumentException('Montant indicatif invalide.');
        }
        $rate = $this->rate();
        $amount = $usdCents / 100 / ($rate['usdPerEur'] ?? 1);
        $unit = $rate ? ' €' : ' $ US';
        if ($amount > 0 && $amount < 10 ** -$decimals) {
            return '< '.number_format(10 ** -$decimals, $decimals, ',', ' ').$unit;
        }

        return number_format($amount, $decimals, ',', ' ').$unit;
    }
}
