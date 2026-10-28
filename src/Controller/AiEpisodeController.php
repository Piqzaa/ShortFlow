<?php

namespace App\Controller;

use App\Entity\AiShot;
use App\Entity\User;
use App\Form\AiStoryStartType;
use App\Form\AiStoryType;
use App\Repository\VideoAssetRepository;
use App\Service\AiCatalog;
use App\Service\AiEpisodeFlow;
use App\Service\AiGenerations;
use App\Service\EpisodePublications;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AiEpisodeController extends AbstractController
{
    #[Route('/studio/series/episodes/{id}/publication', name: 'app_episode_publication', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function publication(int $id, Request $request, #[CurrentUser] User $user, AiEpisodeFlow $flow, EpisodePublications $publications): Response
    {
        $episode = $flow->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('episode_publication_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $draft = $publications->prepare($user, $episode, $request->request->getInt('revision'));

            return $this->redirectToRoute('draft' === $draft->getStatus() ? 'app_composer_start' : 'app_draft', ['draft' === $draft->getStatus() ? 'draft' : 'id' => $draft->getId()], 303);
        } catch (\App\Service\PublicationException $error) {
            $this->addFlash('error', $error->getMessage());

            return $this->redirectToRoute('app_series_episode', ['id' => $id], 303);
        }
    }

    #[Route('/studio/series/episodes/{id}', name: 'app_series_episode', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, #[CurrentUser] User $user, AiEpisodeFlow $flow, AiGenerations $generations, EntityManagerInterface $em, VideoAssetRepository $videos, EpisodePublications $publications, \App\Service\SeriesGenerationConsent $seriesConsent, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $episode = $flow->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$episode->getFlowState() && $episode->getGeneration() && 'prepared' !== $episode->getGeneration()->getStatus()) {
            throw $this->createNotFoundException('Cet épisode possède déjà une génération individuelle.');
        }
        if (in_array($episode->getFlowState(), [null, 'draft'], true) && !$request->request->has('ai_story_start') && !$request->request->has('ai_story') && (!$request->isMethod('POST') || $request->request->has('ai_idea'))) {
            $review = $request->query->getBoolean('review');
            if ($review) {
                try {
                    $flow->quote($episode);
                } catch (\RuntimeException|\InvalidArgumentException $failure) {
                    $this->addFlash('error', $failure->getMessage());
                    $review = false;
                }
            }
            if (!$review || $request->request->has('ai_idea')) {
                return $this->forward(AiStudioController::class.'::index', ['preparingEpisode' => $id]);
            }
        }
        $form = null;
        $quote = null;
        $error = null;
        $invalid = false;
        $layouts = [];
        if (null === $episode->getFlowState()) {
            $submitted = $request->request->all('ai_story_start');
            $target = filter_var($submitted['duration'] ?? 30, FILTER_VALIDATE_INT);
            if ($request->isMethod('POST') && $target >= 1 && $target <= 60) {
                $layouts = $flow->suggestions($episode->getPreparationRecipe()['model'], $target);
            }
            $form = $this->createForm(AiStoryStartType::class, ['duration' => 30, 'continuity' => 'independent', 'mute' => false, 'revision' => (string) $episode->getRevision()], ['csrf_token_id' => 'story_start_'.$id, 'layouts' => array_column($layouts, 'value', 'label')]);
        } elseif ('draft' === $episode->getFlowState()) {
            $rows = array_map(static fn (AiShot $s): array => ['prompt' => $s->getPrompt(), 'seconds' => $s->getSeconds()] + $s->getInputs(), $flow->shots($episode));
            $form = $this->createForm(AiStoryType::class, ['title' => $episode->getTitle(), 'shots' => $rows, 'revision' => (string) $episode->getRevision()], ['durations' => AiCatalog::MODELS[$episode->getFlowRecipe()['model']]['seconds'], 'csrf_token_id' => 'story_'.$id]);
        }
        if ($form) {
            $form->handleRequest($request);
        }
        if ($request->isMethod('POST')) {
            try {
                if (!$limiter->create('episode-flow-'.$user->getId())->consume()->isAccepted()) {
                    throw new \RuntimeException('Trop de demandes rapprochées. Réessaie dans quelques minutes.');
                }
                if ($form?->isSubmitted()) {
                    $invalid = true;
                    if ($form->isValid()) {
                        $data = $form->getData();
                        if (null === $episode->getFlowState()) {
                            $layouts = $flow->suggestions($episode->getPreparationRecipe()['model'], $data['duration']);
                            if (!in_array($data['layout'] ?? '', array_column($layouts, 'value'), true)) {
                                $invalid = false;
                            } else {
                                $flow->initialize($episode, (int) $data['revision'], $data['duration'], $data['continuity'], $data['mute'], $data['layout'] ?? '');

                                return $this->redirectToRoute('app_series_episode', ['id' => $id], 303);
                            }
                        } else {
                            $rows = array_values($data['shots']);
                            $action = $request->request->getString('plan_action');
                            [$command, $index] = array_pad(explode(':', $action, 2), 2, '0');
                            $index = ctype_digit($index) ? (int) $index : -1;
                            if ('add' === $command && count($rows) < 8) {
                                $rows[] = ['prompt' => '', 'seconds' => min(AiCatalog::MODELS[$episode->getFlowRecipe()['model']]['seconds'])];
                            } elseif ('detach_translation' === $command && isset($rows[$index])) {
                                $rows[$index]['translatedIdentity'] = $rows[$index]['translationSource'] = '';
                            } elseif ('remove' === $command && isset($rows[$index]) && count($rows) > 1) {
                                array_splice($rows, $index, 1);
                            } elseif (in_array($command, ['up', 'down'], true)) {
                                $next = $index + ('up' === $command ? -1 : 1);
                                if (isset($rows[$index], $rows[$next])) {
                                    [$rows[$index], $rows[$next]] = [$rows[$next], $rows[$index]];
                                }
                            }
                            $flow->save($episode, (int) $data['revision'], $data['title'], $rows);
                        }

                        if ($episode->getFlowState()) {
                            return $this->redirectToRoute('app_series_episode', ['id' => $id, 'review' => 'estimate' === $request->request->getString('intent') ? 1 : 0], 303);
                        }
                    }
                } else {
                    if (!$this->isCsrfTokenValid('episode_flow_'.$id, $request->request->getString('_token'))) {
                        throw $this->createAccessDeniedException();
                    }
                    $intent = $request->request->getString('intent');
                    if ('confirm' === $intent) {
                        if (!$request->request->getBoolean('confirm')) {
                            throw new \RuntimeException('Confirme le coût total fournisseur avant de lancer.');
                        }
                        $seriesConsent->manual($episode, fn () => $flow->confirm($episode, $request->request->getString('quote'), $request->request->getInt('redo')));
                    } elseif ('review_remaining' === $intent) {
                        $flow->reviewRemaining($episode, $request->request->getInt('revision'));

                        return $this->redirectToRoute('app_series_episode', ['id' => $id, 'review' => 1], 303);
                    } elseif ('correct_unsent' === $intent) {
                        $flow->correctUnsent($episode, $request->request->getInt('shot'), $request->request->getInt('revision'), $request->request->getString('remaining_prompt'), $request->request->getString('translated_identity'));

                        return $this->redirectToRoute('app_series_episode', ['id' => $id, 'review' => 1], 303);
                    } elseif ('repair_prompt' === $intent) {
                        $flow->stageRepair($episode, $request->request->getInt('redo'), $request->request->getInt('revision'), $request->request->getString('repair_prompt'));

                        return $this->redirectToRoute('app_series_episode', ['id' => $id, 'redo' => $request->request->getInt('redo'), '_fragment' => 'story-quote'], 303);
                    } elseif ('pause' === $intent) {
                        $flow->pause($episode);
                    } elseif ('stop' === $intent) {
                        if (!$request->request->getBoolean('confirm')) {
                            throw new \RuntimeException('Confirme l’arrêt des étapes restantes.');
                        }
                        $flow->stop($episode);
                    } elseif ('resume' === $intent) {
                        if (!$request->request->getBoolean('confirm')) {
                            throw new \RuntimeException('Confirme la reprise des plans restant à générer et de leur coût déjà accepté.');
                        }
                        $seriesConsent->assertAllowed($episode);
                        $seriesConsent->manual($episode, fn () => $flow->resume($episode));
                    } elseif ('approve' === $intent) {
                        $video = $videos->owned($request->request->getInt('video'), $user) ?? throw $this->createNotFoundException();
                        $em->getConnection()->transactional(function () use ($em, $user, $episode, $video, $request): void {
                            $em->lock($user, LockMode::PESSIMISTIC_WRITE);
                            $em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
                            if ($episode->getRevision() !== $request->request->getInt('revision')) {
                                throw new \RuntimeException('Cet épisode a changé. Vérifie à nouveau le résultat avant de le valider.');
                            }
                            $episode->approve($video);
                            $em->flush();
                        });
                        $this->addFlash('success', 'Cette version de l’épisode est validée. Tu peux préparer sa publication et choisir une date.');
                    } else {
                        throw $this->createAccessDeniedException();
                    }

                    return $this->redirectToRoute('app_series_episode', ['id' => $id], 303);
                }
            } catch (\RuntimeException|\InvalidArgumentException $failure) {
                if ($failure instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface || $failure instanceof \Symfony\Component\Security\Core\Exception\AccessDeniedException) {
                    throw $failure;
                }
                if ($form?->isSubmitted()) {
                    $form->addError(new FormError($failure->getMessage()));
                } else {
                    $error = $failure->getMessage();
                }
                $invalid = true;
            }
        }
        if ('review' === $episode->getFlowState() || $request->query->getBoolean('review') || $request->query->getInt('redo')) {
            try {
                $quote = $flow->quote($episode, $request->query->getInt('redo'));
            } catch (\RuntimeException|\InvalidArgumentException $failure) {
                $error = $failure->getMessage();
            }
        }
        $recipe = $episode->getPreparationRecipe();
        $library = 'ready' === $episode->getFlowState() ? $videos->findBy(['owner' => $user], ['createdAt' => 'DESC'], 100) : [];

        return $this->render('series/episode.html.twig', ['publicationSummaries' => $publications->summaries([$episode])[$id] ?? [], 'layouts' => $layouts, 'episode' => $episode, 'shots' => $flow->shots($episode), 'storyForm' => $form?->createView(), 'quote' => $quote, 'error' => $error, 'model' => AiCatalog::MODELS[$recipe['model']], 'recipe' => $recipe, 'enabled' => $generations->enabled(), 'library' => $library, 'automaticOccurrence' => $em->getRepository(\App\Entity\SeriesOccurrence::class)->findOneBy(['episode' => $episode])?->isAutomatic() ?? false, 'watchRevision' => $flow->revisionHash($episode)], new Response(status: $invalid ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series/episodes/{id}/etat', name: 'app_series_episode_state', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function state(int $id, #[CurrentUser] User $user, AiEpisodeFlow $flow): Response
    {
        $episode = $flow->owned($id, $user) ?? throw $this->createNotFoundException();

        return $this->json(['revision' => $flow->revisionHash($episode)], headers: ['Cache-Control' => 'private, no-store']);
    }
}
