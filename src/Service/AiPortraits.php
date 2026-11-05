<?php

namespace App\Service;

use App\Entity\AiCharacter;
use App\Entity\AiPortraitDraft;
use App\Entity\AiPortraitRequest;
use App\Entity\User;
use App\Message\GenerateAiPortrait;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class AiPortraits
{
    public function __construct(private EntityManagerInterface $em, private AiPortraitQuotes $quotes, private AiKeys $keys, private AiPortraitApi $api, private AiReferenceStorage $storage, private MessageBusInterface $bus, private ClockInterface $clock, #[Autowire('%env(bool:PORTRAIT_GENERATION_ENABLED)%')] private bool $enabled)
    {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function owned(User $owner, int $id): ?AiPortraitRequest
    {
        return $this->em->getRepository(AiPortraitRequest::class)->findOneBy(['owner' => $owner, 'id' => $id]);
    }

    public function recent(AiPortraitDraft $draft): array
    {
        return $this->em->getRepository(AiPortraitRequest::class)->findBy(['draft' => $draft, 'owner' => $draft->getOwner()], ['id' => 'DESC'], 12);
    }

    public function confirm(User $owner, int $draftId, string $hash): AiPortraitRequest
    {
        return $this->em->getConnection()->transactional(function () use ($owner, $draftId, $hash): AiPortraitRequest {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $draft = $this->em->getRepository(AiPortraitDraft::class)->findOneBy(['owner' => $owner, 'id' => $draftId]);
            if (!$draft) {
                throw new \RuntimeException('Brouillon indisponible.');
            }
            $existing = $this->em->getRepository(AiPortraitRequest::class)->findOneBy(['draft' => $draft, 'quoteHash' => $hash]);
            if ($existing) {
                return $existing;
            }
            $this->em->refresh($draft);
            if (!$this->enabled || 'current' !== $this->quotes->status($draft) || !hash_equals($draft->getQuote()['hash'] ?? '', $hash)) {
                throw new \RuntimeException('Génération désactivée ou devis modifié. Relis le dernier devis avant de confirmer.');
            }
            $repo = $this->em->getRepository(AiPortraitRequest::class);
            foreach (['queued', 'submitting', 'processing', 'retrieving', 'review'] as $active) {
                if ($repo->count(['owner' => $owner, 'status' => $active]) > 0) {
                    throw new \RuntimeException('Une demande de portrait reste à terminer ou à vérifier sur ce compte.');
                }
            }
            if ($repo->count(['owner' => $owner, 'status' => 'ready']) >= 12) {
                throw new \RuntimeException('Accepte ou écarte un portrait en attente avant une nouvelle demande.');
            }
            $this->storage->assertCapacity($owner);
            $request = new AiPortraitRequest($owner, $draft, $draft->getQuote(), $this->clock->now());
            $this->em->persist($request);
            $this->em->flush();
            // Doctrine transport shares this transaction: request and delivery are atomic.
            $this->bus->dispatch(new GenerateAiPortrait($request->getId()));

            return $request;
        });
    }

    private function locked(AiPortraitRequest $request, callable $action): mixed
    {
        return $this->em->getConnection()->transactional(function () use ($request, $action): mixed {
            $this->em->lock($request->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($request);
            $result = $action();
            $this->em->flush();

            return $result;
        });
    }

    private function schedule(AiPortraitRequest $request): void
    {
        $this->bus->dispatch(new GenerateAiPortrait($request->getId()), [new DelayStamp(10000)]);
    }

    public function process(int $id): void
    {
        $request = $this->em->find(AiPortraitRequest::class, $id);
        if (!$request || !$this->enabled) {
            return;
        }
        $lease = bin2hex(random_bytes(16));
        $claim = $this->locked($request, function () use ($request, $lease): ?array {
            $status = $request->getStatus();
            if (in_array($status, ['submitting', 'retrieving'], true)) {
                if ($request->getUpdatedAt() <= $this->clock->now()->modify('-5 minutes')) {
                    $request->state('review', $this->clock->now(), 'Le traitement a été interrompu. Aucun nouvel envoi automatique.');
                }

                return null;
            }
            if (!in_array($status, ['queued', 'processing'], true)) {
                return null;
            }
            $owner = $request->getOwner();
            $connection = $this->keys->hasAccess($owner, 'fal', 'api') ? $this->keys->identity($owner, 'fal', 'api') : null;
            $snapshot = $request->getSnapshot();
            $currentTariff = $this->quotes->fresh() && ($snapshot['checked'] ?? null) === AiPortraitQuotes::CHECKED && ($snapshot['model'] ?? null) === AiPortraitQuotes::MODEL && ($snapshot['costMicros'] ?? null) === AiPortraitQuotes::COST_MICROS;
            if ($connection !== $request->getRecoveryConnection() || ('queued' === $status && !$currentTariff)) {
                $request->state('queued' === $status ? 'blocked' : 'review', $this->clock->now(), 'La connexion ou le tarif a changé. Aucun nouvel envoi.');

                return null;
            }
            if ('queued' === $status) {
                try {
                    $this->storage->assertCapacity($owner);
                } catch (\RuntimeException) {
                    $request->state('blocked', $this->clock->now(), 'Le stockage des références est plein. Aucun envoi effectué.');

                    return null;
                }
            }
            // Read credentials under the same lock as their version check, so a
            // concurrent key replacement cannot change the billed account.
            try {
                $key = $this->keys->plain($owner, 'fal', 'api');
            } catch (\RuntimeException) {
                $request->state('queued' === $status ? 'blocked' : 'review', $this->clock->now(), 'Connexion indisponible. Aucun nouvel envoi.');

                return null;
            }
            $request->state('queued' === $status ? 'submitting' : 'retrieving', $this->clock->now(), lease: $lease);

            return ['action' => 'queued' === $status ? 'submit' : 'retrieve', 'key' => $key];
        });
        if (null === $claim) {
            return;
        }
        ['action' => $action, 'key' => $key] = $claim;
        $temporary = null;
        $created = null;
        try {
            if ('submit' === $action) {
                $receipt = $this->api->submit($request->getSnapshot(), $key);
                $this->locked($request, function () use ($request, $lease, $receipt): void {
                    if ($request->getLease() === $lease) {
                        $request->receipt($receipt);
                        $request->state('processing', $this->clock->now());
                        $this->schedule($request);
                    }
                });

                return;
            }
            $result = $this->api->poll($request->getRemote() ?? [], $key);
            if (null === $result) {
                $this->locked($request, function () use ($request, $lease): void {
                    if ($request->getLease() === $lease) {
                        $request->state('processing', $this->clock->now());
                        $this->schedule($request);
                    }
                });

                return;
            }
            $this->locked($request, function () use ($request, $lease, $result): void {
                if ($request->getLease() === $lease) {
                    $request->metadata(array_intersect_key($result, array_flip(['seed', 'images', 'has_nsfw_concepts'])));
                }
            });
            if (in_array(true, $result['has_nsfw_concepts'] ?? [], true)) {
                throw new \RuntimeException('Portrait refusé par le contrôle de sûreté.');
            }
            $temporary = $this->api->download($this->api->imageUrl($result));
            $dimensions = @getimagesize($temporary);
            if (!is_array($dimensions) || 1024 !== $dimensions[0] || 1024 !== $dimensions[1]) {
                throw new \RuntimeException('Dimensions du portrait inattendues.');
            }
            $this->locked($request, function () use ($request, $lease, $temporary, &$created): void {
                if ($request->getLease() !== $lease) {
                    return;
                }
                $created = $this->storage->store($request->getOwner(), new UploadedFile($temporary, 'portrait.jpg', test: true));
                $request->image($created);
                $request->state('ready', $this->clock->now());
            });
            $created = null;
        } catch (\Throwable) {
            $cleanupFailed = false;
            if (null !== $created) {
                try {
                    $this->storage->discardNew($request->getOwner(), $created);
                } catch (\Throwable) {
                    $cleanupFailed = true;
                }
            }
            if ($this->em->isOpen()) {
                $this->locked($request, function () use ($request, $lease, $action, $cleanupFailed): void {
                    if ($request->getLease() === $lease) {
                        $issue = $cleanupFailed ? 'Le stockage doit être vérifié avant une reprise.' : ('submit' === $action ? 'Envoi incertain : aucun nouvel envoi automatique. Vérifie cette demande chez Fal avant toute autre génération.' : 'Récupération interrompue ou résultat refusé. Le reçu est conservé ; la reprise récupère cette même demande.');
                        $request->state('review', $this->clock->now(), $issue);
                    }
                });
            }
        } finally {
            if (null !== $temporary && is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function resume(User $owner, int $id): AiPortraitRequest
    {
        $request = $this->owned($owner, $id) ?? throw new \RuntimeException('Demande indisponible.');

        return $this->locked($request, function () use ($owner, $request): AiPortraitRequest {
            if (!$this->enabled || 'review' !== $request->getStatus() || !$request->getRemote() || !$this->keys->hasAccess($owner, 'fal', 'api')) {
                throw new \RuntimeException('Reprise indisponible. Aucun nouvel envoi effectué.');
            }
            $request->recoveredWith($this->keys->identity($owner, 'fal', 'api'));
            $request->state('processing', $this->clock->now());
            $this->schedule($request);

            return $request;
        });
    }

    public function accept(User $owner, int $id): AiCharacter
    {
        $request = $this->owned($owner, $id) ?? throw new \RuntimeException('Demande indisponible.');

        return $this->locked($request, function () use ($owner, $request): AiCharacter {
            if ('accepted' === $request->getStatus()) {
                return $request->getAcceptedCharacter() ?? throw new \RuntimeException('La fiche acceptée a été supprimée.');
            }
            if ('ready' !== $request->getStatus() || !$request->getResultReference()) {
                throw new \RuntimeException('Aucun portrait prêt à accepter.');
            }
            $this->storage->path($owner, $request->getResultReference());
            $snapshot = $request->getSnapshot();
            $character = new AiCharacter($owner, $snapshot['name'], $snapshot['appearance'] ?: $snapshot['idea'], $request->getResultReference());
            $this->em->persist($character);
            $request->accept($character);
            $request->state('accepted', $this->clock->now());

            return $character;
        });
    }

    public function discard(User $owner, int $id): void
    {
        $request = $this->owned($owner, $id) ?? throw new \RuntimeException('Demande indisponible.');
        $this->locked($request, function () use ($request): void {
            if ('discarded' === $request->getStatus()) {
                return;
            }
            if ('ready' !== $request->getStatus()) {
                throw new \RuntimeException('Seul un portrait prêt peut être écarté.');
            }
            $request->discard();
            $request->state('discarded', $this->clock->now());
        });
    }
}
