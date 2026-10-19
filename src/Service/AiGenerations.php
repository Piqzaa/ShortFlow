<?php

namespace App\Service;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Message\GenerateAiVideo;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class AiGenerations
{
    public function __construct(private EntityManagerInterface $em, private AiKeys $keys, private AiCatalog $catalog, private AiVideoApi $api, private VideoLibrary $library, private PrivateVideoStorage $storage, private MessageBusInterface $bus, private PublicationLock $lock, #[Autowire('%env(bool:AI_GENERATION_ENABLED)%')] private bool $enabled, private ManagerRegistry $registry)
    {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function owned(int $id, User $owner): ?AiGeneration
    {
        return $this->em->getRepository(AiGeneration::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    public function confirm(User $owner, int $id): void
    {
        if (!$this->enabled) {
            throw new \RuntimeException('Les essais de génération payants ne sont pas encore activés. Ton idée est conservée.');
        }
        $prepared = $this->owned($id, $owner) ?? throw new \RuntimeException('Génération indisponible.');
        $provider = $this->catalog->model($prepared->getModel())['provider'];
        if ('prepared' === $prepared->getStatus() && !$this->keys->hasAccess($owner, $provider, $prepared->getAccessMode())) {
            throw new \RuntimeException('mcp' === $prepared->getAccessMode() ? 'Connecte ton compte Fal par navigateur avant de confirmer.' : 'Ajoute ta clé '.$this->catalog->provider($provider).' avant de confirmer.');
        }
        $this->em->wrapInTransaction(function () use ($owner, $id): void {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $job = $this->owned($id, $owner) ?? throw new \RuntimeException('Génération indisponible.');
            $this->em->refresh($job, LockMode::PESSIMISTIC_WRITE);
            if ('prepared' !== $job->getStatus()) {
                return;
            }
            $provider = $this->catalog->model($job->getModel())['provider'];
            if (!$this->keys->hasAccess($owner, $provider, $job->getAccessMode())) {
                throw new \RuntimeException('mcp' === $job->getAccessMode() ? 'Connecte ton compte Fal par navigateur avant de confirmer.' : 'Ajoute ta clé '.$this->catalog->provider($provider).' avant de confirmer.');
            }
            $this->catalog->validate($job->getModel(), $job->getSeconds(), $job->getRatio());
            $this->catalog->validatePrompt($job->getModel(), $job->getPrompt());
            if ((new \DateTimeImmutable($job->getPriceChecked())) < new \DateTimeImmutable('-14 days') || $this->catalog->estimate($job->getModel(), $job->getSeconds()) !== $job->getEstimatedCents()) {
                throw new \RuntimeException('Le tarif doit être revérifié. Prépare une nouvelle estimation avant de lancer.');
            }
            $active = $this->em->getRepository(AiGeneration::class)->count(['owner' => $owner, 'status' => ['queued', 'submitting', 'processing']]);
            if ($active >= 3) {
                throw new \RuntimeException('Trois générations sont déjà en cours. Attends leur résultat.');
            }
            if ('mcp' === $job->getAccessMode()) {
                $credential = json_decode($this->keys->plain($owner, $provider, 'mcp'), true, 32, JSON_THROW_ON_ERROR);
                $job->bindAccessAccount($credential['account_id']);
            }
            $job->progress('queued');
            $this->em->flush();
            $this->bus->dispatch(new GenerateAiVideo($id));
        });
    }

    public function retry(User $owner, int $id, bool $confirmed = false): void
    {
        if (!$this->enabled) {
            throw new \RuntimeException('La génération est désactivée pour le moment.');
        }
        $this->em->wrapInTransaction(function () use ($owner, $id, $confirmed): void {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $job = $this->owned($id, $owner) ?? throw new \RuntimeException('Génération indisponible.');
            $this->em->refresh($job, LockMode::PESSIMISTIC_WRITE);
            if ('waiting_key' === $job->getStatus()) {
                if (!$confirmed || !$this->keys->hasAccess($owner, $this->catalog->model($job->getModel())['provider'], $job->getAccessMode())) {
                    throw new \RuntimeException('Rétablis l’accès au fournisseur et confirme à nouveau le coût estimé.');
                }
                $this->catalog->validate($job->getModel(), $job->getSeconds(), $job->getRatio());
                $this->catalog->validatePrompt($job->getModel(), $job->getPrompt());
                if ((new \DateTimeImmutable($job->getPriceChecked())) < new \DateTimeImmutable('-14 days') || $this->catalog->estimate($job->getModel(), $job->getSeconds()) !== $job->getEstimatedCents()) {
                    throw new \RuntimeException('Le tarif doit être revérifié avant de reprendre.');
                }
                if ($this->em->getRepository(AiGeneration::class)->count(['owner' => $owner, 'status' => ['queued', 'submitting', 'processing']]) >= 3) {
                    throw new \RuntimeException('Trois générations sont déjà en cours.');
                }
                $job->progress('queued');
                $this->em->flush();
                $this->bus->dispatch(new GenerateAiVideo($id));

                return;
            }
            if ('review' !== $job->getStatus() || !$job->getRemote()) {
                return;
            }
            $job->progress('processing');
            $this->em->flush();
            $this->bus->dispatch(new GenerateAiVideo($id));
        });
    }

    public function process(int $id): void
    {
        $job = $this->em->find(AiGeneration::class, $id);
        if (!$job || !$this->enabled) {
            return;
        }
        $this->lock->run((int) $job->getOwner()->getId(), function () use ($id): void {
            $job = $this->em->find(AiGeneration::class, $id);
            if (!$job) {
                return;
            }
            $this->em->refresh($job);
            if ('submitting' === $job->getStatus()) {
                $job->progress('review', 'Le résultat de la demande est incertain. Vérifie l’historique fournisseur avant tout nouvel essai : aucun renvoi automatique.');
                $this->em->flush();

                return;
            }
            if (!in_array($job->getStatus(), ['queued', 'processing'], true)) {
                return;
            }
            try {
                $key = $this->keys->plain($job->getOwner(), $this->catalog->model($job->getModel())['provider'], $job->getAccessMode());
            } catch (\Throwable) {
                $job->progress('queued' === $job->getStatus() ? 'waiting_key' : 'review', 'L’accès à ce fournisseur est indisponible. Vérifie la connexion choisie dans Connexions.');
                $this->em->flush();

                return;
            }
            if ('queued' === $job->getStatus()) {
                try {
                    $this->api->checkAccess($job, $key);
                    $this->catalog->validate($job->getModel(), $job->getSeconds(), $job->getRatio());
                    $this->catalog->validatePrompt($job->getModel(), $job->getPrompt());
                    if ((new \DateTimeImmutable($job->getPriceChecked())) < new \DateTimeImmutable('-14 days') || $this->catalog->estimate($job->getModel(), $job->getSeconds()) !== $job->getEstimatedCents()) {
                        throw new \RuntimeException('Le tarif doit être revérifié avant de lancer. Prépare une nouvelle estimation.');
                    }
                } catch (\InvalidArgumentException|\RuntimeException $error) {
                    $job->progress('review', $error->getMessage());
                    $this->em->flush();

                    return;
                }
                // Commit this state before the billable POST. A redelivery must never submit again.
                $job->progress('submitting');
                $this->em->flush();
                try {
                    $remote = $this->api->submit($job, $key);
                    $job->progress('processing', remote: $remote);
                    $this->em->flush();
                    $this->later($id);
                } catch (\Throwable) {
                    $job->progress('review', 'La demande n’a pas pu être confirmée. Vérifie l’historique fournisseur avant de recommencer ; elle peut déjà avoir été facturée.');
                    $this->em->flush();
                }

                return;
            }
            if ($job->getUpdatedAt() < new \DateTimeImmutable('-45 minutes')) {
                $job->progress('review', 'Le fournisseur prend plus de temps que prévu. Tu peux consulter à nouveau ce même résultat.');
                $this->em->flush();

                return;
            }
            $path = null;
            $video = null;
            try {
                $result = $this->api->poll($job, $key);
                if (!$result['ready']) {
                    $this->later($id);

                    return;
                }
                $path = $this->api->download($job, $result['url']);
                // The job result and imported asset commit together, including on message redelivery.
                $this->em->wrapInTransaction(function () use ($job, $path, &$video): void {
                    $video = $this->library->import($job->getOwner(), new UploadedFile($path, 'generation.mp4', null, null, true), $job->getTitle());
                    $job->complete($video);
                });
            } catch (\Throwable) {
                if ($video) {
                    $this->storage->discard($video->getStorageName());
                }
                // A failed import rolls back both records; recover a fresh manager for the visible issue.
                if (!$this->em->isOpen()) {
                    $manager = $this->registry->resetManager();
                    if (!$manager instanceof EntityManagerInterface) {
                        throw new \LogicException('Le gestionnaire ORM est indisponible.');
                    }
                    $this->em = $manager;
                    $job = $this->em->find(AiGeneration::class, $id);
                }
                $job->progress('review', 'La vidéo n’a pas pu rejoindre la bibliothèque. Vérifie la clé, la disponibilité du résultat et la place libre, puis reprends la récupération. Aucune nouvelle génération ne sera demandée.');
                $this->em->flush();
            } finally {
                if (null !== $path && is_file($path)) {
                    @unlink($path);
                }
            }
        });
    }

    private function later(int $id): void
    {
        $this->bus->dispatch(new GenerateAiVideo($id), [new DelayStamp(20000)]);
    }
}
