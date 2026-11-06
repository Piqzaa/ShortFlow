<?php

namespace App\Twig;

use App\Service\AiDisplayCurrency;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AiCurrencyExtension extends AbstractExtension
{
    public function __construct(private AiDisplayCurrency $currency)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('ai_money', $this->currency->format(...)), new TwigFunction('ai_exchange', $this->currency->rate(...))];
    }
}
