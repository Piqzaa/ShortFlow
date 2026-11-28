<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\PublishedVideoTrace;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Repository\VideoAssetRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ExceptionInterface;

final class VideoLibrary
{
    public const MAX_BYTES = 104857600;
    public const BYTES_PER_GIB = 1073741824;
    public const MAX_VIDEOS = 100;
    public const FREE_BYTES = 500_000_000;
    public const PAID_BYTES = 3_000_000_000;
    public const TEMPORARY_VIDEOS = 10;
    public const RETENTION_PERIOD = '+7 days';

    public function __construct(private EntityManagerInterface $em, private VideoAssetRepository $videos, private VideoProbe $probe, private PrivateVideoStorage $storage, private EpisodeReservations $episodeReservations, private ClockInterface $clock)
    {
    }

    public function storageLimitBytes(User $owner): int
    {
        $paidUntil = $owner->getPaidUntil();

        return $paidUntil && $paidUntil > $this->clock->now() ? self::PAID_BYTES : self::FREE_BYTES;
    }

    /** @return array{count: int, bytes: int, limit: int, label: string, paid: bool, warning: bool, full: bool, over: int} */
    public function storageStatus(User $owner): array
    {
        $usage = $this->videos->usage($owner, false);
        $limit = $this->storageLimitBytes($owner);
        $paid = self::PAID_BYTES === $limit;

        return $usage + [
            'limit' => $limit, 'label' => $paid ? '3 Go' : '500 Mo', 'paid' => $paid,
            'warning' => $usage['bytes'] >= ($paid ? 2_500_000_000 : 400_000_000) || $usage['count'] >= 90,
            'full' => $usage['bytes'] >= $limit || $usage['count'] >= self::MAX_VIDEOS,
            'over' => max(0, $usage['bytes'] - $limit),
        ];
    }

