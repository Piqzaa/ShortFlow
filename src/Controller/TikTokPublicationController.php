<?php

namespace App\Controller;

use App\Entity\TikTokPublication;
use App\Entity\User;
use App\Form\TikTokPublishType;
use App\Repository\VideoAssetRepository;
use App\Service\PublicationException;
use App\Service\PublicationLock;
use App\Service\TikTokApi;
use App\Service\TikTokMediaLink;
use App\Service\TikTokPublishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/studio/publications/tiktok')]
final class TikTokPublicationController extends AbstractController
{
    #[Route('/preparer', name: 'app_tiktok_prepare', methods: ['GET', 'POST'])]
    public function prepare(Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos, TikTokPublishing $publishing, TikTokMediaLink $links, TikTokApi $api, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter, \App\Service\SocialAccounts $social): Response
    {
        if ($request->isMethod('GET') && count($social->all($user, 'tiktok')) > 1) {
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
            if (!$ids || count($ids) > 5) {
                $this->addFlash('error', 'Sélectionne entre 1 et 5 vidéos.');

                return $this->redirectToRoute('app_library', status: 303);
            }
            $draft = ['ids' => $ids, 'key' => bin2hex(random_bytes(32)), 'expires' => time() + 3600, 'account' => $publishing->connection($user)?->getOpenId()];
            $request->getSession()->set('tiktok_draft', $draft);
        } else {
            $draft = $request->getSession()->get('tiktok_draft');
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
            $data['videos'][$id] = ['caption' => $video->getTitle(), 'privacy' => null, 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => false];
            try {
                $publishing->validateVideo($video);
            } catch (PublicationException $error) {
                $issues[$id] = $error->getMessage();
            }
        }
        $ready = $publishing->ready($user, $draft['account']) && !$issues;
        $creator = [];
        if ($ready) {
            try {
                $connection = $publishing->connection($user, $draft['account']);
                if ($connection) {
                    $creator = $api->creator($connection);
                }
                foreach ($selected as $video) {
                    $publishing->validateVideo($video, $creator['max_video_post_duration_sec']);
                }
            } catch (PublicationException $error) {
                $ready = false;
                $this->addFlash('error', $error->getMessage());
            }
        }
        $form = $this->createForm(TikTokPublishType::class, $data, ['videos' => $selected, 'draft_key' => $draft['key'], 'creator' => $creator])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$ready) {
                $form->addError(new FormError('Vérifie la connexion TikTok, la disponibilité de l’envoi et le format des vidéos.'));
            } elseif ($draft['account'] !== $publishing->connection($user, $draft['account'])?->getOpenId()) {
                $form->addError(new FormError('Le compte a changé. Recharge la préparation.'));
            } elseif (!$limiter->create('publish-'.$user->getId())->consume()->isAccepted()) {
                $form->addError(new FormError('Trop de publications rapprochées. Réessaie dans 15 minutes.'));
            } else {
                try {
                    $publishing->enqueue($user, $draft['ids'], $form->getData()['videos'], $draft['key'], $draft['account']);
                    $request->getSession()->remove('tiktok_draft');
                    $this->addFlash('success', 'Tes vidéos sont dans la file d’envoi TikTok.');

                    return $this->redirectToRoute('app_publications', status: 303);
                } catch (PublicationException $error) {
                    $form->addError(new FormError($error->getMessage()));
                }
            }
        }

        return $this->render('publication/tiktok_prepare.html.twig', ['form' => $form, 'videos' => $selected, 'ids' => $draft['ids'], 'connection' => $publishing->connection($user, $draft['account']), 'creator' => $creator, 'ready' => $ready, 'mediaReady' => $links->isReady(), 'issues' => $issues], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'same-origin']));
    }

    #[Route('/{id}/{action}', name: 'app_tiktok_job_action', requirements: ['id' => '\d+', 'action' => 'reprendre|arreter'], methods: ['POST'])]
    public function action(int $id, string $action, Request $request, #[CurrentUser] User $user, EntityManagerInterface $em, TikTokPublishing $publishing, PublicationLock $lock, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $job = $em->getRepository(TikTokPublication::class)->findOneBy(['id' => $id, 'owner' => $user]) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('tiktok_'.$action.'_'.$id, $request->request->getString('_token'))) {
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
                    if (in_array($job->getStatus(), TikTokPublication::ACTIVE, true)) {
                        $job->change('cancelled', 'Suivi arrêté. Cela ne supprime aucune vidéo déjà publiée : vérifie ton profil avant un nouvel envoi.');
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
