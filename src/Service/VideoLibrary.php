<?php

namespace App\Service;

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

    public function __construct(private EntityManagerInterface $em, private VideoAssetRepository $videos, private VideoProbe $probe, private PrivateVideoStorage $storage)
    {
    }

    public function import(User $owner, UploadedFile $file, ?string $title): VideoAsset
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
        try {
            $this->em->wrapInTransaction(function () use ($owner, $file, $name, $video): void {
                $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
                $usage = $this->videos->usage($owner);
                if ($usage['count'] >= self::MAX_VIDEOS || $usage['bytes'] + $video->getByteSize() > self::MAX_STORAGE) {
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
        $trash = null;
        try {
            $this->em->wrapInTransaction(function () use ($video, &$trash): void {
                $this->em->lock($video->getOwner(), LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($video, LockMode::PESSIMISTIC_WRITE);
                $trash = $this->storage->stageDeletion($video->getStorageName());
                $this->em->remove($video);
            });
        } catch (\Throwable $error) {
            if (null !== $trash) {
                $this->storage->restore($trash, $video->getStorageName());
            }
            if ($error instanceof VideoLibraryException) {
                throw $error;
            }
            throw new VideoLibraryException('La suppression n’a pas abouti. Réessaie plus tard.');
        }
        if (null !== $trash) {
            $this->storage->purge($trash);
        }
    }
}
