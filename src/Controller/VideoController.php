<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\VideoAsset;
use App\Form\VideoRenameType;
use App\Form\VideoUploadType;
use App\Repository\VideoAssetRepository;
use App\Service\PrivateVideoStorage;
use App\Service\VideoLibrary;
use App\Service\VideoLibraryException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/studio/bibliotheque')]
final class VideoController extends AbstractController
{
    public function __construct(private VideoAssetRepository $videos, private VideoLibrary $library, private PrivateVideoStorage $storage)
    {
    }

    #[Route('', name: 'app_library', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $parameters = array_intersect_key($request->query->all(), array_flip(['q', 'page', 'choose', 'date', 'zone']));
        $response = $this->redirectToRoute('app_dashboard', $parameters);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    #[Route('/importer', name: 'app_video_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request, #[CurrentUser] User $user, #[Autowire(service: 'limiter.video_upload')] RateLimiterFactory $limiter, \App\Service\VideoEdits $edits, \App\Service\VideoSeriesStudio $videoSeries): Response
    {
        $next = $request->query->getString('next');
        $seriesContext = 'series' === $next;
        $appendToSeries = 'series-append' === $next;
        $series = $appendToSeries || ($seriesContext && $request->query->has('series')) ? ($videoSeries->owned($request->query->getInt('series'), $user) ?? throw $this->createNotFoundException()) : null;
        $editing = !$appendToSeries && $request->query->has('montage') ? ($edits->owned($request->query->getInt('montage'), $user) ?? throw $this->createNotFoundException()) : null;
        if ($editing && ($editing->isActive() || 'ready' === $editing->getStatus())) {
            return $this->redirectToRoute('app_edit_show', ['id' => $editing->getId()], 303);
        }
        $publicationImport = in_array($next, ['publication', 'compose'], true) && !$editing;
        $form = $this->createForm(VideoUploadType::class, options: ['publication_retention' => $publicationImport])->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && !$limiter->create((string) $user->getId())->consume()->isAccepted()) {
            $form->addError(new FormError('Trop d’imports rapprochés. Réessaie dans 15 minutes.'));
            $status = 429;
        } elseif ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $temporary = $publicationImport && empty($data['keep']) && 'edit' !== $request->request->getString('intent');
                $video = $this->library->import($user, $data['file'], $data['title'], temporary: $temporary);
                $this->addFlash('success', 'Ta vidéo a rejoint ta bibliothèque.');

                $redirect = 'edit' === $request->request->getString('intent') || 'edit' === $request->query->getString('next')
                    ? $this->generateUrl('app_edit_index', ['videos' => [$video->getId()]])
                    : ('compose' === $request->query->getString('next')
                    ? $this->generateUrl('app_composer_start', array_filter(['videos' => [$video->getId()], 'date' => $request->query->getString('date'), 'zone' => $request->query->getString('zone', $user->getTimezone())]))
                    : $this->generateUrl('app_dashboard'));

                if ($editing) {
                    try {
                        $clips = $editing->getClips();
                        $clips[] = ['id' => (int) $video->getId(), 'start' => 0.0, 'end' => round($video->getDuration(), 3), 'origin' => bin2hex(random_bytes(8))];
                        $edits->save($editing, $editing->getTitle(), $editing->getFormat(), $clips, false);
                    } catch (VideoLibraryException $error) {
                        $this->addFlash('error', $error->getMessage().' La vidéo importée reste dans ta bibliothèque.');
                    }
                    $redirect = $this->generateUrl('app_edit_show', ['id' => $editing->getId()]);
                }
                if ($appendToSeries && $series) {
                    try {
                        $episode = $videoSeries->append($user, $series, (int) $video->getId());
                        $this->addFlash('success', 'Ta vidéo a été ajoutée à la fin de la série.');
                        $redirect = $this->generateUrl('app_video_series_show', ['id' => $series->getId(), '_fragment' => 'video-episode-'.$episode->getId()]);
                    } catch (VideoLibraryException $error) {
                        $this->addFlash('error', $error->getMessage().' La vidéo reste dans ta bibliothèque.');
                        $redirect = $this->generateUrl('app_video_series_add', ['id' => $series->getId()]);
                    }
                }
                if ($request->isXmlHttpRequest()) {
                    $result = ['redirect' => $redirect];
                    if ($seriesContext) {
                        $result['redirect'] = $this->generateUrl($series ? 'app_video_series_edit' : 'app_video_series_new', array_filter(['id' => $series?->getId(), 'video' => $video->getId()]));
                        $result['video'] = ['id' => $video->getId(), 'title' => $video->getTitle(), 'preview' => $this->generateUrl('app_video_stream', ['id' => $video->getId()]).'#t=0.1'];
                    }

                    return new JsonResponse($result, 201, ['Cache-Control' => 'no-store, private']);
                }

