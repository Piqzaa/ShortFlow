<?php

namespace App\Controller;

use App\Entity\InstagramPublication;
use App\Entity\User;
use App\Form\InstagramPublishType;
use App\Repository\VideoAssetRepository;
use App\Service\InstagramMediaLink;
use App\Service\InstagramPublishing;
use App\Service\PublicationException;
use App\Service\PublicationLock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/studio/publications/instagram')]
final class InstagramPublicationController extends AbstractController
{
    #[Route('/{id}/voir', name: 'app_instagram_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(int $id, #[CurrentUser] User $user, EntityManagerInterface $em, \App\Service\InstagramApi $api, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $job = $em->getRepository(InstagramPublication::class)->findOneBy(['id' => $id, 'owner' => $user]) ?? throw $this->createNotFoundException();
        try {
            $connection = $em->getRepository(\App\Entity\InstagramConnection::class)->findOneBy(['owner' => $user, 'instagramId' => $job->getInstagramId()]);
            if ('published' !== $job->getStatus() || !$job->getRemoteId() || !$connection || $connection->getInstagramId() !== $job->getInstagramId() || !$limiter->create('instagram-link-'.$user->getId())->consume()->isAccepted()) {
                throw new PublicationException('Le lien Instagram est indisponible. Vérifie le compte connecté.');
            }

            $response = $this->redirect($api->permalink($connection, $job->getRemoteId()), 303);
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Referrer-Policy', 'no-referrer');

            return $response;
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());

            return $this->redirectToRoute('app_publications', status: 303);
        }
    }

    #[Route('/preparer', name: 'app_instagram_prepare', methods: ['GET', 'POST'])]
    public function prepare(Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos, InstagramPublishing $publishing, InstagramMediaLink $links, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter, \App\Service\SocialAccounts $social): Response
    {
        if ($request->isMethod('GET') && count($social->all($user, 'instagram')) > 1) {
            return $this->redirectToRoute('app_composer_start', ['videos' => $request->query->all('videos')], 303);
        }
        if ($request->isMethod('GET')) {
            $ids = [];
            foreach ($request->query->all('videos') as $id) {
                if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) {
                    throw $this->createNotFoundException();
                } $ids[] = (int) $id;
            }
            $ids = array_values(array_unique($ids));
            if (!$ids || count($ids) > 20) {
                $this->addFlash('error', 'Sélectionne entre 1 et 20 vidéos.');

                return $this->redirectToRoute('app_library', status: 303);
            }
            $draft = ['ids' => $ids, 'key' => bin2hex(random_bytes(32)), 'expires' => time() + 3600, 'account' => $publishing->connection($user)?->getInstagramId()];
            $request->getSession()->set('instagram_draft', $draft);
        } else {
            $draft = $request->getSession()->get('instagram_draft');
        }
        if (!is_array($draft) || $draft['expires'] < time()) {
            $this->addFlash('error', 'La préparation a expiré. Sélectionne à nouveau tes vidéos.');

            return $this->redirectToRoute('app_library', status: 303);
        }
        $selected = [];
        $data = ['videos' => []];
        $issues = [];
        foreach ($draft['ids'] as $id) {
            $video = $videos->owned($id, $user) ?? throw $this->createNotFoundException();
            $selected[] = $video;
            $data['videos'][$id] = ['caption' => $video->getTitle(), 'shareToFeed' => true, 'synthetic' => false];
            try {
                $publishing->validateVideo($video);
            } catch (PublicationException $error) {
                $issues[$id] = $error->getMessage();
            }
        }
        $ready = $publishing->ready($user, $draft['account']) && !$issues;
        $form = $this->createForm(InstagramPublishType::class, $data, ['videos' => $selected, 'draft_key' => $draft['key']])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$ready) {
                $form->addError(new FormError('Vérifie la connexion Instagram, la disponibilité de l’envoi et le format des vidéos.'));
            } elseif ($draft['account'] !== $publishing->connection($user, $draft['account'])?->getInstagramId()) {
                $form->addError(new FormError('Le compte a changé. Recharge la préparation.'));
            } elseif (!$limiter->create('publish-'.$user->getId())->consume()->isAccepted()) {
                $form->addError(new FormError('Trop de publications rapprochées. Réessaie dans 15 minutes.'));
            } else {
                try {
                    $publishing->enqueue($user, $draft['ids'], $form->getData()['videos'], $draft['key'], $draft['account']);
                    $request->getSession()->remove('instagram_draft');
                    $this->addFlash('success', 'Tes Reels sont dans la file d’envoi Instagram.');

                    return $this->redirectToRoute('app_publications', status: 303);
                } catch (PublicationException $error) {
                    $form->addError(new FormError($error->getMessage()));
                }
            }
        }

        return $this->render('publication/instagram_prepare.html.twig', ['form' => $form, 'videos' => $selected, 'ids' => $draft['ids'], 'connection' => $publishing->connection($user, $draft['account']), 'ready' => $ready, 'mediaReady' => $links->isReady(), 'issues' => $issues], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'same-origin']));
    }

    #[Route('/{id}/{action}', name: 'app_instagram_job_action', requirements: ['id' => '\d+', 'action' => 'reprendre|arreter'], methods: ['POST'])]
    public function action(int $id, string $action, Request $request, #[CurrentUser] User $user, EntityManagerInterface $em, InstagramPublishing $publishing, PublicationLock $lock, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $job = $em->getRepository(InstagramPublication::class)->findOneBy(['id' => $id, 'owner' => $user]) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('instagram_'.$action.'_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            if ('reprendre' === $action) {
                if (!$limiter->create('resume-'.$user->getId())->consume()->isAccepted()) {
                    throw new PublicationException('Trop de tentatives. Réessaie dans 15 minutes.');
                }
                $publishing->resume($job);
            } else {
                $lock->run((int) $user->getId(), function () use ($em, $job): void {
                    $em->refresh($job);
                    if (in_array($job->getStatus(), InstagramPublication::ACTIVE, true)) {
                        $job->change('cancelled', 'Suivi arrêté. Cela ne supprime aucun Reel déjà publié : vérifie ton profil avant un nouvel envoi.');
                        $em->flush();
                    }
                });
            }
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_publications', status: 303);
    }
}
