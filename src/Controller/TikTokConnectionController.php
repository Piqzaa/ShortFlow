<?php

namespace App\Controller;

use App\Entity\TikTokConnection;
use App\Entity\User;
use App\Service\PublicationException;
use App\Service\PublicationLock;
use App\Service\TikTokApi;
use App\Service\TokenVault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/studio/connexions/tiktok')]
final class TikTokConnectionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private TikTokApi $api, private PublicationLock $lock, #[Autowire(service: 'tiktok.vault')] private TokenVault $vault, private \App\Service\SocialAccounts $accounts)
    {
    }

    #[Route('', name: 'app_tiktok_connect', methods: ['POST'])]
    public function connect(Request $request, #[CurrentUser] User $user, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        if (!$this->isCsrfTokenValid('tiktok_connect', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$limiter->create('tiktok-oauth-'.$user->getId())->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de tentatives. Réessaie dans 15 minutes.');

            return $this->back();
        }
        try {
            $target = $request->request->getString('account') ?: null;
            if ($target && !$this->accounts->find($user, 'tiktok', $target)) {
                throw new PublicationException('Ce compte est indisponible.');
            }
            $state = bin2hex(random_bytes(32));
            $url = $this->api->authorizationUrl($state);
            $request->getSession()->set('tiktok_oauth', ['state' => $state, 'user' => $user->getId(), 'expires' => time() + 600, 'target' => $target]);
            $response = $this->redirect($url, 303);
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Referrer-Policy', 'no-referrer');

            return $response;
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->back();
    }

    #[Route('/retour', name: 'app_tiktok_callback', methods: ['GET'])]
    public function callback(Request $request, #[CurrentUser] User $user): Response
    {
        $flow = $request->getSession()->remove('tiktok_oauth');
        if (!is_array($flow) || ($flow['user'] ?? null) !== $user->getId() || !is_int($flow['expires'] ?? null) || $flow['expires'] < time() || !is_string($flow['state'] ?? null) || !hash_equals($flow['state'], $request->query->getString('state'))) {
            $this->addFlash('error', 'La connexion a expiré. Recommence depuis VydFlow.');
        } elseif ($request->query->has('error')) {
            $this->addFlash('error', 'La connexion TikTok a été annulée.');
        } else {
            try {
                $this->lock->run((int) $user->getId(), function () use ($request, $user, $flow): void {
                    $data = $this->api->connect($request->query->getString('code'));
                    $target = $flow['target'] ?? null;
                    if ($target && $target !== $data['openId']) {
                        throw new PublicationException('Le fournisseur a renvoyé un autre compte. Reconnecte le compte choisi ou utilise « Ajouter un compte ».');
                    }
                    $access = $this->vault->encrypt($data['access']);
                    $refresh = $this->vault->encrypt($data['refresh']);
                    $connection = $this->em->getRepository(TikTokConnection::class)->findOneBy(['owner' => $user, 'openId' => $data['openId']]);
                    if ($target && !$connection) {
                        throw new PublicationException('Ce compte a été retiré. Recommence son ajout.');
                    }
                    $this->em->wrapInTransaction(function () use ($user, $data, $access, $refresh, $connection): void {
                        if ($connection) {
                            $connection->reconnect($data['openId'], $data['name'], $access, $refresh, $data['expires'], $data['refreshExpires']);
                        } else {
                            $this->em->persist(new TikTokConnection($user, $data['openId'], $data['name'], $access, $refresh, $data['expires'], $data['refreshExpires']));
                        }
                    });
                });
                $this->addFlash('success', 'Ton compte TikTok est connecté.');
            } catch (PublicationException $error) {
                $this->addFlash('error', $error->getMessage());
            } catch (\Throwable) {
                $this->addFlash('error', 'La connexion TikTok n’a pas abouti. Vérifie les autorisations puis réessaie.');
            }
        }

        return $this->back();
    }

    #[Route('/profil', name: 'app_tiktok_profile', methods: ['GET'])]
    public function profile(Request $request, #[CurrentUser] User $user, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $connection = $this->accounts->find($user, 'tiktok', $request->query->getString('account') ?: null);
        if (!$connection instanceof TikTokConnection) {
            $this->addFlash('error', 'Connecte ton compte TikTok pour ouvrir son profil.');

            return $this->back();
        }
        if (!$limiter->create('tiktok-profile-'.$user->getId())->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de consultations rapprochées. Réessaie plus tard.');

            return $this->back();
        }
        try {
            $url = $this->lock->run((int) $user->getId(), fn (): string => $this->api->profileUrl($connection));

            return new \Symfony\Component\HttpFoundation\RedirectResponse($url, 303, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());

            return $this->back();
        }
    }

    #[Route('/deconnecter', name: 'app_tiktok_disconnect', methods: ['POST'])]
    public function disconnect(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('tiktok_disconnect', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $revoked = true;
            $this->lock->run((int) $user->getId(), function () use ($user, $request, &$revoked): void {
                $connection = $this->accounts->find($user, 'tiktok', $request->request->getString('account') ?: null);
                if (!$connection instanceof TikTokConnection) {
                    throw new PublicationException('Choisis le compte à déconnecter.');
                }
                try {
                    $this->api->revoke($connection);
                } catch (\Throwable) {
                    $revoked = false;
                }
                $this->em->remove($connection);
                $this->em->flush();
            });
            $request->getSession()->remove('tiktok_oauth');
            $this->addFlash('success', $revoked ? 'Compte TikTok déconnecté et autorisation révoquée.' : 'Compte retiré de VydFlow. Retire aussi l’autorisation dans les réglages TikTok.');
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->back();
    }

    private function back(): Response
    {
        return new \Symfony\Component\HttpFoundation\RedirectResponse($this->generateUrl('app_connections'), 303, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