                if ($seriesContext) {
                    return $this->redirectToRoute($series ? 'app_video_series_edit' : 'app_video_series_new', array_filter(['id' => $series?->getId(), 'video' => $video->getId()]), 303);
                }

                return $this->redirect($redirect, 303);
            } catch (VideoLibraryException $error) {
                $form->addError(new FormError($error->getMessage()));
                $status = 422;
            }
        }

        if ($form->isSubmitted() && $request->isXmlHttpRequest()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return new JsonResponse(['message' => implode(' ', array_unique($errors)) ?: 'L’import n’a pas abouti. Vérifie le fichier.'], $status, ['Cache-Control' => 'no-store, private']);
        }

        return $this->page('library/upload.html.twig', ['form' => $form, 'appendSeries' => $appendToSeries ? $series : null, 'publicationImport' => $publicationImport, 'bulkImport' => in_array($next, ['', 'publication'], true) && !$editing, 'generalImport' => '' === $next && !$editing], $status);
    }

    #[Route('/{id}', name: 'app_video_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, #[CurrentUser] User $user, \App\Service\VideoSeriesStudio $series): Response
    {
        $video = $this->owned($id, $user);
        $episodeId = $request->query->getInt('episode');
        $episode = $episodeId > 0 ? $series->sourceEpisode($episodeId, $user, $video) : null;
        $seriesReturn = $episode ? $this->generateUrl('app_video_series_show', ['id' => $episode->getSeries()->getId(), '_fragment' => 'video-episode-'.$episode->getId()]) : null;
        $form = $this->createForm(VideoRenameType::class, ['title' => $video->getTitle()])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->library->rename($video, $form->getData()['title']);
                $this->addFlash('success', 'Le nom de ta vidéo a été mis à jour.');

                return $seriesReturn ? $this->redirect($seriesReturn, 303) : $this->redirectToRoute('app_dashboard', status: 303);
            } catch (VideoLibraryException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        $deadline = $video->getRetentionDueAt();
        if ($deadline && (null === ($lastSuccess = $this->videos->lastSuccessfulPublicationIfUnprotected($id)) || $deadline < $lastSuccess->modify(VideoLibrary::RETENTION_PERIOD))) {
            $deadline = null;
        }

        return $this->page('library/show.html.twig', ['video' => $video, 'retentionDueAt' => $deadline, 'form' => $form, 'seriesReturn' => $seriesReturn, 'publishedPlatforms' => $this->videos->publishedPlatforms($user, [$video])], $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/{id}/conserver', name: 'app_video_keep', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function keep(int $id, Request $request, #[CurrentUser] User $user): Response
    {
        $video = $this->owned($id, $user);
        if (!$this->isCsrfTokenValid('keep_video_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $this->library->keep($video);
        } catch (\Doctrine\ORM\EntityNotFoundException) {
            throw $this->createNotFoundException('Vidéo indisponible.');
        }
        $this->addFlash('success', 'Ta vidéo est conservée dans ta bibliothèque.');

        return $this->redirectToRoute('app_video_show', ['id' => $id], 303);
    }

    #[Route('/{id}/lecture', name: 'app_video_stream', requirements: ['id' => '\d+'], methods: ['GET', 'HEAD'])]
    #[Route('/{id}/telecharger', name: 'app_video_download', requirements: ['id' => '\d+'], methods: ['GET', 'HEAD'])]
    public function media(int $id, Request $request, #[CurrentUser] User $user): BinaryFileResponse
    {
        $video = $this->owned($id, $user);
        try {
            $path = $this->storage->path($video->getStorageName());
        } catch (VideoLibraryException) {
            throw $this->createNotFoundException('Vidéo indisponible.');
        }
        if (!is_file($path) || !is_readable($path)) {
            throw $this->createNotFoundException('Vidéo indisponible.');
        }
        clearstatcache(true, $path);
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $video->getMimeType());
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $download = 'app_video_download' === $request->attributes->get('_route');
        $extension = 'video/mp4' === $video->getMimeType() ? 'mp4' : 'webm';
        $filename = 'video-'.$id.'.'.$extension;
        if ($download) {
            $title = trim(preg_replace('~[\x00-\x1f\x7f/\\\\]+~u', '-', $video->getTitle()), ' .-');
            $filename = ('' === $title ? 'video-'.$id : mb_substr($title, 0, 100)).'.'.$extension;
        }
        $response->setContentDisposition($download ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE, $filename, 'video-'.$id.'.'.$extension);

        return $response;
    }

    #[Route('/{id}/supprimer', name: 'app_video_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function delete(int $id, Request $request, #[CurrentUser] User $user): Response
    {
        $video = $this->owned($id, $user);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('delete_video_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('La confirmation a expiré. Recharge la page.');
            }
            try {
                $this->library->delete($video);
                $this->addFlash('success', 'Ta vidéo a été supprimée.');

                return $this->redirectToRoute('app_dashboard', status: 303);
            } catch (VideoLibraryException $error) {
                $this->addFlash('error', $error->getMessage());

                return $this->page('library/delete.html.twig', ['video' => $video], 503);
            }
        }

        return $this->page('library/delete.html.twig', ['video' => $video]);
    }

    #[Route('/supprimer-selection', name: 'app_videos_delete', methods: ['GET', 'POST'])]
    public function deleteSelection(Request $request, #[CurrentUser] User $user): Response
    {
        $parameters = $request->isMethod('POST') ? $request->request : $request->query;
        $values = $parameters->all('videos');
        if (!$values || count($values) > 20) {
            $this->addFlash('error', 'Sélectionne entre 1 et 20 vidéos.');

            return $this->redirectToRoute('app_dashboard', status: 303);
        }
        $ids = [];
        foreach ($values as $value) {
            if (!is_scalar($value) || !ctype_digit((string) $value) || false === filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                throw $this->createNotFoundException('Sélection indisponible.');
            }
            $ids[] = (int) $value;
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        $videos = array_map(fn (int $id): VideoAsset => $this->owned($id, $user), $ids);
        $tokenId = 'delete_videos_'.hash('sha256', implode(',', $ids));
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('La confirmation a expiré. Recharge la page.');
            }
            try {
                $this->library->deleteMany($videos);
                $this->addFlash('success', count($videos).' vidéo(s) supprimée(s).');

                return $this->redirectToRoute('app_dashboard', status: 303);
            } catch (VideoLibraryException $error) {
                $this->addFlash('error', $error->getMessage());

                return $this->page('library/delete_selection.html.twig', ['videos' => $videos, 'tokenId' => $tokenId], 409);
            }
        }

        return $this->page('library/delete_selection.html.twig', ['videos' => $videos, 'tokenId' => $tokenId]);
    }

    private function owned(int $id, User $user): VideoAsset
    {
        return $this->videos->owned($id, $user) ?? throw $this->createNotFoundException('Vidéo introuvable.');
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, array $data, int $status = 200): Response
    {
        $response = $this->render($template, $data, new Response(status: $status));
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }
}
