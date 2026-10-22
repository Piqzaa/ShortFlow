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

    #[Route('/styles/enregistrer', name: 'app_edit_style', methods: ['POST'])]
    public function saveStyle(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('edit_style', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $name = trim($request->request->getString('name'));
            if ('' === $name || mb_strlen($name) > 30) {
                throw new VideoLibraryException('Nomme le style en 30 caractères maximum.');
            }
            $style = \App\Service\EditTypography::validate($request->request->all('style'));
            $this->em->wrapInTransaction(function () use ($user, $name, $style): void {
                $this->em->lock($user, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($user);
                $user->saveEditorStyle($name, $style);
            });

            return $this->json(['styles' => $user->getEditorStyles()], 200, ['Cache-Control' => 'private, no-store']);
        } catch (VideoLibraryException $error) {
            return $this->json(['message' => $error->getMessage()], 422);
        }
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
                $settings = array_intersect_key($data, array_flip(['background', 'fit', 'captionStyle', 'intro', 'language', 'sourceVolume', 'musicVolume', 'musicOffset', 'musicLoop', 'captionX', 'captionY', 'captionSize']));
                $settings['typography'] = $request->request->has('caption_appearance') ? $request->request->all('caption_appearance') : ($project->getSettings()['typography'] ?? []);
                if ('auto' === $intent && !$withCaptions) {
                    $settings['captionStyle'] = 'off';
                }
                $this->edits->save($project, $data['title'], $data['format'], $request->request->all('clips'), $export, $settings, $request->request->all('captions'), $request->request->getInt('edit_revision'), $request->request->getBoolean('captions_current'), $request->request->all('texts'), $request->request->has('audio_current') ? $request->request->all('audio') : null);
                if ($withCaptions) {
                    $this->transcriptions->queue($project);
                }
                if (str_contains($request->headers->get('Accept', ''), 'application/json')) {
                    return $this->json($this->editState($project), $withCaptions || $export ? 202 : 200, ['Cache-Control' => 'private, no-store']);
                }
                $this->addFlash('success', $export ? 'L’export est lancé. Tu peux quitter cette page.' : ($withCaptions ? 'Création des sous-titres en local. Tu pourras relire le texte avant d’exporter.' : 'Ton assemblage est enregistré et prêt à être exporté.'));

                return $this->redirectToRoute('app_edit_show', ['id' => $id], 303);
            } catch (VideoLibraryException $error) {
                $form->addError(new FormError($error->getMessage()));
                // Preserve submitted cuts/order only for sources already belonging to this project.
                $submitted = $request->request->all('clips');
                if (count($submitted) >= 1 && count($submitted) <= 32) {
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
        if ($form->isSubmitted() && str_contains($request->headers->get('Accept', ''), 'application/json')) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return $this->json(['message' => implode(' ', array_unique($errors)) ?: 'Vérifie les champs du montage.'], 422, ['Cache-Control' => 'private, no-store']);
        }
        $sources = [];
        foreach ($clips as $clip) {
            $sources[$clip['id']] = $this->videos->owned($clip['id'], $user);
        }
        foreach ($project->getAudioClips() as $audio) {
            if ('video' === $audio['kind']) {
                $sources[$audio['source']] = $this->videos->owned($audio['source'], $user);
            }
        }
        $query = mb_substr(trim($request->query->getString('q')), 0, 120);
        $results = $this->videos->search($user, $query, $request->query->getInt('page', 1));

        return $this->render('edit/show.html.twig', ['project' => $project, 'clips' => $clips, 'sources' => $sources, 'form' => $form, 'labels' => VideoEdit::LABELS, 'query' => $query, 'results' => $results, 'captionsEnabled' => $this->transcriptions->enabled(), 'focusClip' => $request->query->getInt('clip'), 'editorStyles' => $user->getEditorStyles(), 'musicDuration' => $project->getMusicName() ? $this->music->duration($project->getMusicName()) : 0], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/{id}/image', name: 'app_edit_image', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function image(int $id, Request $request, #[CurrentUser] User $user, \App\Service\EditStillImages $images): Response
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('edit_image_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            if ($project->isActive() || 'ready' === $project->getStatus() || count($project->getClips()) >= 32) {
                throw new VideoLibraryException('Attends le traitement ou libère un fragment avant d’ajouter une image.');
            }
            $solid = $request->request->getBoolean('solid');
            if ($solid && count($project->getTexts()) >= 30) {
                throw new VideoLibraryException('Retire un texte avant d’ajouter un carton.');
            }
            $file = $request->files->get('image');
            if (!$solid && !$file instanceof UploadedFile) {
                throw new VideoLibraryException('Choisis une image.');
            }
            $seconds = (float) $request->request->get('seconds', 4);
            $source = $solid ? $images->solid($user, $request->request->getString('color'), $seconds) : $images->import($user, $file, $seconds);
            $clips = $project->getClips();
            $clips[] = ['id' => (int) $source->getId(), 'start' => 0.0, 'end' => $source->getDuration(), 'at' => $project->getDuration(), 'origin' => bin2hex(random_bytes(8))];
            $texts = $project->getTexts();
            if ($solid) {
                $texts[] = ['id' => bin2hex(random_bytes(8)), 'text' => 'Ton texte', 'start' => $project->getDuration(), 'end' => $project->getDuration() + $source->getDuration(), 'x' => 0.5, 'y' => 0.5, 'size' => 6, 'color' => '#ffffff'];
            }
            $this->edits->save($project, $project->getTitle(), $project->getFormat(), $clips, false, texts: $texts);
            $this->addFlash('success', $solid ? 'Carton ajouté. Personnalise son texte et sa durée.' : 'Image ajoutée au montage. Tu peux régler sa durée, son cadrage et ses effets.');
        } catch (VideoLibraryException $error) {
            $this->addFlash('error', (isset($source) ? 'L’image reste disponible dans tes vidéos. ' : '').$error->getMessage());
        }

        return $this->redirectToRoute('app_edit_show', ['id' => $id, 'clip' => isset($source) ? $source->getId() : null], 303);
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
            $clips[] = ['id' => (int) $source->getId(), 'start' => 0.0, 'end' => round($source->getDuration(), 3), 'origin' => bin2hex(random_bytes(8)), 'at' => $project->getDuration()];
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
            $copy->textLayers($project->getTexts());
            $copy->setAudioClips($project->getAudioClips());
            if ($project->getMusicName()) {
                $files = $project->getMusicFiles();
                foreach ($files as &$file) {
                    $file['name'] = $this->music->duplicate($file['name']);
                }unset($file);
                $copy->setMusicFiles($files);
                $last = end($files);
                $copy->music($last['name'], $last['title']);
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
            $this->edits->attachMusic($project, $request->request->getBoolean('remove') ? null : $file, $request->request->getBoolean('rights'), $request->request->getString('musicId') ?: null);
            $this->addFlash('success', $project->getMusicName() ? 'Piste ajoutée. Ajuste son volume dans le panneau Audio.' : 'Piste musicale retirée.');
        } catch (VideoLibraryException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_edit_show', ['id' => $id], 303);
    }

    #[Route('/{id}/onde', name: 'app_edit_waveform', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function waveform(int $id, Request $request, #[CurrentUser] User $user, \App\Service\EditWaveforms $waveforms, \App\Service\PrivateVideoStorage $storage): Response
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();
        if ($request->query->getInt('video')) {
            $video = $this->videos->owned($request->query->getInt('video'), $user) ?? throw $this->createNotFoundException();
            $path = $storage->path($video->getStorageName());
        } else {
            $track = $project->getMusicFiles()[$request->query->getString('track')] ?? throw $this->createNotFoundException();
            $path = $this->music->path($track['name']);
        }
        try {
            $state = $waveforms->read($path);

            return $this->json($state, 'pending' === $state['status'] ? 202 : 200, ['Cache-Control' => 'private, no-store']);
        } catch (VideoLibraryException $error) {
            return $this->json(['status' => 'unavailable'], 422, ['Cache-Control' => 'private, no-store']);
        }
    }

    #[Route('/{id}/musique/lecture', name: 'app_edit_music_stream', requirements: ['id' => '\d+'], methods: ['GET', 'HEAD'])]
    public function musicStream(int $id, Request $request, #[CurrentUser] User $user): BinaryFileResponse
    {
        $project = $this->edits->owned($id, $user) ?? throw $this->createNotFoundException();
        $track = $request->query->getString('track');
        $name = '' === $track ? $project->getMusicName() : ($project->getMusicFiles()[$track]['name'] ?? null);
        $name ?? throw $this->createNotFoundException();
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

        return $this->json($this->editState($project), headers: ['Cache-Control' => 'private, no-store']);
    }

    /** @return array{status:string,label:string,revision:int,captions:list<array{start:float,end:float,text:string,x?:float,y?:float}>,captionStyle:string,issue:?string} */
    private function editState(VideoEdit $project): array
    {
        return ['status' => $project->getStatus(), 'label' => VideoEdit::LABELS[$project->getStatus()], 'revision' => $project->getRevision(), 'captions' => $project->getCaptions(), 'captionStyle' => $project->getSettings()['captionStyle'], 'issue' => $project->getIssue()];
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
