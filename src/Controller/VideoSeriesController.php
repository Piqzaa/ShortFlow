<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\VideoSeriesType;
use App\Form\VideoUploadType;
use App\Repository\VideoAssetRepository;
use App\Service\VideoSeriesStudio;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class VideoSeriesController extends AbstractController
{
    #[Route('/studio/series/creer', name: 'app_series_choose', methods: ['GET'])]
    public function choose(): Response
    {
        return $this->render('series/choose.html.twig', [], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series/videos/nouvelle', name: 'app_video_series_new', methods: ['GET', 'POST'])]
    #[Route('/studio/series/videos/{id}/modifier', name: 'app_video_series_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function configure(Request $request, #[CurrentUser] User $user, VideoSeriesStudio $studio, VideoAssetRepository $videos, \App\Service\SeriesPublicationSettings $publicationSettings, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter, ?int $id = null): Response
    {
        $series = null === $id ? null : ($studio->owned($id, $user) ?? throw $this->createNotFoundException());
        $episodes = $series ? $studio->episodes($series) : [];
        $selected = array_map(static fn ($episode): string => (string) $episode->getVideo()->getId(), $episodes);
        $available = [];
        foreach ($episodes as $episode) {
            $available[$episode->getVideo()->getId()] = $episode->getVideo();
        }
        foreach ($videos->page($user, 1, 100) as $video) {
            $available[$video->getId()] = $video;
        }
        $choices = [];
        $imported = $videos->owned($request->query->getInt('video'), $user);
        if ($imported && !in_array((string) $imported->getId(), $selected, true)) {
            $available[$imported->getId()] = $imported;
            $selected[] = (string) $imported->getId();
        }
        foreach ($available as $video) {
            $choices[$video->getTitle().' · #'.$video->getId()] = (string) $video->getId();
        }
        $publication = $series?->getPublicationSettings() ?? [];
        $form = $this->createForm(VideoSeriesType::class, ['name' => $series?->getName(), 'publication' => $publication, 'videos' => $selected, 'order' => implode(',', $selected), 'revision' => (string) ($series?->getRevision() ?? 0)], ['publication_accounts' => $publicationSettings->choices($user, $publication['accounts'] ?? []), 'videos' => $choices, 'csrf_token_id' => 'video_series_'.($id ?? 'new')])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$limiter->create('video-series-config-'.$user->getId())->consume()->isAccepted()) {
                    throw new \RuntimeException('Trop de demandes rapprochées. Réessaie dans quelques minutes.');
                }
                $data = $form->getData();
                $saved = $studio->save($user, $data['name'], $data['videos'], $data['order'] ?? '', (int) $data['revision'], $series, $data['publication']);
                $this->addFlash('success', 'Ta série est enregistrée. Chaque vidéo forme un épisode.');

                return $this->redirectToRoute('app_video_series_show', ['id' => $saved->getId()], 303);
            } catch (\RuntimeException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('series/videos_configure.html.twig', ['series' => $series, 'seriesForm' => $form, 'videos' => $available, 'uploadForm' => $this->createForm(VideoUploadType::class, null, ['action' => $this->generateUrl('app_video_upload', array_filter(['next' => 'series', 'series' => $id]))])], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series/videos/{id}', name: 'app_video_series_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, #[CurrentUser] User $user, VideoSeriesStudio $studio, \App\Service\EpisodePublications $publications): Response
    {
        $series = $studio->owned($id, $user) ?? throw $this->createNotFoundException();

        $episodes = $studio->episodes($series);

        return $this->render('series/videos_show.html.twig', ['series' => $series, 'episodes' => $episodes, 'publicationSummaries' => $publications->videoSummaries($episodes)], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series/videos/{id}/ajouter', name: 'app_video_series_add', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function add(int $id, Request $request, #[CurrentUser] User $user, VideoSeriesStudio $studio, VideoAssetRepository $videos): Response
    {
        $series = $studio->owned($id, $user) ?? throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('video_series_add_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            try {
                $episode = $studio->append($user, $series, $request->request->getInt('video'));
                $this->addFlash('success', 'La vidéo est dans ta série.');

                return $this->redirectToRoute('app_video_series_show', ['id' => $id, '_fragment' => 'video-episode-'.$episode->getId()], 303);
            } catch (\App\Service\VideoLibraryException $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }
        $query = mb_substr(trim($request->query->getString('q')), 0, 120);
        $activeVideoIds = array_map(static fn ($episode) => $episode->getVideo()->getId(), $studio->episodes($series));
        $upload = $this->createForm(VideoUploadType::class, null, ['action' => $this->generateUrl('app_video_upload', ['next' => 'series-append', 'series' => $id])]);

        return $this->render('series/videos_add.html.twig', [
            'series' => $series,
            'query' => $query,
            'library' => $videos->search($user, $query, $request->query->getInt('page', 1)),
            'activeVideoIds' => $activeVideoIds,
            'uploadForm' => $upload,
        ], new Response(status: $request->isMethod('POST') ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series/videos/{id}/programmer', name: 'app_video_series_schedule', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function schedule(int $id, Request $request, #[CurrentUser] User $user, VideoSeriesStudio $studio, \App\Service\VideoSeriesSchedule $schedule, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $series = $studio->owned($id, $user) ?? throw $this->createNotFoundException();
        $rows = $schedule->rows($series);
        $defaults = [];
        foreach ($rows as $row) {
            if ('plan' !== $row['kind'] || null === $row['draft']) {
                continue;
            }
            $episodeId = (string) $row['episode']->getId();
            $draft = $row['draft'];
            $defaults[$episodeId] = ['picked' => true, 'draft' => (string) $draft->getId(), 'revision' => (string) $draft->getRevision(), 'local' => $draft->getLocalTime() ?: '', 'zone' => $draft->getTimezone()];
        }
        $form = $this->createForm(\App\Form\VideoSeriesScheduleType::class, ['revision' => (string) $series->getRevision(), 'selection' => $defaults], ['revision' => (string) $series->getRevision(), 'episodes' => $defaults, 'csrf_token_id' => 'video_series_schedule_'.$id])->handleRequest($request);
        $preview = null;
        $snapshot = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $picked = [];
            foreach ($data['selection'] as $episodeId => $row) {
                if (!$row['picked']) {
                    continue;
                }
                $picked[(int) $episodeId] = ['draft' => (int) ($row['draft'] ?: 0), 'revision' => (int) ($row['revision'] ?: -1), 'local' => ($row['local'] ?: null), 'zone' => $row['zone']];
            }
            $intent = $request->request->getString('intent', 'preview');
            if (!in_array($intent, ['preview', 'confirm'], true)) {
                throw $this->createNotFoundException();
            }
            try {
                $session = $request->getSession();
                $revision = (int) $data['revision'];
                if ('confirm' === $intent) {
                    $reviewed = $session->get('video_series_schedule_preview');
                    if (!$data['confirm'] || !is_array($reviewed) || $reviewed['series'] !== $id || $reviewed['revision'] !== $revision
                        || !hash_equals($reviewed['token'], (string) $data['snapshot']) || $reviewed['picked'] !== $picked) {
                        throw new \App\Service\PublicationException('Vérifie un nouvel aperçu et coche la confirmation avant de programmer les épisodes.');
                    }
                    if (!$limiter->create('publish-'.$user->getId())->consume()->isAccepted()) {
                        throw new \App\Service\PublicationException('Trop de confirmations rapprochées.');
                    }
                    $session->remove('video_series_schedule_preview');
                    $episodes = $schedule->confirm($user, $series, $revision, $reviewed['ready']);
                    $count = count($episodes);
                    $this->addFlash('success', $count.' épisode'.($count > 1 ? 's' : '').' programmé'.($count > 1 ? 's' : '').' : les envois démarreront aux créneaux choisis.');

                    return $this->redirectToRoute('app_video_series_show', ['id' => $id, '_fragment' => 'video-episode-'.$episodes[0]], 303);
                }
                $session->remove('video_series_schedule_preview');
                $preview = $schedule->preview($series, $revision, $picked);
                if ($preview['ready']) {
                    $ready = [];
                    foreach ($preview['ready'] as $slot) {
                        $episodeId = $slot['episode']->getId();
                        $ready[$episodeId] = $picked[$episodeId];
                    }
                    $snapshot = bin2hex(random_bytes(32));
                    $session->set('video_series_schedule_preview', ['token' => $snapshot, 'series' => $id, 'revision' => $revision, 'picked' => $picked, 'ready' => $ready]);
                }
            } catch (\App\Service\PublicationException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('series/videos_schedule.html.twig', ['series' => $series, 'rows' => $rows, 'form' => $form, 'preview' => $preview, 'snapshot' => $snapshot], new Response(status: $form->isSubmitted() && !$form->isValid() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series/videos/episodes/{id}/publication', name: 'app_video_episode_publication', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function publication(int $id, Request $request, #[CurrentUser] User $user, \Doctrine\ORM\EntityManagerInterface $em, \App\Service\EpisodePublications $publications): Response
    {
        $episode = $em->getRepository(\App\Entity\VideoSeriesEpisode::class)->find($id);
        if (!$episode || $episode->getSeries()->getOwner()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('video_episode_publication_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $draft = $publications->prepareVideo($user, $episode, $request->request->getInt('revision'));

            return $this->redirectToRoute('draft' === $draft->getStatus() ? 'app_composer_start' : 'app_draft', ['draft' === $draft->getStatus() ? 'draft' : 'id' => $draft->getId()], 303);
        } catch (\App\Service\PublicationException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_video_series_show', ['id' => $episode->getSeries()->getId()], 303);
    }
}
