<?php

namespace App\Controller;

use App\Entity\AiPortraitDraft;
use App\Entity\User;
use App\Form\AiPortraitDraftType;
use App\Service\AiPortraitDrafts;
use App\Service\AiPortraitQuotes;
use App\Service\AiPortraits;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AiPortraitDraftController extends AbstractController
{
    #[Route('/studio/personnages/idees', name: 'app_character_ideas', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $owner, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(AiPortraitDraft::class);
        $pages = max(1, (int) ceil($repo->count(['owner' => $owner]) / 12));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        return $this->render('character/ideas.html.twig', ['drafts' => $repo->findBy(['owner' => $owner], ['id' => 'DESC'], 12, ($page - 1) * 12), 'page' => $page, 'pages' => $pages], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/personnages/idees/nouveau', name: 'app_character_idea_new', methods: ['GET', 'POST'])]
    #[Route('/studio/personnages/idees/{id}', name: 'app_character_idea_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, #[CurrentUser] User $owner, AiPortraitDrafts $drafts, AiPortraitQuotes $quotes, AiPortraits $portraits, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter, ?int $id = null): Response
    {
        $draft = null !== $id ? ($drafts->owned($owner, $id) ?? throw $this->createNotFoundException()) : null;
        $form = $this->createForm(AiPortraitDraftType::class, ['name' => $draft?->getName() ?? '', 'idea' => $draft?->getIdea() ?? '', 'appearance' => $draft?->getAppearance() ?? '', 'style' => $draft?->getStyle() ?? '', 'customPrompt' => $draft?->getCustomPrompt() ?? '', 'revision' => (string) ($draft?->getRevision() ?? 0)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$limiter->create('portrait-draft-'.$owner->getId())->consume()->isAccepted()) {
                    throw new \RuntimeException('Trop de sauvegardes rapprochées. Garde cette page ouverte et réessaie dans quelques minutes.');
                }
                $saved = $drafts->save($owner, $id, $form->getData());
                $this->addFlash('success', 'Brouillon enregistré. Aucune image demandée.');

                return $this->redirectToRoute('app_character_idea_edit', ['id' => $saved->getId()], 303);
            } catch (\RuntimeException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('character/idea.html.twig', ['draft' => $draft, 'draftForm' => $form, 'quoteStatus' => $draft ? $quotes->status($draft) : null, 'savedPrompt' => $draft ? $quotes->prompt($draft) : null, 'portraitEnabled' => $portraits->enabled(), 'portraits' => $draft ? $portraits->recent($draft) : []], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }
}
