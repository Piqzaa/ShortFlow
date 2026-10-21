<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\VideoEdit;
use App\Form\VideoEditType;
use App\Repository\VideoAssetRepository;
use App\Service\EditCaptions;
use App\Service\EditMusic;
use App\Service\VideoEdits;
use App\Service\VideoLibraryException;
use App\Service\VideoTranscriptions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/studio/montage')]
final class VideoEditController extends AbstractController
{
    public function __construct(private VideoEdits $edits, private VideoAssetRepository $videos, private EntityManagerInterface $em, private VideoTranscriptions $transcriptions, private EditCaptions $captions, private EditMusic $music)
    {
    }

    #[Route('', name: 'app_edit_index', methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_create', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            try {
                $project = $this->edits->create($user, $request->request->all('videos'));

                return $this->redirectToRoute('app_edit_show', ['id' => $project->getId()], 303);
            } catch (VideoLibraryException $error) {
                $this->addFlash('error', $error->getMessage());

                return $this->redirectToRoute('app_edit_index', status: 303);
            }
        }
        $query = mb_substr(trim($request->query->getString('q')), 0, 120);
        $results = $this->videos->search($user, $query, $request->query->getInt('page', 1));

        return $this->render('edit/index.html.twig', ['results' => $results, 'query' => $query, 'selected' => $request->query->all('videos'), 'projects' => $this->em->getRepository(VideoEdit::class)->findBy(['owner' => $user], ['updatedAt' => 'DESC'], 50), 'labels' => VideoEdit::LABELS], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/video/{id}', name: 'app_edit_video', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function startVideo(int $id, Request $request, #[CurrentUser] User $user): Response
    {
        $this->videos->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('edit_video_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $project = $this->edits->create($user, [$id]);

            return $this->redirectToRoute('app_edit_show', ['id' => $project->getId()], 303);
        } catch (VideoLibraryException $error) {
            $this->addFlash('error', $error->getMessage());

            return $this->redirectToRoute('app_dashboard', status: 303);
        }
    }

    #[Route('/{id}/appliquer', name: 'app_edit_apply', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function apply(int $id, Request $request, #[CurrentUser] User $user, \App\Service\PublicationDrafts $drafts): Response
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('edit_apply_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $draft = $drafts->applyEdit($user, $project);
            $this->addFlash('success', 'Vidéo remplacée dans cette préparation. Textes, réseaux et créneau conservés. Vérifie le récapitulatif avant de confirmer.');

            return $this->redirectToRoute('app_composer_start', ['draft' => $draft->getId()], 303);
        } catch (\App\Service\PublicationException $error) {
            $this->addFlash('error', $error->getMessage().' Ton export reste disponible dans tes vidéos.');

            return $this->redirectToRoute('app_edit_show', ['id' => $id], 303);
        }
    }

    #[Route('/{id}', name: 'app_edit_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, #[CurrentUser] User $user): Response
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();
        $form = $this->createForm(VideoEditType::class, ['title' => $project->getTitle(), 'format' => $project->getFormat()] + $project->getSettings())->handleRequest($request);
        $clips = $project->getClips();
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                $intent = $request->request->getString('intent');
                $export = 'export' === $intent;
                $withCaptions = 'captions' === $intent || ('auto' === $intent && $request->request->getBoolean('autoCaptions'));
                if ($export && !$request->request->getBoolean('confirm')) {
                    throw new VideoLibraryException('Confirme l’export de cette nouvelle vidéo.');
                }
                $settings = array_intersect_key($data, array_flip(['fit', 'captionStyle', 'intro', 'language', 'sourceVolume', 'musicVolume', 'musicOffset', 'musicLoop']));
                if ('auto' === $intent && !$withCaptions) {
                    $settings['captionStyle'] = 'off';
                }
                $this->edits->save($project, $data['title'], $data['format'], $request->request->all('clips'), $export, $settings, $withCaptions ? null : $request->request->all('captions'));
                if ($withCaptions) {
                    $this->transcriptions->queue($project);
                }
                $this->addFlash('success', $export ? 'L’export est lancé. Tu peux quitter cette page.' : ($withCaptions ? 'Création des sous-titres en local. Tu pourras relire le texte avant d’exporter.' : 'Ton assemblage est enregistré et prêt à être exporté.'));

                return $this->redirectToRoute('app_edit_show', ['id' => $id], 303);
            } catch (VideoLibraryException $error) {
                $form->addError(new FormError($error->getMessage()));
                // Preserve submitted cuts/order only for sources already belonging to this project.
                $submitted = $request->request->all('clips');
                if (count($submitted) >= 1 && count($submitted) <= 8) {
                    $known = array_column($clips, null, 'id');
                    $retained = [];
                    foreach ($submitted as $row) {
                        if (is_array($row) && isset($row['id'], $row['start'], $row['end']) && is_scalar($row['id']) && isset($known[$row['id']]) && is_scalar($row['start']) && is_scalar($row['end']) && is_numeric($row['start']) && is_numeric($row['end']) && is_finite((float) $row['start']) && is_finite((float) $row['end'])) {
                            $retained[] = ['id' => (int) $row['id'], 'start' => (float) $row['start'], 'end' => (float) $row['end']];
                        }
                    }
                    if ($retained) {
                        $clips = $retained;
                    }
                }
            }
        }
        $sources = [];
        foreach ($clips as $clip) {
            $sources[$clip['id']] = $this->videos->owned($clip['id'], $user);
        }
        $query = mb_substr(trim($request->query->getString('q')), 0, 120);
        $results = $this->videos->search($user, $query, $request->query->getInt('page', 1));

        return $this->render('edit/show.html.twig', ['project' => $project, 'clips' => $clips, 'sources' => $sources, 'form' => $form, 'labels' => VideoEdit::LABELS, 'query' => $query, 'results' => $results, 'captionsEnabled' => $this->transcriptions->enabled()], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/{id}/ajouter', name: 'app_edit_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(int $id, Request $request, #[CurrentUser] User $user): Response
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('edit_add_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $source = $this->videos->owned($request->request->getInt('video'), $user) ?? throw new VideoLibraryException('Cette vidéo est indisponible.');
            $clips = $project->getClips();
            $clips[] = ['id' => (int) $source->getId(), 'start' => 0.0, 'end' => round($source->getDuration(), 3)];
            $this->edits->save($project, $project->getTitle(), $project->getFormat(), $clips, false);
            $this->addFlash('success', 'Clip ajouté au montage.');
        } catch (VideoLibraryException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_edit_show', ['id' => $id], 303);
    }

    #[Route('/{id}/copier', name: 'app_edit_copy', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function copy(int $id, Request $request, #[CurrentUser] User $user): Response
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('edit_copy_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $this->edits->validate($user, $project->getClips(), false);
            $copy = $this->edits->create($user, array_values(array_unique(array_column($project->getClips(), 'id'))));
            $this->edits->save($copy, mb_substr($project->getTitle(), 0, 110).' · copie', $project->getFormat(), $project->getClips(), false);
            $copy->configure($project->getSettings());
            $copy->caption($project->getCaptions());
            if ($project->getMusicName()) {
                $copy->music($this->music->duplicate($project->getMusicName()), $project->getMusicTitle());
            }
            $this->em->flush();

            return $this->redirectToRoute('app_edit_show', ['id' => $copy->getId()], 303);
        } catch (VideoLibraryException $error) {
            $this->addFlash('error', $error->getMessage());

            return $this->redirectToRoute('app_edit_show', ['id' => $id], 303);
        }
    }

    #[Route('/{id}/musique', name: 'app_edit_music', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function music(int $id, Request $request, #[CurrentUser] User $user): Response
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('edit_music_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $file = $request->files->get('music');
            if (!$request->request->getBoolean('remove') && !$file instanceof UploadedFile) {
                throw new VideoLibraryException('Choisis une piste audio à importer.');
            }
            $this->edits->attachMusic($project, $request->request->getBoolean('remove') ? null : $file, $request->request->getBoolean('rights'));
            $this->addFlash('success', $project->getMusicName() ? 'Piste ajoutée. Ajuste son volume dans le panneau Audio.' : 'Piste musicale retirée.');
        } catch (VideoLibraryException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_edit_show', ['id' => $id], 303);
    }

    #[Route('/{id}/musique/lecture', name: 'app_edit_music_stream', requirements: ['id' => '\d+'], methods: ['GET', 'HEAD'])]
    public function musicStream(int $id, #[CurrentUser] User $user): BinaryFileResponse
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();
        $name = $project->getMusicName() ?? throw $this->createNotFoundException();
        $path = $this->music->path($name);
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path, headers: ['Content-Type' => 'audio/mp4', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Cross-Origin-Resource-Policy' => 'same-origin'], public: false);
    }

    #[Route('/{id}/sous-titres.srt', name: 'app_edit_srt', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function srt(int $id, #[CurrentUser] User $user): Response
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();

        return new Response($this->captions->srt($project->getCaptions()), headers: ['Content-Type' => 'application/x-subrip; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="sous-titres.srt"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    #[Route('/{id}/etat', name: 'app_edit_state', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function state(int $id, #[CurrentUser] User $user): Response
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();

        return $this->json(['status' => $project->getStatus(), 'label' => VideoEdit::LABELS[$project->getStatus()]], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/{id}/retirer', name: 'app_edit_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(int $id, Request $request, #[CurrentUser] User $user): Response
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('edit_remove_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            if (!$request->request->getBoolean('confirm')) {
                throw new VideoLibraryException('Confirme le retrait du projet.');
            }
            $this->edits->remove($project);
            $this->addFlash('success', 'Projet retiré. Les vidéos sources et les exports sont conservés.');

            return $this->redirectToRoute('app_edit_index', status: 303);
        } catch (VideoLibraryException $error) {
            $this->addFlash('error', $error->getMessage());

            return $this->redirectToRoute('app_edit_show', ['id' => $id], 303);
        }
    }
}
