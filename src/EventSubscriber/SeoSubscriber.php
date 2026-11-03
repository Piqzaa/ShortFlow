<?php

namespace App\EventSubscriber;

use App\Service\PublicSeo;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SeoSubscriber implements EventSubscriberInterface
{
    public function __construct(private PublicSeo $seo)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -100]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && (!$event->getResponse()->isSuccessful() || !$this->seo->indexable($event->getRequest()))) {
            $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
    }
}
