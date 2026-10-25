<?php

namespace App\Service;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Entity\VideoAsset;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class NativeExtensions
{
    public function __construct(private EntityManagerInterface $em, private PrivateVideoStorage $storage, private VideoProbe $probe, private NativeVideoPreview $contracts)
    {
    }

    /** @return array<string,mixed> */
    private function source(VideoAsset $video, int $context): array
    {
        $path = $this->storage->path($video->getStorageName());
        if (!is_file($path) || filesize($path) > 11000000 || 'video/mp4' !== $video->getMimeType()) {
            throw new \RuntimeException('Pour cette extension, choisis un MP4 H.264 de 11 Mo maximum.');
        }
        $info = $this->probe->inspect(new UploadedFile($path, 'source.mp4', null, null, true));
        if (0.0 !== (float) ($info['technical']['rotation'] ?? 0) || abs((float) ($info['technical']['pixelRatio'] ?? 1) - 1) > 0.01) {
            throw new \RuntimeException('Exporte d’abord cette vidéo sans rotation ni pixels anamorphiques.');
        }

        return ['sourceId' => $video->getId(), 'digest' => hash_file('sha256', $path), 'inputSeconds' => $info['duration'], 'fps' => (float) ($info['technical']['fps'] ?? 0), 'width' => $info['width'], 'height' => $info['height'], 'context' => $context];
    }

    public function prepare(User $owner, VideoAsset $video, int $seconds, int $context, string $prompt): AiGeneration
    {
        return $this->em->getConnection()->transactional(function () use ($owner, $video, $seconds, $context, $prompt): AiGeneration {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            if ($video->getOwner()->getId() !== $owner->getId()) {
                throw new \RuntimeException('Vidéo indisponible.');
            }
            $source = $this->source($video, $context);
            $uri = $this->uri($video);
            $preview = $this->contracts->extension($uri, $source['inputSeconds'], $source['fps'], $source['width'], $source['height'], $seconds, $context, $prompt);
            $job = new AiGeneration($owner, mb_substr($video->getTitle().' · prolongement', 0, 120), trim($prompt), 'ltx-23-extend', $seconds, $preview['outputRatio'], $preview['cents']);
            $job->attachExtension($source);
            $this->em->persist($job);
            $this->em->flush();

            return $job;
        });
    }

    /** @return array<string,mixed> */
    public function input(AiGeneration $job): array
    {
        $source = $job->getInputSettings()['extension'] ?? throw new \RuntimeException('La source vidéo manque : prépare une nouvelle extension.');
        $video = $this->em->getRepository(VideoAsset::class)->findOneBy(['id' => $source['sourceId'], 'owner' => $job->getOwner()]) ?? throw new \RuntimeException('La source vidéo est indisponible.');
        $current = $this->source($video, $source['context']);
        if ($source != $current) {
            throw new \RuntimeException('La source vidéo a changé. Prépare et confirme un nouveau devis.');
        }

        return $this->contracts->extension($this->uri($video), $source['inputSeconds'], $source['fps'], $source['width'], $source['height'], $job->getSeconds(), $source['context'], $job->getPrompt())['input'];
    }

    private function uri(VideoAsset $video): string
    {
        $bytes = file_get_contents($this->storage->path($video->getStorageName()));
        if (false === $bytes) {
            throw new \RuntimeException('La source vidéo est indisponible.');
        }

        return 'data:video/mp4;base64,'.base64_encode($bytes);
    }

    public function estimate(AiGeneration $job): int
    {
        $source = $job->getInputSettings()['extension'] ?? throw new \RuntimeException('Contexte vidéo manquant.');
        $fps = (float) $source['fps'];
        if ($fps <= 0 || !is_finite($fps)) {
            throw new \RuntimeException('Fréquence vidéo invalide.');
        }

        return (int) ceil(ceil(($source['context'] + $job->getSeconds()) * $fps) / $fps * 10);
    }
}
