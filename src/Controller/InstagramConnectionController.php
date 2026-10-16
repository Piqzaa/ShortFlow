<?php

namespace App\Controller;

use App\Entity\InstagramConnection;
use App\Entity\User;
use App\Service\InstagramApi;
use App\Service\PublicationException;
use App\Service\PublicationLock;
use App\Service\TokenVault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/studio/connexions/instagram')]
final class InstagramConnectionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private InstagramApi $api, private PublicationLock $lock, #[Autowire(service: 'instagram.vault')] private TokenVault $vault)
    {
    }

    #[Route('', name: 'app_instagram_connect', methods: ['POST'])]
    public function connect(Request $request, #[CurrentUser] User $user, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        if (!$this->isCsrfTokenValid('instagram_connect', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$limiter->create('instagram-oauth-'.$user->getId())->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de tentatives rapprochées. Réessaie dans 15 minutes.');

            return $this->back();
        }
        try {
            $state = bin2hex(random_bytes(32));
            $url = $this->api->authorizationUrl($state);
            $request->getSession()->set('instagram_oauth', ['state' => $state, 'user' => $user->getId(), 'expires' => time() + 600]);
            $response = $this->redirect($url, 303);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');

            return $response;
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());

            return $this->back();
        }
    }

    #[Route('/retour', name: 'app_instagram_callback', methods: ['GET'])]
    public function callback(Request $request, #[CurrentUser] User $user): Response
    {
        $flow = $request->getSession()->remove('instagram_oauth');
        if (!is_array($flow) || ($flow['user'] ?? null) !== $user->getId() || !is_int($flow['expires'] ?? null) || $flow['expires'] < time() || !is_string($flow['state'] ?? null) || !hash_equals($flow['state'], $request->query->getString('state'))) {
            $this->addFlash('error', 'La connexion a expiré. Recommence depuis VydFlow.');
        } elseif ($request->query->has('error')) {
            $this->addFlash('error', 'La connexion Instagram a été annulée.');
        } else {
            try {
                $this->lock->run((int) $user->getId(), function () use ($request, $user): void {
                    $data = $this->api->connect($request->query->getString('code'));
                    $encrypted = $this->vault->encrypt($data['access']);
                    $this->em->wrapInTransaction(function () use ($user, $data, $encrypted): void {
                        $connection = $this->em->getRepository(InstagramConnection::class)->findOneBy(['owner' => $user]);
                        if ($connection) {
                            $connection->reconnect($data['id'], $data['username'], $data['type'], $encrypted, $data['expires']);
                        } else {
                            $this->em->persist(new InstagramConnection($user, $data['id'], $data['username'], $data['type'], $encrypted, $data['expires']));
                        }
                    });
                });
                $this->addFlash('success', 'Ton compte Instagram est connecté.');
            } catch (\Throwable) {
                $this->addFlash('error', 'La connexion Instagram n’a pas abouti. Vérifie le compte professionnel et les autorisations puis réessaie.');
            }
        }

        return $this->back();
    }

    #[Route('/deconnecter', name: 'app_instagram_disconnect', methods: ['POST'])]
    public function disconnect(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('instagram_disconnect', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $this->lock->run((int) $user->getId(), function () use ($user): void {
                $connection = $this->em->getRepository(InstagramConnection::class)->findOneBy(['owner' => $user]);
                if ($connection) {
                    $this->em->remove($connection);
                    $this->em->flush();
                }
            });
            $request->getSession()->remove('instagram_oauth');
            $this->addFlash('success', 'Compte Instagram déconnecté de VydFlow.');
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->back();
    }

    private function back(): Response
    {
        $response = $this->redirectToRoute('app_connections', status: 303);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
