<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AiPortraitDrafts;
use App\Service\AiPortraits;
use App\Service\AiReferenceStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AiPortraitController extends AbstractController
{
    #[Route('/studio/personnages/idees/{id}/confirmer', name: 'app_character_portrait_confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirm(int $id, Request $request, #[CurrentUser] User $owner, AiPortraitDrafts $drafts, AiPortraits $portraits, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        if (!$drafts->owned($owner, $id)) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('portrait-confirm-'.$id, $request->request->getString('_token')) || '1' !== $request->request->getString('consent')) {
            throw $this->createAccessDeniedException('Confirme le coût après rechargement de cette page.');
        }
        try {
            if (!$limiter->create('portrait-confirm-'.$owner->getId())->consume()->isAccepted()) {
                throw new \RuntimeException('Trop de confirmations rapprochées. Réessaie dans quelques minutes.');
            }
            $portrait = $portraits->confirm($owner, $id, $request->request->getString('hash'));

            return $this->redirectToRoute('app_character_portrait', ['id' => $portrait->getId()], 303);
        } catch (\RuntimeException $error) {
            $this->addFlash('error', $error->getMessage());

            return $this->redirectToRoute('app_character_idea_edit', ['id' => $id], 303);
        }
    }

    #[Route('/studio/personnages/idees/demandes/{id}', name: 'app_character_portrait', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, #[CurrentUser] User $owner, AiPortraits $portraits): Response
    {
        $portrait = $portraits->owned($owner, $id) ?? throw $this->createNotFoundException();
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('portrait-action-'.$id, $request->request->getString('_token')) || '1' !== $request->request->getString('consent')) {
                throw $this->createAccessDeniedException('Confirme cette action après rechargement.');
            }
            try {
                switch ($request->request->getString('action')) {
                    case 'accept':
                        $character = $portraits->accept($owner, $id);
                        $this->addFlash('success', 'Portrait accepté. Une nouvelle fiche privée a été créée.');

                        return $this->redirectToRoute('app_character_edit', ['id' => $character->getId()], 303);
                    case 'discard':
                        $portraits->discard($owner, $id);
                        break;
                    case 'resume':
                        $portraits->resume($owner, $id);
                        break;
                    default:
                        throw new \RuntimeException('Action indisponible.');
                }

                return $this->redirectToRoute('app_character_portrait', ['id' => $id], 303);
            } catch (\RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('character/portrait.html.twig', ['portrait' => $portrait, 'portraitEnabled' => $portraits->enabled(), 'error' => $error], new Response(status: $error ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/personnages/idees/demandes/{id}/image', name: 'app_character_portrait_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function image(int $id, #[CurrentUser] User $owner, AiPortraits $portraits, AiReferenceStorage $storage): Response
    {
        $portrait = $portraits->owned($owner, $id) ?? throw $this->createNotFoundException();
        if (!in_array($portrait->getStatus(), ['ready', 'accepted'], true) || !$portrait->getResultReference()) {
            throw $this->createNotFoundException();
        }
        try {
            $path = $storage->path($owner, $portrait->getResultReference());
        } catch (\RuntimeException) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path, headers: ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], public: false);
    }
}
