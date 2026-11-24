<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\StripeBilling;
use App\Service\StripeBillingGateway;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class BillingController extends AbstractController
{
    #[Route('/studio/mon-compte/abonnement/{action}', name: 'app_billing_action', requirements: ['action' => 'souscrire|gerer'], methods: ['POST'])]
    public function action(string $action, Request $request, #[CurrentUser] User $owner, StripeBilling $billing, #[Autowire(service: 'limiter.account')] RateLimiterFactory $limiter): Response
    {
        if (!$this->isCsrfTokenValid('billing-'.$action, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$limiter->create('billing-'.$owner->getId())->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de demandes de facturation. Réessaie dans quelques minutes.');
        } else {
            try {
                $url = 'souscrire' === $action ? $billing->checkout($owner) : $billing->portal($owner);

                return $this->redirect($url, 303);
            } catch (\DomainException|ApiErrorException) {
                $this->addFlash('error', 'Impossible d’ouvrir la facturation de test. Réessaie dans quelques minutes.');
            }
        }

        return $this->redirectToRoute('app_profile', ['section' => 'subscription'], 303);
    }

    #[Route('/webhooks/stripe', name: 'app_billing_webhook', methods: ['POST'])]
    public function webhook(Request $request, StripeBillingGateway $gateway, StripeBilling $billing): Response
    {
        if (!$gateway->enabled()) {
            throw $this->createNotFoundException();
        }
        $body = $request->getContent();
        if (strlen($body) > 262144) {
            return new Response(status: 413);
        }
        try {
            $event = $gateway->event($body, $request->headers->get('Stripe-Signature', ''));
        } catch (\UnexpectedValueException|SignatureVerificationException) {
            return new Response(status: 400);
        }
        try {
            $billing->reconcile($event);
        } catch (ApiErrorException) {
            return new Response(status: 503);
        }

        return new Response(status: 204);
    }
}
