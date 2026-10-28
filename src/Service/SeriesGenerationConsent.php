<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\SeriesOccurrence;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class SeriesGenerationConsent
{
    public function __construct(private EntityManagerInterface $em, private AiKeys $keys, private AiCatalog $catalog)
    {
    }

    public function assertAllowed(AiEpisode $episode): void
    {
        $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['episode' => $episode]);
        if (!$occurrence) {
            return;
        }
        $this->em->refresh($occurrence);
        if (!$occurrence->isAutomatic()) {
            if ('running' === $episode->getFlowState()) {
                $recipe = $episode->getFlowRecipe();
                $identity = $this->keys->identity($episode->getOwner(), $this->catalog->model($recipe['model'])['provider'], $recipe['accessMode']);
                if (!$episode->getFlowAccount() || !hash_equals($episode->getFlowAccount(), $identity)) {
                    throw new SeriesReviewRequired('Le compte fournisseur a changé ou son ancienne confirmation ne le lie pas. Utilise « Revoir les plans restants » puis confirme le nouveau devis avant tout envoi.');
                }
            }

            return;
        }
        throw new SeriesReviewRequired('La préparation récurrente n’autorise plus les générations vidéo automatiques. Utilise « Revoir les plans restants », relis l’épisode puis confirme son devis. Aucun nouvel appel payant automatique.');
    }

    public function manual(AiEpisode $episode, callable $action): void
    {
        $this->em->getConnection()->transactional(function () use ($episode, $action): void {
            $this->em->lock($episode->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $action();
            $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['episode' => $episode]);
            $occurrence?->manageManually();
            $this->em->flush();
        });
    }
}
