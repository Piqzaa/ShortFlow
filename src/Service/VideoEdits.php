<?php

namespace App\Service;

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
    public function __construct(private EntityManagerInterface $em, private VideoAssetRepository $videos, private PrivateVideoStorage $storage, private VideoLibrary $library, private VideoEditRenderer $renderer, private MessageBusInterface $bus, private ManagerRegistry $registry, private EditCaptions $captionTools, private EditMusic $music, private CaptionTiming $timing, private EditTextLayers $textLayers, private EditAudioClips $audioClips)
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
        if ('' === $title || mb_strlen($title) > 120 || !isset(VideoEdit::FORMATS[$format]) || count($input) < 1 || count($input) > 8) {
            throw new VideoLibraryException('Indique un nom, un format et entre 1 et 8 clips.');
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
            $clips[] = ['id' => (int) $row['id'], 'start' => (float) $row['start'], 'end' => (float) $row['end']] + (null !== $origin ? ['origin' => $origin] : []);
        }
        $this->validate($project->getOwner(), $clips, $export);
        $settings ??= $project->getSettings();
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
        $duration = array_sum(array_map(static fn (array $c): float => $c['end'] - $c['start'], $clips));
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

    /** @param list<array{id:int,start:float,end:float,origin?:string}> $clips */
    public function validate(User $owner, array $clips, bool $export = true): void
    {
        $total = 0.0;
        foreach ($clips as $clip) {
            $video = $this->videos->owned($clip['id'], $owner);
            if (!$video || !is_file($this->storage->path($video->getStorageName()))) {
                throw new VideoLibraryException('Un clip source a été retiré ou est indisponible. Retire-le du montage ou choisis une autre vidéo.');
            }
            if (!is_finite($clip['start']) || !is_finite($clip['end']) || $clip['start'] < 0 || $clip['end'] > $video->getDuration() + 0.001 || $clip['end'] - $clip['start'] < 0.1) {
                throw new VideoLibraryException('Chaque clip doit durer au moins 0,1 seconde et rester dans les limites de sa source.');
            }
            $total += $clip['end'] - $clip['start'];
        }
        if ($export && $total > 179.5) {
            throw new VideoLibraryException('Raccourcis ton montage à moins de 3 minutes avant de l’exporter.');
        }
    }

    private function capacity(User $owner): void
    {
        $usage = $this->videos->usage($owner);
        if ($usage['count'] >= VideoLibrary::MAX_VIDEOS || $usage['bytes'] + VideoLibrary::MAX_BYTES > VideoLibrary::MAX_STORAGE) {
            throw new VideoLibraryException('Libère une place et 100 Mo dans ta bibliothèque avant l’export.');
        }
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
        $this->music->discard($project->getMusicName());
    }

    public function attachMusic(VideoEdit $project, ?UploadedFile $file, bool $rights): void
    {
        if ($file && !$rights) {
            throw new VideoLibraryException('Confirme que tu disposes des droits pour utiliser cette musique sur les réseaux choisis.');
        }
        if ($project->isActive() || 'ready' === $project->getStatus()) {
            throw new VideoLibraryException('Attends la fin du traitement ou modifie une copie.');
        }
        $name = $file ? $this->music->import($file) : null;
        $old = null;
        try {
            $this->em->wrapInTransaction(function () use ($project, $file, $name, &$old): void {
                $this->em->lock($project->getOwner(), LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($project, LockMode::PESSIMISTIC_WRITE);
                if ($project->isActive() || 'ready' === $project->getStatus()) {
                    throw new VideoLibraryException('Ce montage est déjà en cours de traitement.');
                }
                $old = $project->getMusicName();
                $project->music($name, $file ? mb_substr($file->getClientOriginalName(), 0, 120) : null);
                $project->configure(array_replace($project->getSettings(), ['musicOffset' => 0]));
                $audio = array_values(array_filter($project->getAudioClips(), static fn (array $row): bool => 'music' !== $row['kind']));
                if ($name) {
                    $audio[] = ['id' => bin2hex(random_bytes(12)), 'kind' => 'music', 'source' => 0, 'start' => 0.0, 'end' => $project->getDuration(), 'offset' => 0.0, 'volume' => 20.0, 'loop' => true];
                }
                $project->setAudioClips($audio);
            });
        } catch (\Throwable $error) {
            $this->music->discard($name);
            throw $error;
        }
        $this->music->discard($old);
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
                $this->capacity($project->getOwner());
                $project->progress('rendering');
            });
            $path = $this->renderer->render($project);
            $this->em->wrapInTransaction(function () use ($project, $path, &$video): void {
                $video = $this->library->import($project->getOwner(), new UploadedFile($path, 'montage.mp4', null, null, true), $project->getTitle());
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
