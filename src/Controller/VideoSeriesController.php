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
    public function configure(Request $request, #[CurrentUser] User $user, VideoSeriesStudio $studio, VideoAssetRepository $videos, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter, ?int $id = null): Response
    {
        $series = null === $id ? null : ($studio->owned($id, $user) ?? throw $this->createNotFoundException());
        $choices = [];
        foreach ($videos->page($user, 1, 100) as $video) {
            $choices[$video->getTitle().' · #'.$video->getId()] = (string) $video->getId();
        }
        $selected = $series ? array_map(static fn ($episode): string => (string) $episode->getVideo()->getId(), $studio->episodes($series)) : [];
        $imported = $videos->owned($request->query->getInt('video'), $user);
        if ($imported && !in_array((string) $imported->getId(), $selected, true)) {
            $selected[] = (string) $imported->getId();
        }
        $form = $this->createForm(VideoSeriesType::class, ['name' => $series?->getName(), 'videos' => $selected, 'order' => implode(',', $selected), 'revision' => (string) ($series?->getRevision() ?? 0)], ['videos' => $choices, 'csrf_token_id' => 'video_series_'.($id ?? 'new')])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$limiter->create('video-series-config-'.$user->getId())->consume()->isAccepted()) {
                    throw new \RuntimeException('Trop de demandes rapprochées. Réessaie dans quelques minutes.');
                }
                $data = $form->getData();
                $saved = $studio->save($user, $data['name'], $data['videos'], $data['order'] ?? '', (int) $data['revision'], $series);
                $this->addFlash('success', 'Ta série est enregistrée. Chaque vidéo forme un épisode.');

                return $this->redirectToRoute('app_video_series_show', ['id' => $saved->getId()], 303);
            } catch (\RuntimeException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('series/videos_configure.html.twig', ['series' => $series, 'seriesForm' => $form, 'uploadForm' => $this->createForm(VideoUploadType::class, null, ['action' => $this->generateUrl('app_video_upload', array_filter(['next' => 'series', 'series' => $id]))])], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series/videos/{id}', name: 'app_video_series_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, #[CurrentUser] User $user, VideoSeriesStudio $studio): Response
    {
        $series = $studio->owned($id, $user) ?? throw $this->createNotFoundException();

        return $this->render('series/videos_show.html.twig', ['series' => $series, 'episodes' => $studio->episodes($series)], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }
}
