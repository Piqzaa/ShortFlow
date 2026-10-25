<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\User;
use App\Entity\VideoEdit;
use App\Message\RenderVideoEdit;
use App\Repository\VideoAssetRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

final class VideoEdits
{
    public function __construct(private EntityManagerInterface $em, private VideoAssetRepository $videos, private PrivateVideoStorage $storage, private VideoLibrary $library, private VideoEditRenderer $renderer, private MessageBusInterface $bus, private ManagerRegistry $registry, private EditCaptions $captionTools, private EditMusic $music, private CaptionTiming $timing, private EditTextLayers $textLayers, private EditAudioClips $audioClips, private EpisodeReservations $episodeReservations)
    {
    }

    public function owned(int $id, User $owner): ?VideoEdit
    {
        return $this->em->getRepository(VideoEdit::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /** @param array<mixed> $ids */
    public function create(User $owner, array $ids): VideoEdit
    {
        if (!$ids || count($ids) > 8 || count(array_filter($ids, 'is_scalar')) !== count($ids) || count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
            throw new VideoLibraryException('Choisis entre 1 et 8 clips différents. Tu pourras ensuite les raccourcir.');
        }
        $clips = [];
        foreach ($ids as $id) {
            if (!is_scalar($id) || !ctype_digit((string) $id) || !$video = $this->videos->owned((int) $id, $owner)) {
                throw new VideoLibraryException('Une vidéo de la sélection est indisponible.');
            }
            $clips[] = ['id' => (int) $video->getId(), 'start' => 0.0, 'end' => round($video->getDuration(), 3), 'origin' => bin2hex(random_bytes(8))];
        }
        $project = new VideoEdit($owner, 'Mon montage', $clips);
        $this->em->wrapInTransaction(function () use ($owner, $project): void {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            if ($this->em->getRepository(VideoEdit::class)->count(['owner' => $owner]) >= 50) {
                throw new VideoLibraryException('Tu as déjà 50 montages. Retire un ancien projet pour continuer.');
            }
            $this->em->persist($project);
        });

        return $project;
    }

    public function forPublication(\App\Entity\PublicationDraft $draft, int $source): VideoEdit
    {
        if ($draft->getEpisode()) {
            throw new PublicationException('Ouvre le montage depuis l’épisode, puis valide la version exportée avant de préparer sa publication.');
        }
        if ('draft' !== $draft->getStatus() || !in_array($source, $draft->videoIds(), true)) {
            throw new VideoLibraryException('Rouvre cette préparation avant de modifier sa vidéo.');
        }
        $project = $this->create($draft->getOwner(), [$source]);
        $project->forPublication((int) $draft->getId(), $draft->getRevision(), $source);
        $this->em->flush();

        return $project;
    }

    /** @param array<mixed> $input */
    public function save(VideoEdit $project, string $title, string $format, array $input, bool $export, ?array $settings = null, ?array $captions = null, ?int $revision = null, bool $captionsCurrent = true, ?array $texts = null, ?array $audioClips = null): void
    {
        $title = trim($title);
        if ('' === $title || mb_strlen($title) > 120 || !isset(VideoEdit::FORMATS[$format]) || count($input) < 1 || count($input) > 32) {
            throw new VideoLibraryException('Indique un nom, un format et entre 1 et 32 fragments.');
        }
        $input = array_values(array_filter($input, static fn ($row): bool => !is_array($row) || empty($row['omit'])));
        if (!$input) {
            throw new VideoLibraryException('Garde au moins un clip dans ton montage.');
        }
        usort($input, static fn ($a, $b): int => ((int) (is_array($a) ? ($a['position'] ?? 0) : 0)) <=> ((int) (is_array($b) ? ($b['position'] ?? 0) : 0)));
        $clips = [];
        foreach ($input as $row) {
            if (!is_array($row) || !isset($row['id'], $row['start'], $row['end']) || !is_scalar($row['id']) || !ctype_digit((string) $row['id']) || !is_scalar($row['start']) || !is_scalar($row['end']) || !is_numeric($row['start']) || !is_numeric($row['end'])) {
                throw new VideoLibraryException('Vérifie le début et la fin de chaque clip, en secondes.');
            }
            $origin = $row['origin'] ?? null;
            if (null !== $origin && (!is_string($origin) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $origin))) {
                throw new VideoLibraryException('Ce fragment est invalide. Recharge le montage.');
            }
            $extra = ['look' => EditClipStyle::validate(is_array($row['look'] ?? null) ? $row['look'] : [])];
            foreach (['at' => [0, 179.4], 'rate' => [0.25, 4]] as $key => [$minimum, $maximum]) {
                if (isset($row[$key])) {
                    if (!is_scalar($row[$key]) || !is_numeric($row[$key]) || !is_finite((float) $row[$key]) || $row[$key] < $minimum || $row[$key] > $maximum) {
                        throw new VideoLibraryException('Vérifie la position et la vitesse des clips.');
                    }
                    $extra[$key] = (float) $row[$key];
                }
            }
            $clips[] = $extra + ['id' => (int) $row['id'], 'start' => (float) $row['start'], 'end' => (float) $row['end']] + (null !== $origin ? ['origin' => $origin] : []);
        }
        $this->validate($project->getOwner(), $clips, $export);
        $settings ??= $project->getSettings();
        if (!is_string($settings['background'] ?? '#000000') || !preg_match('/^#[a-fA-F0-9]{6}$/D', $settings['background'] ?? '#000000')) {
            throw new VideoLibraryException('Choisis une couleur de fond valide.');
        }
        $settings['background'] ??= '#000000';
        $settings['typography'] = EditTypography::validate($settings['typography'] ?? ['color' => 'social' === ($settings['captionStyle'] ?? '') ? '#ffff00' : '#ffffff']);
        $settings += ['language' => 'fr', 'captionX' => 0.5, 'captionY' => 0.85, 'captionSize' => 4];
        foreach (['captionX' => [0.05, 0.95], 'captionY' => [0.05, 0.95], 'captionSize' => [2, 10]] as $key => [$min, $max]) {
            if (!is_numeric($settings[$key]) || !is_finite((float) $settings[$key]) || $settings[$key] < $min || $settings[$key] > $max) {
                throw new VideoLibraryException('Vérifie la position et la taille des sous-titres.');
            }
            $settings[$key] = (float) $settings[$key];
        }
        $settings += ['sourceVolume' => 100, 'musicVolume' => 20, 'musicOffset' => 0, 'musicLoop' => true];
        foreach (['sourceVolume' => 100, 'musicVolume' => 100, 'musicOffset' => 599] as $key => $maximum) {
            if (!is_numeric($settings[$key]) || !is_finite((float) $settings[$key]) || $settings[$key] < 0 || $settings[$key] > $maximum) {
                throw new VideoLibraryException('Vérifie les volumes et le début de la piste musicale.');
            }
            $settings[$key] = (float) $settings[$key];
        }
        if (!is_bool($settings['musicLoop'])) {
            throw new VideoLibraryException('Vérifie la répétition de la musique.');
        }
        if ($project->getMusicName() && $settings['musicOffset'] >= $this->music->duration($project->getMusicName())) {
            throw new VideoLibraryException('Le début de la musique doit rester à l’intérieur de la piste.');
        }
        if (!in_array($settings['language'], ['fr', 'en', 'auto'], true)) {
            throw new VideoLibraryException('Choisis une langue prise en charge.');
        }
        if (!isset($settings['fit'], $settings['captionStyle'], $settings['intro']) || !in_array($settings['fit'], ['contain', 'cover'], true) || !in_array($settings['captionStyle'], ['off', 'clean', 'social'], true) || !is_bool($settings['intro'])) {
            throw new VideoLibraryException('Choisis un cadrage et un style valides.');
        }
        $duration = EditTimeline::duration($clips);
        $captionRows = null === $captions ? $project->getCaptions() : $captions;
        if (null === $captions || !$captionsCurrent) {
            $detached = array_filter($project->getAudioClips(), static fn (array $row): bool => 'video' === $row['kind']);
            if ($detached) {
                $captionRows = array_values(array_filter($captionRows, static fn (array $row): bool => $row['start'] < $duration - 0.01));
                foreach ($captionRows as &$captionRow) {
                    $captionRow['end'] = min($captionRow['end'], $duration);
                }
                unset($captionRow);
            } else {
                $captionRows = $this->timing->remap($this->captionTools->validate($captionRows, $project->getDuration()), $project->getClips(), $clips);
            }
        }
        $rows = $this->captionTools->validate($captionRows, $duration);
        $layers = $this->textLayers->validate($texts ?? $project->getTexts(), $duration);
        $audioInput = $audioClips ?? $project->getAudioClips();
        if (null === $audioClips) {
            $audioInput = array_values(array_filter($audioInput, static fn (array $row): bool => $row['start'] < $duration - 0.1));
            foreach ($audioInput as &$audioRow) {
                $audioRow['end'] = min($audioRow['end'], $duration);
            }
            unset($audioRow);
        }
        $audioRows = $this->audioClips->validate($project, $audioInput, $duration);
        if ($export && 'off' !== $settings['captionStyle'] && !$rows) {
            throw new VideoLibraryException('Génère ou ajoute les sous-titres avant d’exporter avec ce style.');
        }
        $this->em->wrapInTransaction(function () use ($project, $title, $format, $clips, $export, $settings, $rows, $revision, $layers, $audioRows): void {
            $this->em->lock($project->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($project, LockMode::PESSIMISTIC_WRITE);
            if (null !== $revision && $project->getRevision() !== $revision) {
                throw new VideoLibraryException('Le montage a changé dans un autre onglet. Recharge avant de continuer ; tes changements restent affichés ici.');
            }
            if ($project->isActive() || 'ready' === $project->getStatus()) {
                if ($export) {
                    return;
                }
                throw new VideoLibraryException('Ce montage a déjà été envoyé à l’export. Actualise le suivi.');
            }
            $this->validate($project->getOwner(), $clips, $export);
            if ($export) {
                $this->capacity($project->getOwner());
                if ($this->em->getRepository(VideoEdit::class)->count(['owner' => $project->getOwner(), 'status' => ['queued', 'rendering', 'transcribing']]) > 0) {
                    throw new VideoLibraryException('Un montage est déjà en cours d’export. Attends sa fin avant le suivant.');
                }
            }
            $project->revise($title, $format, $clips);
            $project->configure($settings);
            $project->caption($rows);
            $project->textLayers($layers);
            $project->setAudioClips($audioRows);
            if ($export) {
                $project->progress('queued');
                $this->em->flush();
                $this->bus->dispatch(new RenderVideoEdit((int) $project->getId()));
            }
        });
    }

    /** @param list<array{id:int,start:float,end:float,origin?:string,at?:float,rate?:float}> $clips */
    public function validate(User $owner, array $clips, bool $export = true): void
    {
        $total = 0.0;
        foreach (EditTimeline::layout($clips) as $clip) {
            if (!is_finite($clip['at']) || $clip['at'] < $total - 0.001 || $clip['rate'] < 0.25 || $clip['rate'] > 4) {
                throw new VideoLibraryException('Les clips vidéo ne doivent pas se chevaucher. Déplace-les sur un espace libre.');
            }
            $video = $this->videos->owned($clip['id'], $owner);
            if (!$video || !is_file($this->storage->path($video->getStorageName()))) {
                throw new VideoLibraryException('Un clip source a été retiré ou est indisponible. Retire-le du montage ou choisis une autre vidéo.');
            }
            if (!is_finite($clip['start']) || !is_finite($clip['end']) || $clip['start'] < 0 || $clip['end'] > $video->getDuration() + 0.001 || $clip['end'] - $clip['start'] < 0.1) {
                throw new VideoLibraryException('Chaque clip doit durer au moins 0,1 seconde et rester dans les limites de sa source.');
            }
            $total = $clip['at'] + ($clip['end'] - $clip['start']) / $clip['rate'];
        }
        if ($export && $total > 179.5) {
            throw new VideoLibraryException('Raccourcis ton montage à moins de 3 minutes avant de l’exporter.');
        }
    }

    private function capacity(User $owner, ?VideoEdit $edit = null): void
    {
        $usage = $this->videos->usage($owner);
        $episode = $edit ? $this->em->getRepository(AiEpisode::class)->findOneBy(['assembly' => $edit]) : null;
        $reserved = $this->episodeReservations->slots($owner, $episode, null, true);
        if ($usage['count'] + $reserved >= VideoLibrary::MAX_VIDEOS || $usage['bytes'] + ($reserved + 1) * VideoLibrary::MAX_BYTES > VideoLibrary::MAX_STORAGE) {
            throw new VideoLibraryException('Libère une place et 100 Mo dans ta bibliothèque avant l’export.');
        }
    }

    /** @param list<int> $ids
     * @param list<int> $durations
     */
    public function assembleEpisode(AiEpisode $episode, array $ids, array $durations = []): void
    {
        $this->em->getConnection()->transactional(function () use ($episode, $ids, $durations): void {
            $owner = $episode->getOwner();
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
            $project = $episode->getAssembly();
            if (!$project) {
                if (!$ids || count($ids) > 8 || count(array_unique($ids)) !== count($ids)) {
                    throw new VideoLibraryException('Les plans à assembler sont indisponibles.');
                }
                if ($this->em->getRepository(VideoEdit::class)->count(['owner' => $owner]) >= 50) {
                    throw new VideoLibraryException('La limite de 50 projets de montage est atteinte.');
                }
                $clips = [];
                foreach ($ids as $index => $id) {
                    $video = $this->videos->owned($id, $owner) ?? throw new VideoLibraryException('Un plan est indisponible.');
                    $clips[] = ['id' => $id, 'start' => 0.0, 'end' => min(round($video->getDuration(), 3), $durations[$index] ?? $video->getDuration())];
                }
                $this->validate($owner, $clips);
                $project = new VideoEdit($owner, $episode->getTitle(), $clips);
                $format = $episode->getFlowRecipe()['ratio'];
                if ('auto' === $format) {
                    $first = $this->videos->owned($ids[0], $owner);
                    $format = $first->getWidth() === $first->getHeight() ? '1:1' : ($first->getWidth() > $first->getHeight() ? '16:9' : '9:16');
                }
                $project->revise($episode->getTitle(), $format, $clips);
                $montage = SeriesMontage::normalize($episode->getFlowRecipe()['montage'] ?? []);
                $project->configure(array_replace($project->getSettings(), $montage, ['sourceVolume' => $episode->isMutePlans() ? 0 : $montage['sourceVolume']]));
                $this->em->persist($project);
                $episode->assembleWith($project);
                $this->em->flush();
            }
            if (in_array($project->getStatus(), ['ready', 'queued', 'rendering'], true)) {
                return;
            }
            if ($this->em->getRepository(VideoEdit::class)->count(['owner' => $owner, 'status' => ['queued', 'rendering', 'transcribing']]) > 0) {
                throw new VideoLibraryException('Un autre montage est en cours. Réessaie après sa fin.');
            }
            $this->capacity($owner, $project);
            $project->progress('queued');
            $episode->flow('assembling');
            $this->em->flush();
            $this->bus->dispatch(new RenderVideoEdit((int) $project->getId()));
        });
    }

    public function remove(VideoEdit $project): void
    {
        $this->em->wrapInTransaction(function () use ($project): void {
            $this->em->lock($project->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($project, LockMode::PESSIMISTIC_WRITE);
            if ($project->isActive()) {
                throw new VideoLibraryException('Attends la fin de l’export avant de retirer ce projet.');
            }
            $this->em->remove($project);
        });
        foreach ($project->getMusicFiles() as $file) {
            $this->music->discard($file['name']);
        }
    }

    public function attachMusic(VideoEdit $project, ?UploadedFile $file, bool $rights, ?string $removeId = null): void
    {
        if ($file && !$rights) {
            throw new VideoLibraryException('Confirme que tu disposes des droits pour utiliser cette musique sur les réseaux choisis.');
        }
        if ($project->isActive() || 'ready' === $project->getStatus()) {
            throw new VideoLibraryException('Attends la fin du traitement ou modifie une copie.');
        }
        $name = $file ? $this->music->import($file) : null;
        $discard = [];
        try {
            $duration = $name ? $this->music->duration($name) : 0.0;
            $this->em->wrapInTransaction(function () use ($project, $file, $name, $duration, $removeId, &$discard): void {
                $this->em->lock($project->getOwner(), LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($project, LockMode::PESSIMISTIC_WRITE);
                if ($project->isActive() || 'ready' === $project->getStatus()) {
                    throw new VideoLibraryException('Ce montage est déjà en cours de traitement.');
                }
                $files = $project->getMusicFiles();
                $audio = $project->getAudioClips();
                if ($name) {
                    if (count($files) >= 8 || count($audio) >= 32) {
                        throw new VideoLibraryException('8 fichiers musicaux et 32 fragments audio maximum.');
                    }
                    $key = 'music-'.bin2hex(random_bytes(8));
                    $title = mb_substr($file->getClientOriginalName(), 0, 120);
                    $files[$key] = ['name' => $name, 'title' => $title, 'duration' => $duration];
                    $audio[] = ['id' => bin2hex(random_bytes(12)), 'kind' => 'music', 'musicId' => $key, 'source' => 0, 'start' => 0.0, 'end' => $project->getDuration(), 'offset' => 0.0, 'volume' => 20.0, 'loop' => true, 'rate' => 1.0];
                } else {
                    foreach ($files as $key => $stored) {
                        if (null === $removeId || $key === $removeId) {
                            $discard[] = $stored['name'];
                            unset($files[$key]);
                        }
                    }
                    $audio = array_values(array_filter($audio, static fn (array $row): bool => 'music' !== $row['kind'] || isset($files[$row['musicId'] ?? 'original'])));
                }
                $project->setMusicFiles($files);
                $last = $files ? end($files) : null;
                $project->music($last['name'] ?? null, $last['title'] ?? null);
                $project->configure(array_replace($project->getSettings(), ['musicOffset' => 0]));
                $project->setAudioClips($audio);
            });
        } catch (\Throwable $error) {
            $this->music->discard($name);
            throw $error;
        }
        foreach ($discard as $old) {
            $this->music->discard($old);
        }
    }

    public function process(int $id): void
    {
        $db = $this->em->getConnection();
        if (!$db->fetchOne('SELECT pg_try_advisory_lock(85403, ?)', [$id])) {
            // Another worker is still rendering this project. Messenger will retry.
            throw new \RuntimeException('Export déjà pris en charge.');
        }
        $video = null;
        $path = null;
        try {
            $project = $this->em->find(VideoEdit::class, $id);
            if (!$project) {
                return;
            }
            $this->em->refresh($project);
            if (!in_array($project->getStatus(), ['queued', 'rendering'], true)) {
                return;
            }
            $this->em->wrapInTransaction(function () use ($project): void {
                $this->em->lock($project->getOwner(), LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($project, LockMode::PESSIMISTIC_WRITE);
                $this->validate($project->getOwner(), $project->getClips());
                $this->capacity($project->getOwner(), $project);
                $project->progress('rendering');
            });
            $path = $this->renderer->render($project);
            $this->em->wrapInTransaction(function () use ($project, $path, &$video): void {
                $video = $this->library->import($project->getOwner(), new UploadedFile($path, 'montage.mp4', null, null, true), $project->getTitle(), $this->em->getRepository(AiEpisode::class)->findOneBy(['assembly' => $project]));
                $project->complete($video);
            });
        } catch (\Throwable $error) {
            if ($video) {
                $this->storage->discard($video->getStorageName());
            }
            if (!$this->em->isOpen()) {
                $manager = $this->registry->resetManager();
                if (!$manager instanceof EntityManagerInterface) {
                    throw new \LogicException('Gestionnaire indisponible.');
                }
                $this->em = $manager;
            }
            $project = $this->em->find(VideoEdit::class, $id);
            if ($project && $project->isActive()) {
                $project->progress('failed', $error instanceof VideoLibraryException ? $error->getMessage() : 'L’export n’a pas abouti. Tes clips et tes réglages sont conservés. Réessaie ou raccourcis le montage.');
                $this->em->flush();
            }
        } finally {
            if ($path) {
                $this->renderer->discard($path);
            }
            $db->executeQuery('SELECT pg_advisory_unlock(85403, ?)', [$id]);
        }
    }
}
