<?php

namespace App\MessageHandler;

use App\Entity\TikTokPublication;
use App\Message\PublishTikTok;
use App\Service\PublicationLock;
use App\Service\TikTokApi;
use App\Service\TikTokMediaLink;
use App\Service\TikTokPublishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsMessageHandler]
final class PublishTikTokHandler
{
    public function __construct(private EntityManagerInterface $em, private TikTokPublishing $publishing, private TikTokApi $api, private TikTokMediaLink $links, private PublicationLock $lock, private MessageBusInterface $bus, #[Autowire(service: 'limiter.tiktok_init')] private RateLimiterFactory $limiter)
    {
    }

    public function __invoke(PublishTikTok $message): void
    {
        $job = $this->em->find(TikTokPublication::class, $message->id);
        if (!$job) {
            return;
        }
        $this->lock->run((int) $job->getOwner()->getId(), function () use ($job, $message): void {
            $this->em->refresh($job);
            if (!in_array($job->getStatus(), ['queued', 'processing', 'publishing'], true)) {
                return;
            }
            // An init can already publish. A lost response must never trigger another init.
            if ($job->isInitAttempted() && !$job->getPublishId()) {
                $job->change('review', 'Résultat incertain. Vérifie ton profil TikTok avant un nouvel envoi.');
                $this->em->flush();

                return;
            }
            $connection = $this->publishing->connection($job->getOwner());
            if ($connection) {
                $this->em->refresh($connection);
            }
            if (!$this->publishing->ready($job->getOwner()) || !$connection || $connection->getOpenId() !== $job->getOpenId()) {
                $job->change('paused', 'Reconnecte le compte TikTok destinataire avant de reprendre.');
                $this->em->flush();

                return;
            }
            if (!$job->getSource() || $job->getSource()->getOwner()->getId() !== $job->getOwner()->getId()) {
                $job->change('failed', 'La vidéo source est indisponible.');
                $this->em->flush();

                return;
            }
            try {
                if (!$job->getPublishId()) {
                    if ($job->getCreatedAt()->getTimestamp() + 86400 <= time()) {
                        $job->change('failed', 'La préparation a expiré avant l’envoi.');
                    } elseif ($this->limiter->create('tiktok-'.$job->getOwner()->getId())->consume()->isAccepted()) {
                        $creator = $this->api->creator($connection);
                        $this->publishing->validateVideo($job->getSource(), $creator['max_video_post_duration_sec']);
                        $options = $job->getOptions();
                        if (!in_array($options['privacy_level'], $creator['privacy_level_options'], true)) {
                            throw new \RuntimeException();
                        }
                        foreach (['comment', 'duet', 'stitch'] as $flag) {
                            if (!$options['disable_'.$flag] && $creator[$flag.'_disabled']) {
                                throw new \RuntimeException();
                            }
                        }
                        $url = $this->links->url($job);
                        $job->attempt();
                        $this->em->flush();
                        $job->started($this->api->start($connection, $url, $options));
                    }
                } else {
                    $result = $this->api->status($connection, $job->getPublishId());
                    if ('PUBLISH_COMPLETE' === $result['status']) {
                        $job->published($result['remoteId']);
                    } elseif ('FAILED' === $result['status']) {
                        $job->change('failed', 'TikTok a refusé la vidéo. Vérifie son format et les réglages du compte.');
                    } else {
                        $job->poll();
                        if ($job->getPolls() >= 30) {
                            $job->change('paused', 'TikTok traite encore la vidéo. Reprends le suivi plus tard.');
                        }
                    }
                }
            } catch (\Throwable) {
                $job->failure();
                if ($job->isInitAttempted() && !$job->getPublishId()) {
                    $job->change('review', 'Résultat incertain. Vérifie ton profil TikTok ; aucun nouvel envoi automatique.');
                } elseif ($job->getFailures() >= 5) {
                    $job->change('paused', 'TikTok est indisponible ou les réglages ont changé. Vérifie le compte avant de reprendre.');
                }
            }
            $this->em->wrapInTransaction(function () use ($job, $message): void {
                $this->em->flush();
                if (in_array($job->getStatus(), ['queued', 'processing'], true)) {
                    $this->bus->dispatch($message, [new DelayStamp(60000)]);
                }
            });
        });
    }
}
