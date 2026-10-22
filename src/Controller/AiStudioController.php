<?php

namespace App\Controller;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Form\AiIdeaType;
use App\Form\AiReferenceType;
use App\Repository\AiGenerationRepository;
use App\Service\AiCatalog;
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
    public function index(Request $request, #[CurrentUser] User $user, AiCatalog $catalog, AiKeys $keys, FalOAuth $falOAuth, AiReferenceStorage $references, EntityManagerInterface $em, FormFactoryInterface $forms, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        if (!$request->getSession()->has('ai_idea_revision')) {
            $request->getSession()->set('ai_idea_revision', bin2hex(random_bytes(16)));
        }
        $idea = $request->getSession()->get('ai_idea', ['seconds' => 5, 'ratio' => '9:16']);
        $idea += ['accessMode' => null !== $keys->connection($user, 'fal') ? 'api' : 'mcp', 'source' => 'text', 'reference' => '', 'endReference' => '', 'useEnd' => false, 'imageFit' => 'crop'];
        $connected = [];
        foreach (AiCatalog::PROVIDERS as $id => $name) {
            $connected[$id] = null !== $keys->connection($user, $id) || ('fal' === $id && null !== $falOAuth->connection($user));
        }
        $connected['fal-api'] = null !== $keys->connection($user, 'fal');
        $connected['fal-mcp'] = null !== $falOAuth->connection($user);
        $connecting = $request->isMethod('POST') && 'connect' === $request->request->getString('intent');
        $form = $this->createForm(AiIdeaType::class, $idea, ['validation_groups' => $connecting ? ['connection'] : ['Default'], 'connected_providers' => $connected, 'favorite_models' => $user->getAiFavorites()])->handleRequest($request);
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
                if ($form->isSubmitted() && 'image' === $form->get('source')->getData() && ('reference' === $field || $form->get('useEnd')->getData())) {
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
            } else {
                $title = trim($data['title'] ?? '');
                if ('' === $title) {
                    $title = mb_substr(preg_replace('/\s+/u', ' ', trim($data['prompt'])), 0, 80);
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
                $em->persist($job);
                $em->flush();

                return $this->redirectToRoute('app_ai_generation', ['id' => $job->getId()], 303);
            }
        }
        $jobs = $em->getRepository(AiGeneration::class)->findBy(['owner' => $user], ['createdAt' => 'DESC', 'id' => 'DESC'], 1);

        return $this->render('ai/studio.html.twig', ['ideaForm' => $form, 'referenceUrl' => $referenceUrl, 'endReferenceUrl' => $endReferenceUrl, 'endReferenceForm' => $forms->createNamed('ai_end_reference', AiReferenceType::class, null, ['action' => $this->generateUrl('app_ai_reference_upload', ['slot' => 'end']), 'label' => 'Image de fin']), 'referenceForm' => $this->createForm(AiReferenceType::class, options: ['action' => $this->generateUrl('app_ai_reference_upload')]), 'models' => AiCatalog::MODELS, 'groups' => $catalog->groups(), 'providers' => AiCatalog::PROVIDERS, 'jobs' => $jobs, 'connected' => $connected], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
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
            if ('image' !== $form->getData()['source'] || !$form->getData()[$field] || ('endReference' === $field && !$form->getData()['useEnd'])) {
                continue;
            }
            try {
                $references->path($user, $form->getData()[$field]);
            } catch (\RuntimeException) {
                return $this->json(['saved' => false], 422, ['Cache-Control' => 'private, no-store']);
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
        if (!in_array($slot, ['start', 'end'], true)) {
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
                    $idea = $request->getSession()->get('ai_idea', []);
                    $idea['end' === $slot ? 'endReference' : 'reference'] = $name;
                    $idea['source'] = 'image';
                    if ('end' === $slot) {
                        $idea['useEnd'] = true;
                    }
                    $model = AiCatalog::MODELS[$idea['model'] ?? ''] ?? [];
                    if (!($model['image'] ?? false) || ('end' === $slot && !isset($model['endImageField']))) {
                        $idea['model'] = 'kling-25-image';
                        $model = AiCatalog::MODELS['kling-25-image'];
                    }
                    if (!in_array($idea['ratio'] ?? '', $model['ratios'], true)) {
                        $idea['ratio'] = $model['ratios'][0];
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
    public function generation(int $id, Request $request, #[CurrentUser] User $user, AiGenerations $generations, AiCatalog $catalog, AiKeys $keys, FalOAuth $falOAuth, \App\Service\AiSeriesStudio $seriesStudio): Response
    {
        $job = $generations->owned($id, $user) ?? throw $this->createNotFoundException();
        $episode = $seriesStudio->episodeFor($job);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ai_generation_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            try {
                if ('retry' === $request->request->getString('intent')) {
                    $generations->retry($user, $id, '1' === $request->request->getString('confirm'));
                } elseif ('1' === $request->request->getString('confirm')) {
                    $generations->confirm($user, $id, $episode ? $request->request->getString('idea_revision') : null);
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
    public function history(Request $request, #[CurrentUser] User $user, AiGenerationRepository $jobs): Response
    {
        $query = mb_substr(trim($request->query->getString('q')), 0, 120);
        $status = $request->query->getString('status');
        if ('' !== $status && !isset(AiGeneration::LABELS[$status])) {
            throw $this->createNotFoundException();
        }

        return $this->render('ai/history.html.twig', ['history' => $jobs->history($user, $query, $status, $request->query->getInt('page', 1)), 'query' => $query, 'status' => $status, 'labels' => AiGeneration::LABELS, 'models' => AiCatalog::MODELS, 'providers' => AiCatalog::PROVIDERS], new Response(headers: ['Cache-Control' => 'private, no-store']));
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
        $request->getSession()->set('ai_idea_revision', bin2hex(random_bytes(16)));
        $request->getSession()->set('ai_idea', ['title' => $job->getTitle(), 'prompt' => $job->getPrompt(), 'model' => $job->getModel(), 'seconds' => $job->getSeconds(), 'ratio' => $job->getRatio(), 'accessMode' => $job->getAccessMode(), 'source' => null !== $job->getReferenceImage() ? 'image' : 'text', 'reference' => $job->getReferenceImage(), 'endReference' => $job->getEndReferenceImage(), 'useEnd' => null !== $job->getEndReferenceImage(), 'imageFit' => $job->getImageFit()]);
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
