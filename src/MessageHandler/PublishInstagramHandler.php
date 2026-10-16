<?php

namespace App\MessageHandler;

use App\Entity\InstagramPublication;
use App\Message\PublishInstagram;
use App\Service\InstagramApi;
use App\Service\InstagramMediaLink;
use App\Service\InstagramPublishing;
use App\Service\PublicationLock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final class PublishInstagramHandler
{
    public function __construct(private EntityManagerInterface $em, private InstagramPublishing $publishing, private InstagramApi $api, private InstagramMediaLink $links, private PublicationLock $lock, private MessageBusInterface $bus)
    {
    }

    public function __invoke(PublishInstagram $message): void
    {
        $job = $this->em->find(InstagramPublication::class, $message->id);
        if (!$job) {
            return;
        }
        $this->lock->run((int) $job->getOwner()->getId(), function () use ($job, $message): void {
            $this->em->refresh($job);
            if (!in_array($job->getStatus(), ['queued', 'processing', 'publishing'], true)) {
                return;
            }
            $connection = $this->publishing->connection($job->getOwner());
            if ($connection) {
                $this->em->refresh($connection);
            }
            if (!$this->publishing->ready($job->getOwner()) || !$connection || $connection->getInstagramId() !== $job->getInstagramId()) {
                $job->change('paused', 'Reconnecte le compte Instagram destinataire avant de reprendre.');
                $this->em->flush();

                return;
            }
            if (!$job->getSource() || $job->getSource()->getOwner()->getId() !== $job->getOwner()->getId()) {
                $job->change('failed', 'La vidéo source est indisponible.');
                $this->em->flush();

                return;
            }
            try {
                if (!$job->getContainerId()) {
                    if ($job->getCreatedAt()->getTimestamp() + 86400 <= time()) {
                        $job->change('failed', 'L’envoi a expiré avant son démarrage. Prépare une nouvelle publication.');
                        $this->em->flush();

                        return;
                    }
                    // Lost creation responses can leave an orphan container, never a published Reel.
                    $job->container($this->api->createReel($connection, $this->links->url($job), $job->getCaption(), $job->isShareToFeed(), $job->isSynthetic()));
                    $this->em->flush();
                } else {
                    $status = $this->api->containerStatus($connection, $job->getContainerId());
                    if ('PUBLISHED' === $status) {
                        $job->published($job->getRemoteId());
                    } elseif (in_array($status, ['ERROR', 'EXPIRED'], true)) {
                        $job->change('failed', 'Instagram a refusé la vidéo ou le conteneur a expiré. Vérifie le format avant un nouvel envoi.');
                    } elseif ('FINISHED' === $status && !$job->isPublishAttempted()) {
                        // Persist intent before the non-idempotent remote POST; never repeat that POST.
                        $job->attempt();
                        $this->em->flush();
                        $job->published($this->api->publishReel($connection, $job->getContainerId()));
                    } else {
                        $job->poll();
                        if ($job->getPolls() >= 5) {
                            $job->change($job->isPublishAttempted() ? 'review' : 'paused', $job->isPublishAttempted() ? 'Résultat incertain. Vérifie ton profil Instagram avant tout nouvel envoi ; VydFlow ne republiera pas automatiquement.' : 'Le traitement prend plus de temps. Reprends le suivi dans quelques minutes.');
                        }
                    }
                }
            } catch (\Throwable) {
                $job->failure();
                if ($job->getFailures() >= 5) {
                    $job->change($job->isPublishAttempted() ? 'review' : 'paused', 'Instagram est indisponible. Vérifie ton profil avant tout nouvel envoi.');
                }
            }
            $this->em->wrapInTransaction(function () use ($job, $message): void {
                $this->em->flush();
                if (in_array($job->getStatus(), ['queued', 'processing', 'publishing'], true)) {
                    $this->bus->dispatch($message, [new DelayStamp(60000)]);
                }
            });
        });
    }
}
