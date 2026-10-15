<?php

namespace App\Controller;

use App\Form\WaitlistType;
use App\Service\WaitlistRegistration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class LandingController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_VERIFIED')) {
            $response = $this->redirectToRoute('app_dashboard');
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        }

        return $this->render('landing/index.html.twig');
    }

    #[Route('/liste-attente', name: 'app_waitlist', methods: ['GET', 'POST'])]
    public function waitlist(Request $request, WaitlistRegistration $registration, #[Autowire(service: 'limiter.waitlist')] RateLimiterFactory $limiter): Response
    {
        $form = $this->createForm(WaitlistType::class);
        $form->handleRequest($request);
        $status = Response::HTTP_OK;
        if ($form->isSubmitted()) {
            if (!$limiter->create($request->getClientIp() ?? 'local')->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de tentatives. Réessaie dans 15 minutes.');
                $status = Response::HTTP_TOO_MANY_REQUESTS;
            } elseif ($form->isValid()) {
                $data = $form->getData();
                if (empty($data['website'])) {
                    $registration->register($data['email']);
                }
                $this->addFlash('success', 'Merci ! Ta demande a bien été prise en compte.');

                return $this->redirectToRoute('app_waitlist', ['_fragment' => 'rejoindre'], Response::HTTP_SEE_OTHER);
            } else {
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
        }

        return $this->render('landing/waitlist.html.twig', ['waitlist' => $form], new Response(status: $status));
    }
}
