<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Repository\VideoAssetRepository;
use App\Service\AccountAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ProfileController extends AbstractController
{
    #[Route('/studio/mon-compte', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos, AccountAccess $accounts, Security $security, #[Autowire(service: 'limiter.account')] RateLimiterFactory $limiter): Response
    {
        $form = $this->createForm(ChangePasswordType::class)->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && !$limiter->create('password-'.$user->getId().'-'.$request->getClientIp())->consume()->isAccepted()) {
            $form->addError(new FormError('Trop de tentatives. Réessaie dans 15 minutes.'));
            $status = 429;
        } elseif ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($accounts->changePassword($user, $data['currentPassword'], $data['newPassword'])) {
                $security->logout(false);
                $request->getSession()->invalidate();
                $this->addFlash('success', 'Ton mot de passe a été modifié. Reconnecte-toi avec le nouveau.');

                return $this->redirectToRoute('app_login', status: 303);
            }
            $form->get('currentPassword')->addError(new FormError('Le mot de passe actuel est incorrect.'));
        }

        return $this->render('studio/profile.html.twig', ['form' => $form, 'usage' => $videos->usage($user)], new Response(status: $status, headers: ['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']));
    }
}
