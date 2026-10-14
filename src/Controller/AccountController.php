<?php

namespace App\Controller;

use App\Entity\AccountToken;
use App\Form\AccountEmailType;
use App\Form\AccountTokenType;
use App\Form\RegistrationType;
use App\Service\AccountAccess;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AccountController extends AbstractController
{
    public function __construct(
        private AccountAccess $accounts,
        private Security $security,
        #[Autowire(service: 'limiter.account')] private RateLimiterFactory $limiter,
        #[Autowire(service: 'limiter.account_email')] private RateLimiterFactory $emailLimiter,
    ) {
    }

    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authentication): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authentication->getLastAuthenticationError();
        $response = $this->render('account/login.html.twig', ['login_error' => null !== $error, 'confirmation_required' => $error instanceof CustomUserMessageAccountStatusException, 'last_email' => $authentication->getLastUsername()]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Handled by Symfony Security.');
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }
        $form = $this->createForm(RegistrationType::class)->handleRequest($request);
        if ($form->isSubmitted() && !$this->allowRequest($request)) {
            return $this->formPage($form, 'register', 429);
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                if ($this->allowEmail($data['email'])) {
                    $this->accounts->register($data['email'], $data['password']);
                }
            } catch (UniqueConstraintViolationException) {
                // Concurrent duplicate has the same neutral response.
            } catch (TransportExceptionInterface) {
                $form->addError(new FormError('L’envoi est indisponible. Réessaie via « Renvoyer un lien » dans quelques minutes.'));

                return $this->formPage($form, 'register', 503);
            }
            $this->addFlash('success', 'Si cette adresse permet de créer un compte, un email de confirmation a été envoyé. Confirme ton adresse avec ce lien avant de te connecter.');

            return $this->redirectToRoute('app_login', status: 303);
        }

        return $this->formPage($form, 'register', $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    #[Route('/compte/renvoyer-confirmation', name: 'app_resend_verification', methods: ['GET', 'POST'])]
    public function requestLink(Request $request): Response
    {
        $reset = 'app_forgot_password' === $request->attributes->get('_route');
        $mode = $reset ? 'forgot' : 'resend';
        $form = $this->createForm(AccountEmailType::class)->handleRequest($request);
        if ($form->isSubmitted() && !$this->allowRequest($request)) {
            return $this->formPage($form, $mode, 429);
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->getData()['email'];
            try {
                if ($this->allowEmail($email)) {
                    $this->accounts->requestToken($email, $reset ? AccountToken::RESET : AccountToken::VERIFY);
                }
            } catch (TransportExceptionInterface) {
                $form->addError(new FormError('L’envoi est indisponible. Réessaie dans quelques minutes.'));

                return $this->formPage($form, $mode, 503);
            }
            $this->addFlash('success', 'Si un compte correspond à cette adresse et nécessite cette action, un email a été envoyé. Consulte aussi les indésirables.');

            return $this->redirectToRoute('app_login', status: 303);
        }

        return $this->formPage($form, $mode, $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/compte/verifier', name: 'app_verify_email', methods: ['GET', 'POST'])]
    #[Route('/compte/nouveau-mot-de-passe', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function redeemLink(Request $request): Response
    {
        $reset = 'app_reset_password' === $request->attributes->get('_route');
        $mode = $reset ? 'reset' : 'verify';
        $form = $this->createForm(AccountTokenType::class, options: ['reset_password' => $reset])->handleRequest($request);
        if ($form->isSubmitted() && !$this->allowRequest($request)) {
            return $this->formPage($form, $mode, 429);
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($this->accounts->consumeToken($data['token'], $reset ? AccountToken::RESET : AccountToken::VERIFY, $data['password'] ?? null)) {
                if ($reset) {
                    if ($this->getUser()) {
                        $this->security->logout(false);
                    }
                    $request->getSession()->invalidate();
                }
                $this->addFlash('success', $reset ? 'Ton mot de passe a été modifié. Connecte-toi avec le nouveau.' : 'Ton adresse email est confirmée. Tu peux accéder à ton espace.');

                return $this->redirectToRoute($reset || !$this->getUser() ? 'app_login' : 'app_dashboard', status: 303);
            }
            $form->addError(new FormError('Ce lien est invalide, expiré ou déjà utilisé. Demande un nouveau lien.'));
        }

        return $this->formPage($form, $mode, $form->isSubmitted() ? 422 : 200);
    }

    private function allowRequest(Request $request): bool
    {
        if ($this->limiter->create($request->getClientIp() ?? 'local')->consume()->isAccepted()) {
            return true;
        }
        $this->addFlash('error', 'Trop de tentatives. Réessaie dans 15 minutes.');

        return false;
    }

    private function allowEmail(string $email): bool
    {
        $key = hash_hmac('sha256', strtolower(trim($email)), $this->getParameter('kernel.secret'));

        return $this->emailLimiter->create($key)->consume()->isAccepted();
    }

    private function formPage(FormInterface $form, string $mode, int $status): Response
    {
        $response = $this->render('account/form.html.twig', ['form' => $form, 'mode' => $mode], new Response(status: $status));
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
