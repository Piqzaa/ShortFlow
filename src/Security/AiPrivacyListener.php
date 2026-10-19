<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;

#[AsEventListener(event: 'kernel.request', priority: 2048)]
final class AiPrivacyListener
{
    public function __construct(private ?Profiler $profiler = null)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/studio/connexions/ia') || str_starts_with($event->getRequest()->getPathInfo(), '/studio/ia')) {
            $this->profiler?->disable();
            if (str_starts_with($request->getPathInfo(), '/studio/connexions/ia') && $request->isMethod('POST')) {
                $values = $request->request->all();
                $key = is_string($values['apiKey'] ?? null) ? $values['apiKey'] : '';
                unset($values['apiKey']);
                // Remove both parsed and raw payload before routing, security and any exception rendering.
                $request->initialize($request->query->all(), $values, $request->attributes->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), '');
                $request->attributes->set('_ai_key', new \SensitiveParameterValue($key));
            }
        }
    }
}
