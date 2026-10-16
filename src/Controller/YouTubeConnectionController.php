<?php

namespace App\Controller;

use App\Entity\InstagramConnection;
use App\Entity\TikTokConnection;
use App\Entity\User;
use App\Entity\YouTubeConnection;
use App\Service\InstagramApi;
use App\Service\PublicationException;
use App\Service\PublicationLock;
use App\Service\TikTokApi;
use App\Service\TokenVault;
use App\Service\YouTubeApi;
use App\Service\YouTubePublishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/studio/connexions')]
final class YouTubeConnectionController extends AbstractController
{
    #[Route('', name: 'app_connections', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, YouTubePublishing $publishing, YouTubeApi $api, InstagramApi $instagram, TikTokApi $tiktok, EntityManagerInterface $em): Response
    {
        return $this->render('publication/connections.html.twig', ['connection' => $publishing->connection($user), 'ready' => $api->isReady(), 'instagramConnection' => $em->getRepository(InstagramConnection::class)->findOneBy(['owner' => $user]), 'instagramReady' => $instagram->isReady(), 'tiktokConnection' => $em->getRepository(TikTokConnection::class)->findOneBy(['owner' => $user]), 'tiktokReady' => $tiktok->isReady()], new Response(headers: ['Cache-Control' => 'no-store, private']));
    }

    #[Route('/youtube', name: 'app_youtube_connect', methods: ['POST'])]
    public function connect(Request $request, #[CurrentUser] User $user, YouTubeApi $api, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        if (!$this->isCsrfTokenValid('youtube_connect', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$limiter->create('oauth-'.$user->getId())->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de tentatives rapprochées. Réessaie dans 15 minutes.');

            return $this->redirectToRoute('app_connections', status: 303);
        }
        try {
            $state = bin2hex(random_bytes(32));
            $verifier = bin2hex(random_bytes(32));
            $url = $api->authorizationUrl($state, $verifier);
            $request->getSession()->set('youtube_oauth', ['state' => $state, 'verifier' => $verifier, 'user' => $user->getId(), 'expires' => time() + 600]);

            return $this->redirect($url, 303)->setPrivate()->setMaxAge(0);
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());

            return $this->redirectToRoute('app_connections', status: 303);
        }
    }

    #[Route('/youtube/retour', name: 'app_youtube_callback', methods: ['GET'])]
    public function callback(Request $request, #[CurrentUser] User $user, YouTubeApi $api, YouTubePublishing $publishing, TokenVault $vault, PublicationLock $lock, EntityManagerInterface $em): Response
    {
        $flow = $request->getSession()->remove('youtube_oauth');
        if (!is_array($flow) || $flow['user'] !== $user->getId() || $flow['expires'] < time() || !hash_equals($flow['state'], $request->query->getString('state'))) {
            $this->addFlash('error', 'La connexion a expiré. Recommence depuis VydFlow.');
        } elseif ($request->query->has('error')) {
            $this->addFlash('error', 'La connexion YouTube a été annulée.');
        } else {
            try {
                $lock->run((int) $user->getId(), function () use ($api, $request, $flow, $publishing, $user, $vault, $em): void {
                    $tokens = $api->exchange($request->query->getString('code'), $flow['verifier']);
                    $channel = $api->channel($tokens['access']);
                    $em->wrapInTransaction(function () use ($tokens, $channel, $publishing, $user, $vault, $em): void {
                        $old = $publishing->connection($user);
                        if ($old) {
                            $em->remove($old);
                            $em->flush();
                        }
                        $em->persist(new YouTubeConnection($user, $channel['id'], $channel['title'], $vault->encrypt($tokens['access']), $vault->encrypt($tokens['refresh']), $tokens['expires']));
                    });
                });
                $this->addFlash('success', 'Ta chaîne YouTube est connectée.');
            } catch (\Throwable) {
                $this->addFlash('error', 'La connexion YouTube n’a pas abouti. Vérifie les autorisations puis réessaie.');
            }
        }
        $response = $this->redirectToRoute('app_connections', status: 303);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    #[Route('/youtube/deconnecter', name: 'app_youtube_disconnect', methods: ['POST'])]
    public function disconnect(Request $request, #[CurrentUser] User $user, YouTubePublishing $publishing, PublicationLock $lock, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('youtube_disconnect', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $lock->run((int) $user->getId(), function () use ($publishing, $user, $em): void {
                $connection = $publishing->connection($user);
                if ($connection) {
                    $em->remove($connection);
                    $em->flush();
                }
            });
            $this->addFlash('success', 'Chaîne déconnectée de VydFlow. Les prochains envois sont suspendus.');
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        $request->getSession()->remove('youtube_oauth');

        return $this->redirectToRoute('app_connections', status: 303);
    }
}
