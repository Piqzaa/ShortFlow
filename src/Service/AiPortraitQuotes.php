<?php

namespace App\Service;

use App\Entity\AiPortraitDraft;
use Symfony\Component\Clock\ClockInterface;

final class AiPortraitQuotes
{
    public const MODEL = 'fal-ai/flux-2-pro';
    public const CHECKED = '2026-11-05';
    public const COST_MICROS = 30000;

    public function __construct(private ClockInterface $clock, private AiKeys $keys)
    {
    }

    public function fresh(): bool
    {
        $checked = new \DateTimeImmutable(self::CHECKED, new \DateTimeZone('Europe/Paris'));
        $now = $this->clock->now();

        return $now >= $checked && $now < $checked->modify('+14 days');
    }

    public function prompt(AiPortraitDraft $draft): string
    {
        if ('' !== $draft->getCustomPrompt()) {
            return $draft->getCustomPrompt();
        }

        return "Create one character reference portrait. One character only, clearly visible, plain background, no text or watermark.\nCharacter name: ".$draft->getName()."\nIdea: ".$draft->getIdea()."\nAppearance: ".$draft->getAppearance()."\nVisual style: ".$draft->getStyle();
    }

    public function build(AiPortraitDraft $draft): ?array
    {
        if (!$this->fresh()) {
            return null;
        }
        $owner = $draft->getOwner();
        $connection = $this->keys->hasAccess($owner, 'fal', 'api') ? $this->keys->identity($owner, 'fal', 'api') : null;
        $quote = ['draftId' => $draft->getId() ?? throw new \LogicException('Enregistre le brouillon avant son devis.'), 'ownerId' => $owner->getId(), 'model' => self::MODEL, 'provider' => 'fal', 'accessMode' => 'api', 'quantity' => 1, 'costMicros' => self::COST_MICROS, 'currency' => 'USD', 'checked' => self::CHECKED, 'connection' => $connection, 'revision' => $draft->getRevision(), 'name' => $draft->getName(), 'idea' => $draft->getIdea(), 'appearance' => $draft->getAppearance(), 'style' => $draft->getStyle(), 'input' => ['prompt' => $this->prompt($draft), 'image_size' => ['width' => 1024, 'height' => 1024], 'output_format' => 'jpeg', 'sync_mode' => false, 'enable_safety_checker' => true, 'safety_tolerance' => '2']];
        $quote['hash'] = hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $quote;
    }

    public function status(AiPortraitDraft $draft): string
    {
        $current = $this->build($draft);
        if (!$current) {
            return 'expired';
        }
        $stored = $draft->getQuote();
        if (!$stored) {
            return 'missing';
        }
        if (!hash_equals($stored['hash'], $current['hash'])) {
            return 'changed';
        }

        return null === $current['connection'] ? 'connection_missing' : 'current';
    }
}