    public function import(User $owner, UploadedFile $file, ?string $title, ?AiEpisode $completingEpisode = null, ?AiGeneration $completingGeneration = null, bool $temporary = false, ?AiEpisode $assemblyEpisode = null): VideoAsset
    {
        if (!$file->isValid() || $file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
            throw new VideoLibraryException('Choisis une vidéo valide de 100 Mo maximum.');
        }
        $info = $this->probe->inspect($file);
        if ($completingGeneration && ($extension = $completingGeneration->getInputSettings()['extension'] ?? null)) {
            $expected = $extension['inputSeconds'] + $completingGeneration->getSeconds();
            if (abs($info['duration'] - $expected) > 0.6 || !$info['height'] || abs($info['width'] / $info['height'] - ('9:16' === $completingGeneration->getRatio() ? 9 / 16 : 16 / 9)) > 0.03) {
                throw new VideoLibraryException('Le résultat de l’extension ne correspond pas à la durée complète ou au format confirmés.');
            }
        }
        $name = bin2hex(random_bytes(24)).'.'.$info['extension'];
        $title = trim($title ?? '');
        if ('' === $title) {
            $title = pathinfo(basename(str_replace('\\', '/', $file->getClientOriginalName())), PATHINFO_FILENAME);
            $title = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $title) ?? 'Ma vidéo');
        }
        $title = mb_substr('' === $title ? 'Ma vidéo' : $title, 0, 120);
        $video = new VideoAsset($owner, $title, $name, $info['mime'], (int) $file->getSize(), $info['duration'], $info['width'], $info['height'], $info['codec']);
        $video->recordTechnicalMetadata($info['technical']);
        if ($temporary) {
            $video->markTemporary();
        }
        if ($assemblyEpisode) {
            if ($assemblyEpisode->getOwner()->getId() !== $owner->getId()) {
                throw new VideoLibraryException('Plan indisponible.');
            }
            $video->useForAssembly($assemblyEpisode);
        }
        try {
            $this->em->wrapInTransaction(function () use ($owner, $file, $name, $video, $completingEpisode, $completingGeneration): void {
                $this->em->refresh($owner, LockMode::PESSIMISTIC_WRITE);
                if ($completingEpisode && $completingEpisode->getOwner()->getId() !== $owner->getId()) {
                    throw new VideoLibraryException('Résultat indisponible.');
                }
                if ($completingGeneration && $completingGeneration->getOwner()->getId() !== $owner->getId()) {
                    throw new VideoLibraryException('Génération indisponible.');
                }
                if ($video->isTemporary()) {
                    if ($this->videos->usage($owner, true)['count'] >= self::TEMPORARY_VIDEOS) {
                        throw new VideoLibraryException('Tes 10 places d’import temporaire sont occupées. Supprime un fichier temporaire dont tu n’as plus besoin avant de réessayer.');
                    }
                } else {
                    $this->assertLibraryCapacity($owner, $video->getByteSize(), $completingEpisode, $completingGeneration);
                }
                $this->storage->store($file, $name);
                $this->em->persist($video);
            });
        } catch (\Throwable $error) {
            $this->storage->discard($name);
            if ($error instanceof VideoLibraryException) {
                throw $error;
            }
            throw new VideoLibraryException('L’import est indisponible. Réessaie plus tard.');
        }

        return $video;
    }

    public function rename(VideoAsset $video, string $title): void
    {
        try {
            $this->em->wrapInTransaction(function () use ($video, $title): void {
                $this->em->lock($video->getOwner(), LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($video, LockMode::PESSIMISTIC_WRITE);
                $video->rename($title);
            });
        } catch (\Throwable) {
            throw new VideoLibraryException('Le nom n’a pas pu être modifié. Réessaie plus tard.');
        }
    }

    public function delete(VideoAsset $video): void
    {
        $this->deleteMany([$video]);
    }

    /** @param list<VideoAsset> $videos */
    public function deleteMany(array $videos): void
    {
        $this->remove($videos);
    }

    public function keep(VideoAsset $video): void
    {
        $this->em->getConnection()->transactional(function () use ($video): void {
            $this->em->refresh($video->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($video, LockMode::PESSIMISTIC_WRITE);
            if ($video->isTemporary()) {
                $this->assertLibraryCapacity($video->getOwner(), $video->getByteSize());
                $video->keep();
                $this->em->flush();
            }
        });
    }

    private function assertLibraryCapacity(User $owner, int $bytes, ?AiEpisode $episode = null, ?AiGeneration $generation = null): void
    {
        $usage = $this->videos->usage($owner, false);
        $reserved = $this->episodeReservations->slots($owner, $episode, $generation, true);
        $limit = ($episode || $generation) && $owner->getPaidUntil() ? self::PAID_BYTES : $this->storageLimitBytes($owner);
        if ($usage['count'] + $reserved >= self::MAX_VIDEOS || $usage['bytes'] + $reserved * self::MAX_BYTES + $bytes > $limit) {
            throw new VideoLibraryException('Ta bibliothèque a atteint sa limite ou n’a plus assez de place pour ce fichier. Libère de la place pour continuer.');
        }
    }

    public function checkRetention(VideoAsset $video, \DateTimeImmutable $now): bool
    {
        return $this->remove([$video], $now);
    }

    public function removeAssembledClip(VideoAsset $video): bool
    {
        return $this->remove([$video], assembledClip: true);
    }

    /** @param list<VideoAsset> $videos */
    private function remove(array $videos, ?\DateTimeImmutable $retentionCheck = null, bool $assembledClip = false): bool
    {
        if (!$videos || count($videos) > 20) {
            throw new VideoLibraryException('Sélectionne entre 1 et 20 vidéos.');
        }
        $owner = $videos[0]->getOwner();
        $staged = [];
        try {
            $removed = $this->em->wrapInTransaction(function () use ($videos, $owner, &$staged, $retentionCheck, $assembledClip): bool {
                $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
                foreach ($videos as $video) {
                    if ($video->getOwner()->getId() !== $owner->getId()) {
                        throw new VideoLibraryException('Sélection indisponible.');
                    }
                    $this->em->refresh($video, LockMode::PESSIMISTIC_WRITE);
                    if ($retentionCheck && !$this->retentionExpired($video, $retentionCheck)) {
                        return false;
                    }
                    if ($assembledClip) {
                        $output = $this->videos->assembledClipOutput((int) $video->getId());
                        if (null === $output || !is_file($this->storage->path($output))) {
                            return false;
                        }
                    }
                    $extensionUse = $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM ai_generation WHERE owner_id = ? AND input_settings->'extension'->>'sourceId' = ? AND status IN ('prepared', 'queued', 'submitting', 'processing', 'waiting_key', 'review')", [$owner->getId(), (string) $video->getId()]);
                    if ($extensionUse) {
                        throw new VideoLibraryException('Cette vidéo est la source d’une extension préparée ou en cours. Retire sa préparation avant de supprimer la source. Aucune vidéo supprimée.');
                    }
                    $episodeUse = $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM ai_shot s JOIN ai_episode e ON e.id = s.episode_id JOIN ai_generation g ON g.id = s.generation_id WHERE g.video_id = ? AND e.flow_state IN ('running', 'assembling', 'paused')", [$video->getId()]);
                    if ($episodeUse) {
                        throw new VideoLibraryException('Ce clip est utilisé par un épisode en cours ou suspendu. Termine cet épisode avant de le retirer. Aucune vidéo supprimée.');
                    }
                    foreach ($this->em->getRepository(\App\Entity\VideoEdit::class)->findBy(['owner' => $owner, 'status' => ['queued', 'rendering', 'transcribing']]) as $edit) {
                        if (in_array($video->getId(), array_column($edit->getClips(), 'id'), true)) {
                            $operation = 'transcribing' === $edit->getStatus() ? 'la création des sous-titres' : 'un export de montage';
                            throw new VideoLibraryException('Une vidéo sélectionnée est utilisée par '.$operation.' en cours. Attends sa fin. Aucune vidéo supprimée.');
                        }
                    }
                    $active = $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM youtube_publication WHERE source_id = ? AND status IN ('queued', 'uploading', 'processing', 'paused')", [$video->getId()]);
                    $instagramActive = $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM instagram_publication WHERE source_id = ? AND status IN ('queued', 'processing', 'publishing', 'paused', 'review')", [$video->getId()]);
                    $tiktokActive = $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM tiktok_publication WHERE source_id = ? AND status IN ('queued', 'processing', 'publishing', 'paused', 'review')", [$video->getId()]);
                    $scheduled = $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM publication_draft_item i JOIN publication_draft d ON d.id = i.draft_id WHERE i.source_id = ? AND d.status = 'scheduled'", [$video->getId()]);
                    if ((int) $scheduled > 0) {
                        throw new VideoLibraryException('Une vidéo sélectionnée est programmée. Annule sa programmation avant de supprimer. Aucune vidéo supprimée.');
                    }
                    if ((int) $active + (int) $instagramActive + (int) $tiktokActive > 0) {
                        throw new VideoLibraryException('Une vidéo sélectionnée a un envoi en cours ou suspendu. Aucune vidéo supprimée : termine cet envoi ou retire-la de la sélection.');
                    }
                }
                foreach ($videos as $video) {
                    if ($retentionCheck) {
                        $this->em->persist(new PublishedVideoTrace($video, $this->publicationPoster($video), $retentionCheck));
                    }
                    if ($assembledClip) {
                        foreach ($this->em->getRepository(AiGeneration::class)->findBy(['video' => $video]) as $generation) {
                            $generation->detachAssembledClip();
                        }
                    }
                    $trash = $this->storage->stageDeletion($video->getStorageName());
                    if (null !== $trash) {
                        $staged[] = ['trash' => $trash, 'name' => $video->getStorageName()];
                    }
                    $this->em->remove($video);
                }

                return true;
            });
        } catch (\Throwable $error) {
            foreach ($staged as $file) {
                $this->storage->restore($file['trash'], $file['name']);
            }
            if ($error instanceof VideoLibraryException) {
                throw $error;
            }
            throw new VideoLibraryException('La suppression n’a pas abouti. Réessaie plus tard.', previous: $error);
        }
        foreach ($staged as $file) {
            $this->storage->purge($file['trash']);
        }

        return $removed;
    }

    private function publicationPoster(VideoAsset $video): ?string
    {
        try {
            $path = $this->storage->path($video->getStorageName());
            $process = new Process(['ffmpeg', '-nostdin', '-v', 'error', '-max_alloc', '67108864', '-protocol_whitelist', 'file,pipe', '-threads', '1', '-i', $path, '-map', '0:v:0', '-frames:v', '1', '-vf', 'scale=320:320:force_original_aspect_ratio=decrease', '-map_metadata', '-1', '-c:v', 'mjpeg', '-q:v', '5', '-threads', '1', '-f', 'image2pipe', 'pipe:1'], timeout: 5);
            if (0 === $process->run() && '' !== ($poster = $process->getOutput()) && strlen($poster) <= 49152) {
                return base64_encode($poster);
            }
        } catch (ExceptionInterface|VideoLibraryException) {
            // The title still identifies the publication when the source cannot be decoded.
        }

        return null;
    }

    private function retentionExpired(VideoAsset $video, \DateTimeImmutable $now): bool
    {
        if (!$video->isTemporary()) {
            return false;
        }
        $lastSuccess = $this->videos->lastSuccessfulPublicationIfUnprotected((int) $video->getId());
        if (!$lastSuccess) {
            $video->setRetentionDueAt(null);

            return false;
        }
        $due = $video->getRetentionDueAt();
        if (!$due || $due < $lastSuccess->modify(self::RETENTION_PERIOD)) {
            $video->setRetentionDueAt(max($now, $lastSuccess)->modify(self::RETENTION_PERIOD));

            return false;
        }

        return $due <= $now;
    }
}
