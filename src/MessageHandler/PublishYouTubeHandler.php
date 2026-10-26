<?php

namespace App\MessageHandler;

use App\Entity\YouTubePublication;
use App\Message\PublishYouTube;
use App\Service\PrivateVideoStorage;
use App\Service\PublicationException;
use App\Service\PublicationLock;
use App\Service\TokenVault;
use App\Service\YouTubeApi;
use App\Service\YouTubePublishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final class PublishYouTubeHandler
{
    public function __construct(private EntityManagerInterface $em, private YouTubeApi $api, private YouTubePublishing $publishing, private PrivateVideoStorage $storage, private TokenVault $vault, private PublicationLock $lock, private MessageBusInterface $bus)
    {
    }

    public function __invoke(PublishYouTube $message): void
    {
        $job = $this->em->find(YouTubePublication::class, $message->publicationId);
        if (!$job || !in_array($job->getStatus(), ['queued', 'uploading', 'processing'], true)) {
            return;
        }
        $this->lock->run((int) $job->getOwner()->getId(), function () use ($job): void {
            $this->em->refresh($job);
            if (!in_array($job->getStatus(), ['queued', 'uploading', 'processing'], true)) {
                return;
            }
            try {
                $this->step($job);
            } catch (\Throwable) {
                // Never log provider exceptions: they can contain tokens or session URLs.
                $job->failure();
                $job->change($job->getFailures() >= 5 ? 'paused' : $job->getStatus(), 'L’envoi est interrompu. Vérifie la connexion YouTube ; le transfert pourra être repris.');
            }
            $this->em->wrapInTransaction(function () use ($job): void {
                $this->em->flush();
                if (in_array($job->getStatus(), ['queued', 'uploading', 'processing'], true)) {
                    $this->bus->dispatch(new PublishYouTube((int) $job->getId()), [new DelayStamp('processing' === $job->getStatus() || $job->getFailures() > 0 ? 30000 : 1000)]);
                }
            });
        });
    }

    private function step(YouTubePublication $job): void
    {
        $connection = $this->publishing->connection($job->getOwner(), $job->getChannelId());
        if ($connection) {
            $this->em->refresh($connection);
        }
        if (!$connection || $connection->getChannelId() !== $job->getChannelId()) {
            $job->change('paused', 'Reconnecte la chaîne destinataire pour reprendre cet envoi.');

            return;
        }
        if ($job->getRemoteId()) {
            $access = $this->api->access($connection);
            $item = $this->api->processing($access, $job->getRemoteId());
            $processing = $item['processingDetails']['processingStatus'] ?? null;
            $upload = $item['status']['uploadStatus'] ?? null;
            if (in_array($processing, ['failed', 'terminated'], true) || in_array($upload, ['failed', 'rejected', 'deleted'], true)) {
                $job->change('failed', 'YouTube n’a pas accepté cette vidéo. Consulte YouTube Studio avant de préparer un nouvel envoi.');
            } elseif ('succeeded' === $processing || 'processed' === $upload) {
                $job->complete($item['status']['privacyStatus'] ?? null);
            } elseif ($job->getUpdatedAt() < new \DateTimeImmutable('-24 hours')) {
                $job->change('paused', 'Le traitement prend plus de temps que prévu. Vérifie YouTube Studio puis reprends le suivi.');
            }

            return;
        }
        $video = $job->getSource();
        if (!$video) {
            $job->change('failed', 'La vidéo source est indisponible.');

            return;
        }
        $path = $this->storage->path($video->getStorageName());
        if (!is_file($path) || filesize($path) !== $video->getByteSize()) {
            $job->change('failed', 'La vidéo source est indisponible.');

            return;
        }
        if (!$job->getUploadSession()) {
            try {
                $this->publishing->validateVideo($video);
            } catch (PublicationException $error) {
                $job->change('failed', $error->getMessage());

                return;
            }
            $session = $this->api->start($job, $this->api->access($connection));
            $job->session($this->vault->encrypt($session));
            // Persist the resumable session BEFORE any video bytes leave the server.
            $this->em->flush();
        }
        $session = $this->vault->decrypt((string) $job->getUploadSession());
        $access = $this->api->access($connection);
        $result = $this->api->probe($session, $access, $video->getByteSize());
        if (!$result['expired'] && !$result['id'] && $result['offset'] < $video->getByteSize()) {
            $result = $this->api->chunk($session, $access, $path, $result['offset'], $video->getByteSize(), $video->getMimeType());
        }
        if ($result['expired']) {
            $job->change('review', 'La session a expiré. Vérifie YouTube Studio avant tout nouvel envoi pour éviter un doublon.');

            return;
        }
        $job->progress($result['offset']);
        if ($result['id']) {
            $job->uploaded($result['id']);
        }
    }
}
