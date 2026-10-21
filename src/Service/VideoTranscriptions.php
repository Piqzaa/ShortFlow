<?php

namespace App\Service;

use App\Entity\VideoEdit;
use App\Message\TranscribeVideoEdit;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final class VideoTranscriptions
{
    public function __construct(private EntityManagerInterface $em, private VideoEdits $edits, private EditAudio $audio, private SpeechTranscriber $speech, private EditCaptions $captions, private MessageBusInterface $bus, #[Autowire('%env(bool:LOCAL_CAPTIONS_ENABLED)%')] private bool $enabled)
    {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function queue(VideoEdit $project): void
    {
        if (!$this->enabled) {
            throw new VideoLibraryException('Les sous-titres locaux attendent leur activation. Le montage reste disponible.');
        }
        $this->edits->validate($project->getOwner(), $project->getClips());
        $this->em->wrapInTransaction(function () use ($project): void {
            $this->em->lock($project->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($project, LockMode::PESSIMISTIC_WRITE);
            if ('transcribing' === $project->getStatus()) {
                return;
            }
            if ($project->isActive() || 'ready' === $project->getStatus() || $this->em->getRepository(VideoEdit::class)->count(['owner' => $project->getOwner(), 'status' => ['queued', 'rendering', 'transcribing']]) > 0) {
                throw new VideoLibraryException('Attends la fin du traitement en cours avant de créer les sous-titres.');
            }
            $this->edits->validate($project->getOwner(), $project->getClips());
            $project->progress('transcribing');
            $this->em->flush();
            $this->bus->dispatch(new TranscribeVideoEdit((int) $project->getId()));
        });
    }

    public function process(int $id): void
    {
        $db = $this->em->getConnection();
        if (!$db->fetchOne('SELECT pg_try_advisory_lock(85403, ?)', [$id])) {
            throw new \RuntimeException('Projet déjà en cours de traitement.');
        }
        $path = null;
        try {
            $project = $this->em->find(VideoEdit::class, $id);
            if (!$project) {
                return;
            }
            $this->em->refresh($project);
            if ('transcribing' !== $project->getStatus()) {
                return;
            }
            if (!$this->enabled) {
                throw new VideoLibraryException('La transcription locale est désactivée. Tes réglages sont conservés.');
            }
            $this->edits->validate($project->getOwner(), $project->getClips());
            $path = $this->audio->extract($project);
            $rows = $this->captions->validate($this->speech->transcribe($path, $project->getSettings()['language'] ?? 'fr'), $project->getDuration());
            $project->caption($rows);
            $settings = $project->getSettings();
            if ($rows && 'off' === $settings['captionStyle']) {
                $settings['captionStyle'] = 'clean';
                $project->configure($settings);
            }
            $project->progress('draft', $rows ? null : 'Aucune parole détectée. Vérifie le son ou ajoute ton texte manuellement.');
            $this->em->flush();
        } catch (\Throwable $error) {
            if (isset($project) && 'transcribing' === $project->getStatus()) {
                $project->progress('failed', $error instanceof VideoLibraryException ? $error->getMessage() : 'La transcription locale a échoué. Tes sources et ton montage restent conservés.');
                $this->em->flush();
            }
        } finally {
            if ($path) {
                $this->audio->discard($path);
            }
            $db->executeQuery('SELECT pg_advisory_unlock(85403, ?)', [$id]);
        }
    }
}
