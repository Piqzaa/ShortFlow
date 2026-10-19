<?php

namespace App\Controller;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Form\AiIdeaType;
use App\Service\AiCatalog;
use App\Service\AiGenerations;
use App\Service\AiKeys;
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
    public function index(Request $request, #[CurrentUser] User $user, AiCatalog $catalog, AiKeys $keys, EntityManagerInterface $em, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $idea = $request->getSession()->get('ai_idea', ['seconds' => 5, 'ratio' => '9:16']);
        $form = $this->createForm(AiIdeaType::class, $idea)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $request->getSession()->set('ai_idea', $data);
            if (!$limiter->create('ai-idea-'.$user->getId())->consume()->isAccepted()) {
                $form->addError(new FormError('Trop de préparations rapprochées. Réessaie plus tard.'));
            } elseif ('connect' === $request->request->getString('intent')) {
                return $this->redirectToRoute('app_ai_keys', ['next' => 'idea'], 303);
            } else {
                $title = trim($data['title'] ?? '');
                if ('' === $title) {
                    $title = mb_substr(preg_replace('/\s+/u', ' ', trim($data['prompt'])), 0, 80);
                }
                $job = new AiGeneration($user, $title, trim($data['prompt']), $data['model'], $data['seconds'], $data['ratio'], $catalog->estimate($data['model'], $data['seconds']));
                $em->persist($job);
                $em->flush();

                return $this->redirectToRoute('app_ai_generation', ['id' => $job->getId()], 303);
            }
        }
        $jobs = $em->getRepository(AiGeneration::class)->findBy(['owner' => $user], ['createdAt' => 'DESC', 'id' => 'DESC'], 12);

        return $this->render('ai/studio.html.twig', ['ideaForm' => $form, 'models' => AiCatalog::MODELS, 'jobs' => $jobs, 'hasKey' => null !== $keys->connection($user)], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/ia/generations/{id}', name: 'app_ai_generation', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function generation(int $id, Request $request, #[CurrentUser] User $user, AiGenerations $generations, AiCatalog $catalog, AiKeys $keys): Response
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

        return $this->render('ai/generation.html.twig', ['job' => $job, 'model' => $catalog->model($job->getModel()), 'enabled' => $generations->enabled(), 'hasKey' => null !== $keys->connection($user)], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/connexions/ia', name: 'app_ai_keys', methods: ['GET', 'POST'])]
    public function keys(Request $request, #[CurrentUser] User $user, AiKeys $keys, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
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
                if (!$limiter->create('ai-key-'.$user->getId())->consume()->isAccepted()) {
                    throw new \InvalidArgumentException('Trop de modifications rapprochées. Réessaie plus tard.');
                }
                if ('remove' === $request->request->getString('intent')) {
                    if (!$request->request->getBoolean('confirm')) {
                        throw new \InvalidArgumentException('Confirme le retrait de la clé.');
                    }
                    $keys->remove($user);
                    $this->addFlash('success', 'La clé Fal a été retirée de VydFlow.');
                } else {
                    $keys->save($user, $key);
                    $this->addFlash('success', 'Clé Fal enregistrée. Son accès sera vérifié lors du premier essai autorisé.');
                }

                return $this->redirectToRoute('idea' === $back ? 'app_studio' : ($back ? 'app_ai_generation' : 'app_ai_keys'), $back && 'idea' !== $back ? ['id' => (int) $back] : [], 303);
            } catch (\InvalidArgumentException $error) {
                $this->addFlash('error', $error->getMessage());
            } finally {
                unset($key);
            }

            return $this->redirectToRoute('app_ai_keys', ['next' => $back], 303);
        }

        return $this->render('ai/keys.html.twig', ['connection' => $keys->connection($user), 'ready' => $keys->ready(), 'back' => $back], new Response(headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']));
    }
}
