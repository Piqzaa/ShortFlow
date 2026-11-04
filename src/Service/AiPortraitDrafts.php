<?php

namespace App\Service;

use App\Entity\AiPortraitDraft;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class AiPortraitDrafts
{
    public function __construct(private EntityManagerInterface $em, private AiPortraitQuotes $quotes)
    {
    }

    public function owned(User $owner, int $id): ?AiPortraitDraft
    {
        return $this->em->getRepository(AiPortraitDraft::class)->findOneBy(['owner' => $owner, 'id' => $id]);
    }

    public function save(User $owner, ?int $id, array $data): AiPortraitDraft
    {
        return $this->em->getConnection()->transactional(function () use ($owner, $id, $data): AiPortraitDraft {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $editing = null !== $id;
            $draft = $editing ? $this->owned($owner, $id) : new AiPortraitDraft($owner);
            if (!$draft) {
                throw new \RuntimeException('Brouillon indisponible.');
            }
            if ($editing) {
                $this->em->refresh($draft);
            }
            if ($draft->getRevision() !== (int) ($data['revision'] ?? -1)) {
                throw new \RuntimeException('Ce brouillon a changé. Recharge-le avant de modifier ; tes textes restent affichés.');
            }
            $values = [];
            foreach (['name' => 60, 'idea' => 1000, 'appearance' => 2000, 'style' => 300, 'customPrompt' => 4000] as $field => $limit) {
                $text = $data[$field] ?? '';
                if (!is_string($text) || mb_strlen($text) > $limit || (in_array($field, ['name', 'idea'], true) && '' === trim($text))) {
                    throw new \RuntimeException('Vérifie les textes et leurs longueurs avant de sauvegarder.');
                }
                $values[$field] = trim($text);
            }
            $draft->revise($values['name'], $values['idea'], $values['appearance'], $values['style'], $values['customPrompt']);
            if (!$editing) {
                $this->em->persist($draft);
            }
            $this->em->flush();
            $draft->estimate($this->quotes->build($draft));
            $this->em->flush();

            return $draft;
        });
    }
}
