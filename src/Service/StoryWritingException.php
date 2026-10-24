<?php

namespace App\Service;

final class StoryWritingException extends \RuntimeException
{
    public function __construct(public readonly string $reason, ?\Throwable $previous = null)
    {
        $message = match ($reason) {
            'timeout' => 'Le moteur local a dépassé le délai de rédaction. Réessaie avec moins de plans ou lorsque le poste est moins chargé.',
            'unavailable' => 'Le moteur local est injoignable. Vérifie qu’Ollama fonctionne sur le poste.',
            'model' => 'Le modèle de rédaction configuré est introuvable dans Ollama. Vérifie son installation.',
            'busy' => 'Le rédacteur local prépare déjà une autre histoire. Réessaie dans un instant.',
            'incomplete' => 'Le moteur local a interrompu sa réponse avant la fin. Réessaie avec moins de plans.',
            default => 'Le rédacteur n’a pas fourni de scénario exploitable. Tu peux réessayer ou écrire les scènes toi-même.',
        };
        parent::__construct($message.' Tes scènes sont conservées.', 0, $previous);
    }
}
