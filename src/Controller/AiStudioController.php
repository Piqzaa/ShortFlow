<?php

namespace App\Controller;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\User;
use App\Form\AiIdeaType;
use App\Form\AiReferenceType;
use App\Repository\AiGenerationRepository;
use App\Service\AiCatalog;
use App\Service\AiEpisodeFlow;
use App\Service\AiGenerations;
use App\Service\AiKeys;
use App\Service\AiReferenceStorage;
use App\Service\FalOAuth;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AiStudioController extends AbstractController
{
    #[Route('/studio/creation', name: 'app_studio', methods: ['GET', 'POST'])]
    #[Route('/studio/ia', name: 'app_ai_studio', methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $user, AiCatalog $catalog, AiKeys $keys, FalOAuth $falOAuth, AiReferenceStorage $references, EntityManagerInterface $em, FormFactoryInterface $forms, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter, AiEpisodeFlow $flow, \App\Service\EpisodeCovers $covers, ?int $preparingEpisode = null): Response
    {
        if (!$request->getSession()->has('ai_idea_revision')) {
            $request->getSession()->set('ai_idea_revision', bin2hex(random_bytes(16)));
        }
        $apiConnections = $keys->connectedProviders($user);
        $falConnection = $falOAuth->connection($user);
        $idea = $request->getSession()->get('ai_idea', ['seconds' => 5, 'ratio' => '9:16']);
        $idea += ['accessMode' => $apiConnections['fal'] ? 'api' : 'mcp', 'source' => 'text', 'reference' => '', 'endReference' => '', 'useEnd' => false, 'imageFit' => 'crop'];
        if ($request->isMethod('GET') && isset(AiCatalog::MODELS[$idea['model'] ?? ''])) {
            $idea['source'] = $catalog->referenceMode($idea['model']);
        }
        $episode = null;
        if ($preparingEpisode) {
            $episode = $flow->owned($preparingEpisode, $user) ?? throw $this->createNotFoundException();
            $recipe = $episode->getPreparationRecipe();
            $shots = $flow->shots($episode);
            $idea = ['title' => $episode->getTitle(), 'prompt' => $episode->getPrompt(), 'target' => $shots ? array_sum(array_map(static fn ($s): int => $s->getSeconds(), $shots)) : ($recipe['totalSeconds'] ?? 30), 'shots' => array_map(static fn ($s): array => ['prompt' => $s->getPrompt(), 'seconds' => $s->getSeconds()] + $s->getInputs(), $shots), 'source' => $catalog->referenceMode($recipe['model']), 'useEnd' => (bool) $recipe['endReference'], 'continuity' => $episode->getContinuity(), 'mute' => $episode->isMutePlans(), 'revision' => (string) $episode->getRevision()] + $recipe;
        }
        $idea += ['target' => $idea['seconds'] ?? 30, 'shots' => [], 'continuity' => 'independent', 'mute' => false, 'revision' => '0'];
        if ($episode && !$episode->isStandalone() && '' === trim($idea['prompt'])) {
            $idea['prompt'] = $episode->getSeries()->initialPrompt();
        }
        if (!$idea['shots'] && isset(AiCatalog::MODELS[$idea['model'] ?? ''])) {
            try {
                $idea['shots'] = array_map(static fn (int $s): array => ['prompt' => '', 'seconds' => $s], $flow->durations($idea['model'], $idea['target']));
            } catch (\RuntimeException) {
                // An incompatible total remains visible until explicitly adjusted.
            }
        }
        $connected = [];
        foreach (AiCatalog::PROVIDERS as $id => $name) {
            $connected[$id] = $apiConnections[$id] || ('fal' === $id && null !== $falConnection);
        }
        $connected['fal-api'] = $apiConnections['fal'];
        $connected['fal-mcp'] = null !== $falConnection;
        $connecting = $request->isMethod('POST') && 'connect' === $request->request->getString('intent');
        $form = $this->createForm(AiIdeaType::class, $idea, ['validation_groups' => $connecting ? ['connection'] : ['Default'], 'connected_providers' => $connected, 'favorite_models' => $user->getAiFavorites(), 'composed' => in_array($request->request->getString('intent'), ['compose', 'replan', 'single'], true) || $request->request->has('plan_action'), 'csrf_token_id' => $episode ? 'prepare_episode_'.$episode->getId() : 'ai_idea'])->handleRequest($request);
        $referenceUrl = $endReferenceUrl = null;
        foreach (['reference', 'endReference'] as $field) {
            $reference = $form->get($field)->getData();
            if (!is_string($reference) || '' === $reference) {
                continue;
            }
            try {
                $references->path($user, $reference);
                $url = $this->generateUrl('app_ai_reference_image', ['name' => $reference]);
                if ('reference' === $field) {
                    $referenceUrl = $url;
                } else {
                    $endReferenceUrl = $url;
                }
            } catch (\RuntimeException $error) {
                if ($form->isSubmitted() && in_array($form->get('source')->getData(), ['image', 'subject'], true) && ('reference' === $field || $form->get('useEnd')->getData())) {
                    $form->get($field)->addError(new FormError($error->getMessage()));
                }
            }
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $request->getSession()->set('ai_idea', $data);
            $request->getSession()->set('ai_idea_revision', bin2hex(random_bytes(16)));
            if (!$limiter->create('ai-idea-'.$user->getId())->consume()->isAccepted()) {
                $form->addError(new FormError('Trop de préparations rapprochées. Réessaie plus tard.'));
            } elseif ('connect' === $request->request->getString('intent')) {
                if ('mcp' === $data['accessMode']) {
                    return $this->redirectToRoute('app_ai_fal_connect', ['next' => 'idea'], 303);
                }

                return $this->redirectToRoute('app_ai_keys', ['next' => 'idea', 'provider' => $catalog->model($data['model'])['provider']], 303);
            } elseif (in_array($request->request->getString('intent'), ['compose', 'replan', 'single'], true) || $request->request->has('plan_action')) {
                try {
                    if ($request->request->has('plan_action')) {
                        $rows = array_values($data['shots']);
                        [$action, $index] = array_pad(explode(':', $request->request->getString('plan_action'), 2), 2, '0');
                        $index = ctype_digit($index) ? (int) $index : -1;
                        if ('add' === $action && count($rows) < 8) {
                            $rows[] = ['prompt' => '', 'seconds' => min($catalog->model($data['model'])['seconds'])];
                        } elseif ('remove' === $action && count($rows) > 1 && isset($rows[$index])) {
                            array_splice($rows, $index, 1);
                        } elseif (in_array($action, ['up', 'down'], true)) {
                            $next = $index + ('up' === $action ? -1 : 1);
                            if (isset($rows[$index], $rows[$next])) {
                                [$rows[$index], $rows[$next]] = [$rows[$next], $rows[$index]];
                            }
                        }
                        $data['shots'] = $rows;
                        $data['target'] = array_sum(array_column($rows, 'seconds'));
                        $saved = $flow->prepare($user, $data, $episode);

                        return $this->redirectToRoute('app_series_episode', ['id' => $saved->getId()], 303);
                    }
                    if (in_array($request->request->getString('intent'), ['replan', 'single'], true)) {
                        $single = 'single' === $request->request->getString('intent');
                        if ($single && !in_array((int) $data['target'], $catalog->model($data['model'])['seconds'], true)) {
                            throw new \RuntimeException('Ce modèle ne produit pas un seul clip de cette durée. Choisis une durée native ou plusieurs scènes ; aucun modèle ne sera remplacé.');
                        }
                        $durations = $single ? [(int) $data['target']] : $flow->durations($data['model'], (int) $data['target']);
                        if ($single) {
                            $data['continuity'] = 'single';
                        }
                        $old = array_values($data['shots']);
                        if (array_filter(array_slice($old, count($durations)), static fn (array $row): bool => '' !== trim($row['sceneDescription'] ?? ''))) {
                            throw new \RuntimeException('Ce découpage retirerait un résumé de scène. Conserve tes plans ou reporte ce texte avant de changer leur nombre.');
                        }
                        if (count($old) !== count($durations) && array_filter($old, static fn (array $row): bool => !empty($row['startReference']) || !empty($row['endReference']) || '' !== trim($row['imagePrompt'] ?? ''))) {
                            throw new \RuntimeException('Déplace ou retire les cadres et les consignes d’images avant de changer automatiquement le nombre de plans. Tes textes et images restent conservés.');
                        }
                        $data['shots'] = array_map(static fn (int $seconds, int $i): array => ['prompt' => $old[$i]['prompt'] ?? '', 'seconds' => $seconds] + array_intersect_key($old[$i] ?? [], array_flip(['startReference', 'endReference', 'referenceFit', 'imagePrompt', 'sceneDescription'])), $durations, array_keys($durations));
                        if (count($old) > count($durations)) {
                            $last = count($durations) - 1;
                            $data['shots'][$last]['prompt'] .= "\n".implode("\n", array_column(array_slice($old, count($durations)), 'prompt'));
                        }
                        $form = $this->createForm(AiIdeaType::class, $data, ['connected_providers' => $connected, 'favorite_models' => $user->getAiFavorites(), 'composed' => true, 'csrf_token_id' => $episode ? 'prepare_episode_'.$episode->getId() : 'ai_idea']);
                    } else {
                        if (!$episode) {
                            $last = $request->getSession()->get('ai_composed_preparation', []);
                            $hash = hash('sha256', serialize($data));
                            if (($last['hash'] ?? '') === $hash) {
                                $existing = $flow->owned((int) $last['id'], $user);
                                if ($existing && in_array($existing->getFlowState(), ['draft', 'running', 'assembling'], true)) {
                                    return $this->redirectToRoute('app_series_episode', ['id' => $existing->getId(), 'review' => 'draft' === $existing->getFlowState() ? 1 : 0], 303);
                                }
                            }
                        }
                        $saved = $flow->prepare($user, $data, $episode);
                        if (!$episode) {
                            $request->getSession()->set('ai_composed_preparation', ['hash' => $hash, 'id' => $saved->getId()]);
                        }

                        return $this->redirectToRoute('app_series_episode', ['id' => $saved->getId(), 'review' => 1], 303);
                    }
                } catch (\RuntimeException|\InvalidArgumentException $failure) {
                    $form->addError(new FormError($failure->getMessage()));
                }
            } elseif ($episode) {
                $form->addError(new FormError('Utilise le récapitulatif de cette préparation pour continuer.'));
            } else {
                $title = trim($data['title'] ?? '');
                if ('' === $title) {
                    $title = mb_substr(preg_replace('/\s+/u', ' ', trim($data['prompt'])), 0, 80);
                }
                if ('plans' === $request->request->getString('intent')) {
                    $recipe = ['model' => $data['model'], 'seconds' => $data['seconds'], 'ratio' => $data['ratio'], 'accessMode' => $data['accessMode'], 'reference' => ($catalog->model($data['model'])['image'] ?? false) ? $data['reference'] : null, 'endReference' => ($data['useEnd'] ?? false) ? $data['endReference'] : null, 'imageFit' => $data['imageFit'] ?? 'crop'];
                    $recipe['subjects'] = $data['subjects'] ?? [];
                    $recipe['subjectDetails'] = (new \App\Service\AiPlanInputs($catalog, $references))->subjectDetails($user, $data['model'], $recipe['subjects'], $data['subjectDetails'] ?? [], ($catalog->model($data['model'])['subjectReference'] ?? false) ? $data['reference'] : null, false);
                    $fingerprint = hash('sha256', serialize([$title, trim($data['prompt']), $recipe]));
                    $previous = $request->getSession()->get('ai_plan_preparation', []);
                    $project = null;
                    if (($previous['hash'] ?? '') === $fingerprint) {
                        $project = $em->getRepository(AiEpisode::class)->findOneBy(['id' => $previous['id'], 'owner' => $user]);
                        if ($project && in_array($project->getFlowState(), ['ready', 'stopped'], true)) {
                            $project = null;
                        }
                    }
                    if (!$project) {
                        $project = $em->getConnection()->transactional(function () use ($em, $user, $title, $data, $recipe): AiEpisode {
                            $em->lock($user, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);

                            $project = new AiEpisode($user, 1, $title, trim($data['prompt']), $recipe);
                            $em->persist($project);
                            $em->flush();

                            return $project;
                        });
                        $request->getSession()->set('ai_plan_preparation', ['hash' => $fingerprint, 'id' => $project->getId()]);
                    }

                    return $this->redirectToRoute('app_series_episode', ['id' => $project->getId()], 303);
                }
                $job = new AiGeneration($user, $title, trim($data['prompt']), $data['model'], $data['seconds'], $data['ratio'], $catalog->estimate($data['model'], $data['seconds']));
                if ($catalog->model($data['model'])['image'] ?? false) {
                    $job->attachReference($data['reference']);
                    $job->frameImages($data['imageFit'] ?? 'crop');
                    if ($data['useEnd'] ?? false) {
                        $job->attachEndReference($data['endReference']);
                    }
                }
                if ('mcp' === $data['accessMode']) {
                    $job->useMcp();
                }
                $job->attachSubjects($data['subjects'] ?? []);
                $job->attachSubjectDetails((new \App\Service\AiPlanInputs($catalog, $references))->subjectDetails($user, $data['model'], $data['subjects'] ?? [], $data['subjectDetails'] ?? [], ($catalog->model($data['model'])['subjectReference'] ?? false) ? $data['reference'] : null, false));
                $em->persist($job);
                $em->flush();

                return $this->redirectToRoute('app_ai_generation', ['id' => $job->getId()], 303);
            }
        }
        $jobs = $em->getRepository(AiGeneration::class)->findBy(['owner' => $user], ['createdAt' => 'DESC', 'id' => 'DESC'], 1);

        $projects = $episode ? [] : $em->getRepository(AiEpisode::class)->findBy(['owner' => $user], ['id' => 'DESC'], 4);

        return $this->render('ai/studio.html.twig', ['projectCovers' => $covers->episodes($user, $projects), 'episode' => $episode, 'ideaForm' => $form, 'referenceUrl' => $referenceUrl, 'endReferenceUrl' => $endReferenceUrl, 'endReferenceForm' => $forms->createNamed('ai_end_reference', AiReferenceType::class, null, ['action' => $this->generateUrl('app_ai_reference_upload', ['slot' => 'end']), 'label' => 'Image de fin']), 'referenceForm' => $this->createForm(AiReferenceType::class, options: ['action' => $this->generateUrl('app_ai_reference_upload')]), 'models' => AiCatalog::MODELS, 'groups' => $catalog->groups(), 'providers' => AiCatalog::PROVIDERS, 'projects' => $projects, 'jobs' => $jobs, 'connected' => $connected], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/creation/idee', name: 'app_ai_save_idea', methods: ['POST'])]
    public function saveIdea(Request $request, #[CurrentUser] User $user, AiReferenceStorage $references): Response
    {
        $session = $request->getSession();
        $form = $this->createForm(AiIdeaType::class, null, ['validation_groups' => ['autosave']])->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->json(['saved' => false], 422, ['Cache-Control' => 'private, no-store']);
        }
        foreach (['reference', 'endReference'] as $field) {
            if (!in_array($form->getData()['source'], ['image', 'subject'], true) || !$form->getData()[$field] || ('endReference' === $field && !$form->getData()['useEnd'])) {
                continue;
            }
            try {
                $references->path($user, $form->getData()[$field]);
            } catch (\RuntimeException) {
                return $this->json(['saved' => false], 422, ['Cache-Control' => 'private, no-store']);
            }
        }
        foreach ($form->getData()['subjects'] ?? [] as $subject) {
            try {
                $references->path($user, $subject);
            } catch (\RuntimeException) {
                return $this->json(['saved' => false], 422, ['Cache-Control' => 'private, no-store']);
            }
        }
        foreach ($form->getData()['subjectDetails'] ?? [] as $detail) {
            foreach (array_merge(['primary' === ($detail['reference'] ?? '') ? null : ($detail['reference'] ?? null)], $detail['views'] ?? []) as $name) {
                if (!$name) {
                    continue;
                }
                try {
                    if (!is_string($name)) {
                        throw new \RuntimeException('Référence invalide.');
                    }
                    $references->path($user, $name);
                } catch (\RuntimeException) {
                    return $this->json(['saved' => false], 422, ['Cache-Control' => 'private, no-store']);
                }
            }
        }
        foreach ($form->getData()['shots'] ?? [] as $shot) {
            foreach (['startReference', 'endReference'] as $field) {
                if (empty($shot[$field])) {
                    continue;
                }
                try {
                    $references->path($user, $shot[$field]);
                } catch (\RuntimeException) {
                    return $this->json(['saved' => false], 422, ['Cache-Control' => 'private, no-store']);
                }
            }
        }
        $revision = $request->request->getString('revision');
        $editor = $request->request->getString('editor');
        $sequence = $request->request->getInt('sequence');
        if ('' === $revision || !hash_equals((string) $session->get('ai_idea_revision', ''), $revision) || !preg_match('/^[a-f0-9]{32}$/D', $editor) || $sequence < 1) {
            return $this->json(['saved' => false], 409, ['Cache-Control' => 'private, no-store']);
        }
        // Keep late requests from overwriting a newer edit, confirmation or reuse.
        $sequences = $session->get('ai_idea_sequences', []);
        $key = $user->getId().':'.$editor;
        if ($sequence <= ($sequences[$key] ?? 0)) {
            return $this->json(['saved' => false], 409, ['Cache-Control' => 'private, no-store']);
        }
        unset($sequences[$key]);
        $sequences[$key] = $sequence;
        $session->set('ai_idea_sequences', array_slice($sequences, -10, null, true));
        $session->set('ai_idea', $form->getData());

        return $this->json(['saved' => true], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/studio/creation/images', name: 'app_ai_reference_upload', methods: ['POST'])]
    public function uploadReference(Request $request, #[CurrentUser] User $user, AiReferenceStorage $references, EntityManagerInterface $em, FormFactoryInterface $forms, #[Autowire(service: 'limiter.video_upload')] RateLimiterFactory $limiter): Response
    {
        $slot = $request->query->getString('slot', 'start');
        if (!in_array($slot, ['start', 'end', 'plan'], true)) {
            throw $this->createNotFoundException();
        }
        $form = $forms->createNamed('end' === $slot ? 'ai_end_reference' : 'ai_reference', AiReferenceType::class)->handleRequest($request);
        $error = 'Choisis un JPG, PNG ou WebP valide de 10 Mo maximum, puis réessaie.';
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$limiter->create('ai-reference-'.$user->getId())->consume()->isAccepted()) {
                $error = 'Trop d’images importées. Réessaie dans quelques minutes.';
            } else {
                try {
                    $file = $form->get('image')->getData();
                    if (!$file instanceof UploadedFile) {
                        throw new \RuntimeException($error);
                    }
                    $name = $em->getConnection()->transactional(function () use ($em, $user, $references, $file): string {
                        $em->lock($user, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);

                        return $references->store($user, $file);
                    });
                    if ('plan' === $slot) {
                        return $this->json(['reference' => $name, 'url' => $this->generateUrl('app_ai_reference_image', ['name' => $name])], headers: ['Cache-Control' => 'private, no-store']);
                    }
                    $idea = $request->getSession()->get('ai_idea', []);
                    $idea['end' === $slot ? 'endReference' : 'reference'] = $name;
                    $idea['source'] = ($idea['source'] ?? '') === 'subject' && 'end' !== $slot ? 'subject' : 'image';
                    if ('end' === $slot) {
                        $idea['useEnd'] = true;
                    }
                    $model = AiCatalog::MODELS[$idea['model'] ?? ''] ?? [];
                    if (!($model['image'] ?? false) || ('end' === $slot && !isset($model['endImageField']))) {
                        $idea['model'] = '';
                        $model = [];
                    }
                    if ($model && !in_array($idea['ratio'] ?? '', $model['ratios'], true)) {
                        $idea['ratio'] = '';
                    }
                    $request->getSession()->set('ai_idea', $idea);
                    if ('application/json' === $request->headers->get('Accept')) {
                        return $this->json(['reference' => $name, 'slot' => $slot, 'url' => $this->generateUrl('app_ai_reference_image', ['name' => $name])], headers: ['Cache-Control' => 'private, no-store']);
                    }

                    return $this->redirectToRoute('app_studio', status: 303);
                } catch (\RuntimeException $failure) {
                    $error = $failure->getMessage();
                }
            }
        }
        if ('application/json' === $request->headers->get('Accept')) {
            return $this->json(['error' => $error], 422, ['Cache-Control' => 'private, no-store']);
        }
        $this->addFlash('error', $error);

        return $this->redirectToRoute('app_studio', status: 303);
    }

    #[Route('/studio/creation/images/{name}', name: 'app_ai_reference_image', requirements: ['name' => '[a-f0-9]{48}\.jpg'], methods: ['GET'])]
    public function referenceImage(string $name, Request $request, #[CurrentUser] User $user, AiReferenceStorage $references): Response
    {
        try {
            $bytes = $references->bytes($user, $name, $request->query->getString('ratio', 'auto'), $request->query->getString('fit', 'crop'), $request->query->has('match') ? $request->query->getString('match') : null);
        } catch (\RuntimeException) {
            throw $this->createNotFoundException();
        }

        return new Response($bytes, headers: ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline; filename="reference.jpg"']);
    }

    #[Route('/studio/creation/favoris/{id}', name: 'app_ai_favorite', methods: ['POST'])]
    public function favorite(string $id, Request $request, #[CurrentUser] User $user, AiCatalog $catalog, EntityManagerInterface $em): Response
    {
        if (!isset($catalog->groups()[$id])) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('ai_favorite_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $favorite = $request->request->getBoolean('favorite');
        $em->wrapInTransaction(function () use ($em, $user, $id, $favorite): void {
            $em->refresh($user, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $user->favoriteAiModel($id, $favorite);
        });
        if ('application/json' === $request->headers->get('Accept')) {
            return $this->json(['favorite' => $favorite], headers: ['Cache-Control' => 'private, no-store']);
        }

        return $this->redirectToRoute('app_studio', status: 303);
    }

    #[Route('/studio/ia/generations/{id}', name: 'app_ai_generation', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function generation(int $id, Request $request, #[CurrentUser] User $user, AiGenerations $generations, AiCatalog $catalog, AiKeys $keys, FalOAuth $falOAuth, \App\Service\AiSeriesStudio $seriesStudio, EntityManagerInterface $em): Response
    {
        $job = $generations->owned($id, $user) ?? throw $this->createNotFoundException();
        $shot = $em->getRepository(\App\Entity\AiShot::class)->findOneBy(['generation' => $job]);
        if ($shot) {
            if ($request->isMethod('POST')) {
                throw $this->createAccessDeniedException('Ce plan est piloté depuis son épisode.');
            }

            return $this->redirectToRoute('app_series_episode', ['id' => $shot->getEpisode()->getId(), '_fragment' => 'shot-'.$shot->getPosition()], 303);
        }
        $episode = $seriesStudio->episodeFor($job);
        if ($episode?->getFlowState()) {
            if ($request->isMethod('POST')) {
                throw $this->createAccessDeniedException('Cette ancienne préparation a été remplacée.');
            }

            return $this->redirectToRoute('app_series_episode', ['id' => $episode->getId()], 303);
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ai_generation_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            try {
                if ('retry' === $request->request->getString('intent')) {
                    $generations->retry($user, $id, '1' === $request->request->getString('confirm'));
                } elseif ('1' === $request->request->getString('confirm')) {
                    $generations->confirm($user, $id, $episode || 'ltx-23-extend' === $job->getModel() ? $request->request->getString('idea_revision') : null);
                } else {
                    throw new \RuntimeException('Confirme le coût fournisseur avant de lancer.');
                }
            } catch (\RuntimeException $error) {
                $this->addFlash('error', $error->getMessage());
            }

            return $this->redirectToRoute('app_ai_generation', ['id' => $id], 303);
        }

        $model = $catalog->model($job->getModel());

        return $this->render('ai/generation.html.twig', ['job' => $job, 'episode' => $episode, 'model' => $model, 'providerName' => $catalog->provider($model['provider']), 'enabled' => $generations->enabled(), 'hasKey' => $keys->hasAccess($user, $model['provider'], $job->getAccessMode()), 'mcpAccount' => 'mcp' === $job->getAccessMode() ? $falOAuth->accountId($user) : null], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/creation/historique', name: 'app_ai_history', methods: ['GET'])]
    public function history(Request $request, #[CurrentUser] User $user, AiGenerationRepository $jobs, EntityManagerInterface $em): Response
    {
        $query = mb_substr(trim($request->query->getString('q')), 0, 120);
        $status = $request->query->getString('status');
        if ('' !== $status && !isset(AiGeneration::LABELS[$status])) {
            throw $this->createNotFoundException();
        }

        $projectQuery = $em->createQueryBuilder()->select('p')->from(AiEpisode::class, 'p')->where('p.owner = :owner')->setParameter('owner', $user);
        $projectTotal = (int) (clone $projectQuery)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $projectPages = max(1, (int) ceil($projectTotal / 12));
        $projectPage = min(max(1, $request->query->getInt('plans', 1)), $projectPages);
        $projects = $projectQuery->orderBy('p.id', 'DESC')->setFirstResult(($projectPage - 1) * 12)->setMaxResults(12)->getQuery()->getResult();

        return $this->render('ai/history.html.twig', ['projects' => $projects, 'projectPage' => $projectPage, 'projectPages' => $projectPages, 'history' => $jobs->history($user, $query, $status, $request->query->getInt('page', 1)), 'query' => $query, 'status' => $status, 'labels' => AiGeneration::LABELS, 'models' => AiCatalog::MODELS, 'providers' => AiCatalog::PROVIDERS], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/ia/generations/{id}/etat', name: 'app_ai_status', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function status(int $id, #[CurrentUser] User $user, AiGenerations $generations): Response
    {
        $job = $generations->owned($id, $user) ?? throw $this->createNotFoundException();

        return $this->json(['status' => $job->getStatus(), 'updatedAt' => $job->getUpdatedAt()->format('U.u'), 'stage' => $job->getProviderStage(), 'checkedAt' => $job->getProviderCheckedAt()?->format(DATE_ATOM)], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/studio/ia/generations/{id}/reutiliser', name: 'app_ai_reuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reuse(int $id, Request $request, #[CurrentUser] User $user, AiGenerations $generations): Response
    {
        $job = $generations->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('ai_reuse_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($extension = $job->getInputSettings()['extension'] ?? null) {
            return $this->redirectToRoute('app_ai_extend', ['id' => $extension['sourceId']], 303);
        }
        $request->getSession()->set('ai_idea_revision', bin2hex(random_bytes(16)));
        $request->getSession()->set('ai_idea', ['title' => $job->getTitle(), 'prompt' => $job->getPrompt(), 'model' => $job->getModel(), 'seconds' => $job->getSeconds(), 'ratio' => $job->getRatio(), 'accessMode' => $job->getAccessMode(), 'source' => (new AiCatalog())->referenceMode($job->getModel()), 'reference' => $job->getReferenceImage(), 'endReference' => $job->getEndReferenceImage(), 'useEnd' => null !== $job->getEndReferenceImage(), 'imageFit' => $job->getImageFit(), 'subjects' => $job->getInputSettings()['subjects'] ?? [], 'subjectDetails' => $job->getInputSettings()['subjectDetails'] ?? []]);
        $this->addFlash('success', 'Ton idée et ses réglages sont repris. Modifie-les puis vérifie une nouvelle estimation avant de générer.');

        return $this->redirectToRoute('app_studio', status: 303);
    }

    #[Route('/studio/connexions/ia', name: 'app_ai_keys', methods: ['GET', 'POST'])]
    public function keys(Request $request, #[CurrentUser] User $user, AiKeys $keys, FalOAuth $falOAuth, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $next = $request->query->getString('next');
        $back = 'idea' === $next || preg_match('/^[1-9][0-9]{0,8}$/D', $next) ? $next : '';
        if ($request->isMethod('POST')) {
            // Plain HTML form: no form data collector can retain the API key.
            $secret = $request->attributes->get('_ai_key');
            $key = $secret instanceof \SensitiveParameterValue ? $secret->getValue() : '';
            $request->attributes->remove('_ai_key');
            if (!$this->isCsrfTokenValid('ai_keys', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            try {
                $provider = $request->request->getString('provider', 'fal');
                $name = (new AiCatalog())->provider($provider);
                if (!$limiter->create('ai-key-'.$user->getId())->consume()->isAccepted()) {
                    throw new \InvalidArgumentException('Trop de modifications rapprochées. Réessaie plus tard.');
                }
                if ('remove' === $request->request->getString('intent')) {
                    if (!$request->request->getBoolean('confirm')) {
                        throw new \InvalidArgumentException('Confirme le retrait de la clé.');
                    }
                    $keys->remove($user, $provider);
                    $this->addFlash('success', 'La clé '.$name.' a été retirée de VydFlow.');
                } else {
                    $keys->save($user, $key, $provider);
                    $this->addFlash('success', 'Clé '.$name.' enregistrée. Son accès sera vérifié lors du premier essai autorisé.');
                }

                return $this->redirectToRoute('idea' === $back ? 'app_studio' : ($back ? 'app_ai_generation' : 'app_ai_keys'), $back && 'idea' !== $back ? ['id' => (int) $back] : [], 303);
            } catch (\InvalidArgumentException $error) {
                $this->addFlash('error', $error->getMessage());
            } finally {
                unset($key);
            }

            return $this->redirectToRoute('app_ai_keys', ['next' => $back], 303);
        }

        $connections = [];
        $maskedKeys = [];
        foreach (AiCatalog::PROVIDERS as $id => $name) {
            $connections[$id] = $keys->connection($user, $id);
            $maskedKeys[$id] = $keys->masked($user, $id);
        }
        $preferred = $request->query->getString('provider', 'fal');
        $providers = AiCatalog::PROVIDERS;
        if (isset($providers[$preferred])) {
            $providers = [$preferred => $providers[$preferred]] + $providers;
        }

        return $this->render('ai/keys.html.twig', ['connections' => $connections, 'maskedKeys' => $maskedKeys, 'providers' => $providers, 'ready' => $keys->ready(), 'back' => $back, 'falMcpConnected' => null !== $falOAuth->connection($user)], new Response(headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']));
    }

    #[Route('/studio/connexions/ia/fal', name: 'app_ai_fal_connect', methods: ['GET', 'POST'])]
    public function falConnect(Request $request, #[CurrentUser] User $user, FalOAuth $oauth, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $session = $request->getSession();
        $next = $request->query->getString('next');
        $back = 'idea' === $next || preg_match('/^[1-9][0-9]{0,8}$/D', $next) ? $next : '';
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('fal_mcp', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            try {
                if (!$limiter->create('fal-mcp-'.$user->getId())->consume()->isAccepted()) {
                    throw new \RuntimeException('Trop de demandes rapprochées. Réessaie plus tard.');
                }
                $intent = $request->request->getString('intent');
                if ('remove' === $intent) {
                    if (!$request->request->getBoolean('confirm')) {
                        throw new \RuntimeException('Confirme la déconnexion Fal.');
                    }
                    $oauth->remove($user);
                    $session->remove('fal_oauth');
                    $this->addFlash('success', 'La connexion navigateur Fal a été retirée de VydFlow. Ta clé API reste conservée.');
                } elseif ('cancel' === $intent) {
                    $session->remove('fal_oauth');
                } elseif ('start' === $intent) {
                    $url = $oauth->begin($user, $session, $this->generateUrl('app_ai_fal_callback', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL), $back);
                    $response = $this->redirect($url, 303);
                    $response->headers->set('Cache-Control', 'private, no-store');
                    $response->headers->set('Referrer-Policy', 'no-referrer');

                    return $response;
                }
            } catch (\Throwable $error) {
                $this->addFlash('error', \RuntimeException::class === $error::class ? $error->getMessage() : 'La connexion Fal n’a pas abouti. Vérifie l’autorisation et le compte MCP actif chez Fal. Aucun nouvel envoi de vidéo n’a été demandé.');
            }

            return $this->redirectToRoute('app_ai_fal_connect', ['next' => $back], 303);
        }

        return $this->render('ai/fal_connect.html.twig', ['connected' => null !== $oauth->connection($user), 'back' => $back], new Response(headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']));
    }

    #[Route('/studio/connexions/ia/fal/retour', name: 'app_ai_fal_callback', methods: ['GET'])]
    public function falCallback(Request $request, #[CurrentUser] User $user, FalOAuth $oauth): Response
    {
        $back = '';
        $callback = $request->attributes->get('_fal_callback');
        $request->attributes->remove('_fal_callback');
        $values = $callback instanceof \SensitiveParameterValue ? $callback->getValue() : ['code' => '', 'state' => '', 'denied' => true];
        try {
            $back = $oauth->finish($user, $request->getSession(), $values['code'], $values['state'], $values['denied']);
            $this->addFlash('success', 'Fal est connecté. Choisis la connexion navigateur dans le Studio pour l’utiliser.');
        } catch (\Throwable $error) {
            $this->addFlash('error', \RuntimeException::class === $error::class ? $error->getMessage() : 'Le retour Fal n’a pas abouti. Recommence depuis Connexions.');
        }
        $response = $this->redirectToRoute('idea' === $back ? 'app_studio' : ($back ? 'app_ai_generation' : 'app_ai_keys'), $back && 'idea' !== $back ? ['id' => (int) $back] : [], 303);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
