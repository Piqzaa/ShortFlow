<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ProfilePreferencesType;
use App\Form\ProfileType;
use App\Repository\VideoAssetRepository;
use App\Service\AccountAccess;
use App\Service\ProfileAvatarStorage;
use App\Service\ProfileSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ProfileController extends AbstractController
{
    #[Route('/studio/mon-compte', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos, AccountAccess $accounts, Security $security, ProfileSettings $settings, #[Autowire(service: 'limiter.account')] RateLimiterFactory $limiter): Response
    {
        $section = $request->query->getString('section', 'profile');
        if (!in_array($section, ['profile', 'preferences', 'security', 'subscription'], true)) {
            throw $this->createNotFoundException();
        }
        $profile = $this->createForm(ProfileType::class, ['displayName' => $user->getDisplayName()])->handleRequest($request);
        $preferences = $this->createForm(ProfilePreferencesType::class, ['timezone' => $user->getTimezone()])->handleRequest($request);
        foreach (['profile' => $profile, 'preferences' => $preferences] as $name => $settingForm) {
            if (!$settingForm->isSubmitted()) {
                continue;
            }
            $section = $name;
            if (!$limiter->create('profile-'.$user->getId())->consume()->isAccepted()) {
                $settingForm->addError(new FormError('Trop de modifications rapprochées. Réessaie dans quelques minutes.'));
            } elseif ($settingForm->isValid()) {
                try {
                    $data = $settingForm->getData();
                    if ('profile' === $name) {
                        $settings->updateProfile($user, $data['displayName'] ?? '', $data['avatar'] ?? null, (bool) ($data['removeAvatar'] ?? false));
                    } else {
                        $settings->updateTimezone($user, $data['timezone']);
                    }
                    $this->addFlash('success', 'Tes informations ont été enregistrées.');

                    return $this->redirectToRoute('app_profile', ['section' => $section], 303);
                } catch (\RuntimeException $error) {
                    $settingForm->addError(new FormError($error->getMessage()));
                }
            }
        }
        $form = $this->createForm(ChangePasswordType::class)->handleRequest($request);
        $status = $form->isSubmitted() || $profile->isSubmitted() || $preferences->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted()) {
            $section = 'security';
        }
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

        return $this->render('studio/account.html.twig', ['form' => $form, 'profileForm' => $profile, 'preferencesForm' => $preferences, 'section' => $section, 'usage' => $videos->usage($user)], new Response(status: $status, headers: ['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']));
    }

    #[Route('/studio/mon-compte/photo/{name}', name: 'app_avatar', requirements: ['name' => '[a-f0-9]{48}\.jpg'], methods: ['GET', 'HEAD'])]
    public function avatar(string $name, #[CurrentUser] User $user, ProfileAvatarStorage $storage): BinaryFileResponse
    {
        if ($name !== $user->getAvatarName()) {
            throw $this->createNotFoundException();
        }
        $path = $storage->path($name);
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path, headers: ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Cross-Origin-Resource-Policy' => 'same-origin']);
    }
}
