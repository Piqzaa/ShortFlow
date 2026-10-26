<?php

namespace App\Service;

use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Repository\PublicationDraftRepository;
use App\Repository\VideoAssetRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PublicationBatch
{
    public function __construct(private EntityManagerInterface $em, private VideoAssetRepository $videos, private PublicationDraftRepository $repository, private PublicationDrafts $drafts, private PublicationLock $lock, private PublicationTime $time, private SocialAccounts $social)
    {
    }

    /** @param array<int|string,array{networks:list<string>,mode:string,local:?string,timezone:string,accounts?:array<string,?string>}> $plans
     * @return list<PublicationDraft>
     */
    public function prepare(User $owner, string $key, array $plans): array
    {
        if (!preg_match('/^[a-f0-9]{48}$/D', $key) || count($plans) < 2 || count($plans) > 20) {
            throw new PublicationException('Choisis de 2 à 20 vidéos pour cette préparation.');
        }
        $identities = [];
        foreach ($plans as $id => $plan) {
            if (!is_int($id) || $id < 1) {
                throw new PublicationException('Une vidéo de la sélection est invalide.');
            }
            $identities[$id] = hash('sha256', $owner->getId().':'.$key.':'.$id);
        }

        return $this->lock->run((int) $owner->getId(), function () use ($owner, $plans, $identities): array {
            $existing = $this->repository->findBy(['owner' => $owner, 'preparationKey' => array_values($identities)]);
            if ($existing) {
                if (count($existing) !== count($plans)) {
                    throw new PublicationException('Cette sélection a déjà été préparée puis modifiée. Retrouve ses brouillons dans Vidéos.');
                }
                $byKey = [];
                foreach ($existing as $draft) {
                    $byKey[$draft->getPreparationKey()] = $draft;
                }

                return array_map(static fn (string $identity): PublicationDraft => $byKey[$identity], array_values($identities));
            }
            // Validate every row before saving the first draft. No provider capabilities are requested here.
            $selected = [];
            $chosen = [];
            foreach ($plans as $id => $plan) {
                $selected[$id] = $this->videos->owned($id, $owner) ?? throw new PublicationException('Une vidéo sélectionnée est indisponible.');
                if (!$plan['networks'] || count(array_unique($plan['networks'])) !== count($plan['networks']) || array_diff($plan['networks'], array_keys(MultiNetworkPublishing::LABELS)) || !in_array($plan['mode'], ['immediate', 'scheduled'], true) || !in_array($plan['timezone'], \DateTimeZone::listIdentifiers(), true)) {
                    throw new PublicationException('Vérifie les réseaux et le créneau de chaque vidéo.');
                }
                if ('scheduled' === $plan['mode']) {
                    $this->time->validateFuture($this->time->resolve($plan['local'] ?? '', $plan['timezone']));
                }
                $chosen[$id] = $this->social->select($owner, $plan['networks'], $plan['accounts'] ?? []);
            }
            try {
                return $this->em->getConnection()->transactional(function () use ($owner, $plans, $identities, $selected, $chosen): array {
                    $result = [];
                    foreach ($plans as $id => $plan) {
                        $title = str_replace(['<', '>'], '', $selected[$id]->getTitle());
                        $content = $accounts = [];
                        foreach ($plan['networks'] as $network) {
                            $accounts[$network] = $chosen[$id][$network];
                            $content[$network]['videos'][$id] = match ($network) {
                                'youtube' => ['title' => mb_substr($title, 0, 100), 'description' => '', 'privacy' => 'public', 'audience' => 'yes', 'synthetic' => false],
                                'instagram' => ['caption' => $title, 'shareToFeed' => true, 'synthetic' => false],
                                'tiktok' => ['caption' => $title, 'privacy' => null, 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => false],
                                default => throw new PublicationException('Ce réseau est indisponible.'),
                            };
                        }
                        $draft = $this->drafts->save($owner, [$id], $plan['networks'], $content, $accounts, mb_substr($title, 0, 120));
                        $this->drafts->plan($owner, (int) $draft->getId(), $draft->getRevision(), 'scheduled' === $plan['mode'] ? $plan['local'] : null, $plan['timezone']);
                        $draft->identifyPreparation($identities[$id]);
                        $result[] = $draft;
                    }
                    $this->em->flush();

                    return $result;
                });
            } catch (\Throwable $error) {
                // Flushed objects must not survive a rollback of the complete selection.
                $this->em->clear();
                throw $error;
            }
        });
    }
}
