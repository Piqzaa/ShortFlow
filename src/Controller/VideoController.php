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
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $usage = $this->videos->usage($user);
        $pages = max(1, (int) ceil($usage['count'] / 12));
        $page = max(1, min($pages, $request->query->getInt('page', 1)));

        return $this->page('library/index.html.twig', ['videos' => $this->videos->page($user, $page), 'usage' => $usage, 'page' => $page, 'pages' => $pages]);
    }

    #[Route('/importer', name: 'app_video_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request, #[CurrentUser] User $user, #[Autowire(service: 'limiter.video_upload')] RateLimiterFactory $limiter): Response
    {
        $form = $this->createForm(VideoUploadType::class)->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && !$limiter->create((string) $user->getId())->consume()->isAccepted()) {
            $form->addError(new FormError('Trop d’imports rapprochés. Réessaie dans 15 minutes.'));
            $status = 429;
        } elseif ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $video = $this->library->import($user, $data['file'], $data['title']);
                $this->addFlash('success', 'Ta vidéo a rejoint ta bibliothèque.');

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['redirect' => $this->generateUrl('app_video_show', ['id' => $video->getId()])], 201, ['Cache-Control' => 'no-store, private']);
                }

                return $this->redirectToRoute('app_video_show', ['id' => $video->getId()], 303);
            } catch (VideoLibraryException $error) {
                $form->addError(new FormError($error->getMessage()));
                $status = 422;
            }
        }

        return $this->page('library/upload.html.twig', ['form' => $form], $status);
    }

    #[Route('/{id}', name: 'app_video_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, #[CurrentUser] User $user): Response
    {
        $video = $this->owned($id, $user);
        $form = $this->createForm(VideoRenameType::class, ['title' => $video->getTitle()])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->library->rename($video, $form->getData()['title']);
                $this->addFlash('success', 'Le nom de ta vidéo a été mis à jour.');

                return $this->redirectToRoute('app_video_show', ['id' => $id], 303);
            } catch (VideoLibraryException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->page('library/show.html.twig', ['video' => $video, 'form' => $form], $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/{id}/lecture', name: 'app_video_stream', requirements: ['id' => '\d+'], methods: ['GET', 'HEAD'])]
    public function media(int $id, #[CurrentUser] User $user): BinaryFileResponse
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
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'video-'.$id.'.'.pathinfo($video->getStorageName(), PATHINFO_EXTENSION));

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

                return $this->redirectToRoute('app_library', status: 303);
            } catch (VideoLibraryException $error) {
                $this->addFlash('error', $error->getMessage());

                return $this->page('library/delete.html.twig', ['video' => $video], 503);
            }
        }

        return $this->page('library/delete.html.twig', ['video' => $video]);
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
