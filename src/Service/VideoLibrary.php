<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Repository\VideoAssetRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class VideoLibrary
{
    public const MAX_BYTES = 104857600;
    public const MAX_STORAGE = 1073741824;
    public const MAX_VIDEOS = 100;

    public function __construct(private EntityManagerInterface $em, private VideoAssetRepository $videos, private VideoProbe $probe, private PrivateVideoStorage $storage, private EpisodeReservations $episodeReservations)
    {
    }

    public function import(User $owner, UploadedFile $file, ?string $title, ?AiEpisode $completingEpisode = null, ?AiGeneration $completingGeneration = null): VideoAsset
    {
        if (!$file->isValid() || $file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
            throw new VideoLibraryException('Choisis une vidéo valide de 100 Mo maximum.');
        }
        $info = $this->probe->inspect($file);
        $name = bin2hex(random_bytes(24)).'.'.$info['extension'];
        $title = trim($title ?? '');
        if ('' === $title) {
            $title = pathinfo(basename(str_replace('\\', '/', $file->getClientOriginalName())), PATHINFO_FILENAME);
            $title = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $title) ?? 'Ma vidéo');
        }
        $title = mb_substr('' === $title ? 'Ma vidéo' : $title, 0, 120);
        $video = new VideoAsset($owner, $title, $name, $info['mime'], (int) $file->getSize(), $info['duration'], $info['width'], $info['height'], $info['codec']);
        $video->recordTechnicalMetadata($info['technical']);
        try {
            $this->em->wrapInTransaction(function () use ($owner, $file, $name, $video, $completingEpisode, $completingGeneration): void {
                $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
                $usage = $this->videos->usage($owner);
                if ($completingEpisode && $completingEpisode->getOwner()->getId() !== $owner->getId()) {
                    throw new VideoLibraryException('Résultat indisponible.');
                }
                if ($completingGeneration && $completingGeneration->getOwner()->getId() !== $owner->getId()) {
                    throw new VideoLibraryException('Génération indisponible.');
                }
                $reserved = $this->episodeReservations->slots($owner, $completingEpisode, $completingGeneration, true);
                if ($usage['count'] + $reserved >= self::MAX_VIDEOS || $usage['bytes'] + $reserved * self::MAX_BYTES + $video->getByteSize() > self::MAX_STORAGE) {
                    throw new VideoLibraryException('Ta bibliothèque a atteint sa limite : 100 vidéos ou 1 Go. Supprime une vidéo pour libérer de la place.');
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
        if (!$videos || count($videos) > 20) {
            throw new VideoLibraryException('Sélectionne entre 1 et 20 vidéos.');
        }
        $owner = $videos[0]->getOwner();
        $staged = [];
        try {
            $this->em->wrapInTransaction(function () use ($videos, $owner, &$staged): void {
                $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
                foreach ($videos as $video) {
                    if ($video->getOwner()->getId() !== $owner->getId()) {
                        throw new VideoLibraryException('Sélection indisponible.');
                    }
                    $this->em->refresh($video, LockMode::PESSIMISTIC_WRITE);
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
                    $trash = $this->storage->stageDeletion($video->getStorageName());
                    if (null !== $trash) {
                        $staged[] = ['trash' => $trash, 'name' => $video->getStorageName()];
                    }
                    $this->em->remove($video);
                }
            });
        } catch (\Throwable $error) {
            foreach ($staged as $file) {
                $this->storage->restore($file['trash'], $file['name']);
            }
            if ($error instanceof VideoLibraryException) {
                throw $error;
            }
            throw new VideoLibraryException('La suppression n’a pas abouti. Réessaie plus tard.');
        }
        foreach ($staged as $file) {
            $this->storage->purge($file['trash']);
        }
    }
}
