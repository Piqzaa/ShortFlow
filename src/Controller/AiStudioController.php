<?php

namespace App\Controller;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Form\AiIdeaType;
use App\Service\AiCatalog;
use App\Service\AiGenerations;
use App\Service\AiKeys;
use App\Service\FalOAuth;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AiStudioController extends AbstractController
{
    #[Route('/studio/creation', name: 'app_studio', methods: ['GET', 'POST'])]
    #[Route('/studio/ia', name: 'app_ai_studio', methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $user, AiCatalog $catalog, AiKeys $keys, FalOAuth $falOAuth, EntityManagerInterface $em, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $idea = $request->getSession()->get('ai_idea', ['seconds' => 5, 'ratio' => '9:16']);
        $idea += ['accessMode' => 'api'];
        $form = $this->createForm(AiIdeaType::class, $idea)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $request->getSession()->set('ai_idea', $data);
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
                if ('mcp' === $data['accessMode']) {
                    $job->useMcp();
                }
                $em->persist($job);
                $em->flush();

                return $this->redirectToRoute('app_ai_generation', ['id' => $job->getId()], 303);
            }
        }
        $jobs = $em->getRepository(AiGeneration::class)->findBy(['owner' => $user], ['createdAt' => 'DESC', 'id' => 'DESC'], 12);

        $connected = [];
        foreach (AiCatalog::PROVIDERS as $id => $name) {
            $connected[$id] = null !== $keys->connection($user, $id) || ('fal' === $id && null !== $falOAuth->connection($user));
        }
        $connected['fal-api'] = null !== $keys->connection($user, 'fal');
        $connected['fal-mcp'] = null !== $falOAuth->connection($user);

        return $this->render('ai/studio.html.twig', ['ideaForm' => $form, 'models' => AiCatalog::MODELS, 'providers' => AiCatalog::PROVIDERS, 'jobs' => $jobs, 'connected' => $connected], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/ia/generations/{id}', name: 'app_ai_generation', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function generation(int $id, Request $request, #[CurrentUser] User $user, AiGenerations $generations, AiCatalog $catalog, AiKeys $keys, FalOAuth $falOAuth): Response
    {
        $job = $generations->owned($id, $user) ?? throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ai_generation_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            try {
                if ('retry' === $request->request->getString('intent')) {
                    $generations->retry($user, $id, '1' === $request->request->getString('confirm'));
                } elseif ('1' === $request->request->getString('confirm')) {
                    $generations->confirm($user, $id);
                } else {
                    throw new \RuntimeException('Confirme le coût fournisseur avant de lancer.');
                }
            } catch (\RuntimeException $error) {
                $this->addFlash('error', $error->getMessage());
            }

            return $this->redirectToRoute('app_ai_generation', ['id' => $id], 303);
        }

        $model = $catalog->model($job->getModel());

        return $this->render('ai/generation.html.twig', ['job' => $job, 'model' => $model, 'providerName' => $catalog->provider($model['provider']), 'enabled' => $generations->enabled(), 'hasKey' => $keys->hasAccess($user, $model['provider'], $job->getAccessMode()), 'mcpAccount' => 'mcp' === $job->getAccessMode() ? $falOAuth->accountId($user) : null], new Response(headers: ['Cache-Control' => 'private, no-store']));
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
        foreach (AiCatalog::PROVIDERS as $id => $name) {
            $connections[$id] = $keys->connection($user, $id);
        }
        $preferred = $request->query->getString('provider', 'fal');
        $providers = AiCatalog::PROVIDERS;
        if (isset($providers[$preferred])) {
            $providers = [$preferred => $providers[$preferred]] + $providers;
        }

        return $this->render('ai/keys.html.twig', ['connections' => $connections, 'providers' => $providers, 'ready' => $keys->ready(), 'back' => $back, 'falMcpConnected' => null !== $falOAuth->connection($user)], new Response(headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']));
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
                } elseif ('check' === $intent) {
                    if ($oauth->finish($user, $session)) {
                        $this->addFlash('success', 'Fal est connecté sans copie de clé API. Choisis la connexion navigateur dans le Studio pour l’utiliser.');

                        return $this->redirectToRoute('idea' === $back ? 'app_studio' : ($back ? 'app_ai_generation' : 'app_ai_keys'), $back && 'idea' !== $back ? ['id' => (int) $back] : [], 303);
                    }
                    $this->addFlash('notice', 'L’autorisation est encore en attente chez Fal. Termine-la puis vérifie à nouveau.');
                } elseif ('start' === $intent) {
                    $oauth->begin($user, $session);
                }
            } catch (\Throwable $error) {
                $this->addFlash('error', \RuntimeException::class === $error::class ? $error->getMessage() : 'La connexion Fal n’a pas abouti. Vérifie l’autorisation et le compte MCP actif chez Fal. Aucun nouvel envoi de vidéo n’a été demandé.');
            }

            return $this->redirectToRoute('app_ai_fal_connect', ['next' => $back], 303);
        }
        $pending = $oauth->pending($user, $session);
        $public = $pending ? ['code' => $pending['user_code'], 'uri' => $pending['uri']] : null;

        return $this->render('ai/fal_connect.html.twig', ['pending' => $public, 'connected' => null !== $oauth->connection($user), 'back' => $back], new Response(headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']));
    }
}
