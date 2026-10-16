<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\YouTubePublication;
use App\Form\YouTubePublishType;
use App\Repository\VideoAssetRepository;
use App\Service\PublicationException;
use App\Service\PublicationLock;
use App\Service\PublicationOverview;
use App\Service\YouTubeApi;
use App\Service\YouTubePublishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/studio/publications')]
final class PublicationController extends AbstractController
{
    #[Route('', name: 'app_publications', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, PublicationOverview $publications): Response
    {
        $network = $request->query->getString('network');
        $state = $request->query->getString('state');
        $network = in_array($network, ['youtube', 'instagram'], true) ? $network : '';
        $state = isset(PublicationOverview::STATES[$state]) ? $state : '';

        return $this->page('publication/index.html.twig', ['feed' => $publications->page($user, $request->query->getInt('page', 1), $network, $state), 'network' => $network, 'state' => $state]);
    }

    #[Route('/preparer', name: 'app_publication_prepare', methods: ['GET', 'POST'])]
    public function prepare(Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos, YouTubePublishing $publishing, YouTubeApi $api, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        if ($request->isMethod('GET')) {
            $values = $request->query->all('videos');
            $ids = [];
            foreach ($values as $id) {
                if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) {
                    throw $this->createNotFoundException();
                } $ids[] = (int) $id;
            }
            $ids = array_values(array_unique($ids));
            if (!$ids || count($ids) > 20) {
                $this->addFlash('error', 'Sélectionne entre 1 et 20 vidéos dans ta bibliothèque.');

                return $this->redirectToRoute('app_library', status: 303);
            }
            $draft = ['ids' => $ids, 'key' => bin2hex(random_bytes(32)), 'expires' => time() + 3600, 'channel' => $publishing->connection($user)?->getChannelId()];
            $request->getSession()->set('youtube_draft', $draft);
        } else {
            $draft = $request->getSession()->get('youtube_draft');
        }
        if (!is_array($draft) || $draft['expires'] < time()) {
            $this->addFlash('error', 'La préparation a expiré. Sélectionne à nouveau tes vidéos.');

            return $this->redirectToRoute('app_library', status: 303);
        }
        $selected = [];
        $data = ['videos' => []];
        foreach ($draft['ids'] as $id) {
            $video = $videos->owned($id, $user) ?? throw $this->createNotFoundException();
            $selected[] = $video;
            $data['videos'][$id] = ['title' => mb_substr(str_replace(['<', '>'], '', $video->getTitle()), 0, 100), 'description' => ''];
        }
        $connection = $publishing->connection($user);
        $ready = $api->isReady() && null !== $connection;
        $form = $this->createForm(YouTubePublishType::class, $data, ['videos' => $selected, 'draft_key' => $draft['key']])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$ready) {
                $form->addError(new FormError('Connecte ta chaîne YouTube avant de publier.'));
            } elseif ($draft['channel'] !== $connection->getChannelId()) {
                $form->addError(new FormError('La chaîne a changé. Recharge la préparation avant de confirmer.'));
            } elseif (!$limiter->create('publish-'.$user->getId())->consume()->isAccepted()) {
                $form->addError(new FormError('Trop de publications rapprochées. Réessaie dans 15 minutes.'));
            } else {
                try {
                    $publishing->enqueue($user, $draft['ids'], $form->getData()['videos'], $draft['key'], $draft['channel']);
                    $request->getSession()->remove('youtube_draft');
                    $this->addFlash('success', 'Tes vidéos sont dans la file d’envoi YouTube.');

                    return $this->redirectToRoute('app_publications', status: 303);
                } catch (PublicationException $error) {
                    $form->addError(new FormError($error->getMessage()));
                }
            }
        }

        return $this->page('publication/prepare.html.twig', ['form' => $form, 'videos' => $selected, 'connection' => $connection, 'ready' => $ready], $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/{id}/reprendre', name: 'app_publication_resume', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resume(int $id, Request $request, #[CurrentUser] User $user, EntityManagerInterface $em, YouTubePublishing $publishing, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $job = $em->getRepository(YouTubePublication::class)->findOneBy(['id' => $id, 'owner' => $user]) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('resume_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            if (!$limiter->create('resume-'.$user->getId())->consume()->isAccepted()) {
                throw new PublicationException('Trop de tentatives rapprochées. Réessaie dans 15 minutes.');
            } $publishing->resume($job);
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_publications', status: 303);
    }

    #[Route('/{id}/arreter', name: 'app_publication_stop', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function stop(int $id, Request $request, #[CurrentUser] User $user, EntityManagerInterface $em, PublicationLock $lock): Response
    {
        $job = $em->getRepository(YouTubePublication::class)->findOneBy(['id' => $id, 'owner' => $user]) ?? throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('stop_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            try {
                $lock->run((int) $user->getId(), function () use ($em, $job): void {
                    $em->refresh($job);
                    if (in_array($job->getStatus(), YouTubePublication::ACTIVE, true)) {
                        $job->change('cancelled', 'Envoi arrêté dans VydFlow. Vérifie YouTube Studio avant tout nouvel envoi.');
                        $em->flush();
                    }
                });
            } catch (PublicationException $error) {
                $this->addFlash('error', $error->getMessage());
            }

            return $this->redirectToRoute('app_publications', status: 303);
        }

        return $this->page('publication/stop.html.twig', ['job' => $job]);
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, array $data, int $status = 200): Response
    {
        return $this->render($template, $data, new Response(status: $status, headers: ['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'same-origin']));
    }
}
