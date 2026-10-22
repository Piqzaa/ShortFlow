<?php

namespace App\Service;

use App\Entity\VideoAsset;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PublicationVideoCheck
{
    /** @var array<string, array<string, mixed>> */
    private array $legacyMetadata = [];

    public function __construct(private VideoProbe $probe, private PrivateVideoStorage $storage)
    {
    }

    public function validate(VideoAsset $video, string $network, int $maxDuration = 600): void
    {
        $label = MultiNetworkPublishing::LABELS[$network] ?? throw new \InvalidArgumentException('Réseau inconnu.');
        $label = 'instagram' === $network ? 'Instagram' : $label;
        $fix = ' Ouvre le montage et exporte une copie MP4 verticale à 30 images/s, puis remplace la vidéo dans cette préparation.';
        if ('youtube' !== $network && ('video/mp4' !== $video->getMimeType() || 'h264' !== $video->getCodec())) {
            $format = 'video/webm' === $video->getMimeType() ? 'WebM' : $video->getMimeType();
            throw new PublicationException('Format incompatible avec '.$label.' : '.$format.' / '.strtoupper($video->getCodec()).'. Exporte cette vidéo en MP4 / H.264 depuis le montage ou un autre outil, puis importe la copie dans la bibliothèque si nécessaire et remplace la source de cette préparation.');
        }
        $minimum = 'youtube' === $network ? 0.0 : 3.0;
        $maximum = 'tiktok' === $network ? min(180, $maxDuration) : 180;
        if ($video->getDuration() <= 0 || $video->getDuration() < $minimum || $video->getDuration() > $maximum) {
            $durationIssue = $video->getDuration() < $minimum ? '3 secondes minimum' : ('youtube' === $network ? '3 minutes maximum' : '3 à '.$maximum.' secondes');
            throw new PublicationException('Pour '.$label.' : durée de '.$durationIssue.'.'.$fix);
        }
        $metadata = $this->metadata($video);
        $rotation = (float) ($metadata['rotation'] ?? 0);
        if (!is_finite($rotation) || abs($rotation / 90 - round($rotation / 90)) > 0.001) {
            throw new PublicationException('Rotation vidéo incompatible avec '.$label.'.'.$fix);
        }
        $width = $video->getWidth() * ($metadata['pixelRatio'] ?? 1.0);
        $height = (float) $video->getHeight();
        if (1 === abs((int) round($rotation / 90)) % 2) {
            [$width, $height] = [$height, $width];
        }
        if ('youtube' === $network) {
            if ($width > $height) {
                throw new PublicationException('Pour YouTube Shorts : choisis une vidéo carrée ou verticale. Cette vidéo est horizontale.'.$fix);
            }

            return;
        }
        $fps = $metadata['fps'] ?? null;
        if (!is_numeric($fps) || !is_finite((float) $fps) || $fps < 23 || $fps > 60) {
            throw new PublicationException('Pour '.$label.' : cadence de 23 à 60 images/s requise'.(is_numeric($fps) ? ' (vidéo : '.round((float) $fps, 2).')' : ', cadence de cette vidéo illisible').'.'.$fix);
        }
        if ('tiktok' === $network) {
            if (min($width, $height) < 360 || max($width, $height) > 4096) {
                throw new PublicationException('Pour TikTok : dimensions de 360 à 4096 pixels par côté.'.$fix);
            }

            return;
        }
        if ($width > 1920 || $width / $height < 0.01 || $width / $height > 10) {
            throw new PublicationException('Pour Instagram : largeur de 1920 pixels maximum et proportions de 0,01:1 à 10:1.'.$fix);
        }
        $bitrate = $metadata['videoBitrate'] ?? null;
        if (!is_numeric($bitrate) || $bitrate <= 0 || $bitrate > 25000000) {
            throw new PublicationException('Pour Instagram : débit vidéo lisible de 25 Mbit/s maximum requis.'.$fix);
        }
        if (($metadata['interlaced'] ?? false) || (null !== ($metadata['audioCodec'] ?? null) && ('aac' !== $metadata['audioCodec'] || ($metadata['audioSampleRate'] ?? 0) < 1 || $metadata['audioSampleRate'] > 48000 || !in_array($metadata['audioChannels'] ?? null, [1, 2], true)))) {
            throw new PublicationException('Pour Instagram : vidéo progressive et audio AAC mono ou stéréo à 48 kHz maximum.'.$fix);
        }
    }

    /** @return array<string, mixed> */
    private function metadata(VideoAsset $video): array
    {
        if (null !== $video->getTechnicalMetadata()) {
            return $video->getTechnicalMetadata();
        }
        // Old assets stay usable without a bulk rewrite or a database mutation on GET.
        $name = $video->getStorageName();
        if (isset($this->legacyMetadata[$name])) {
            return $this->legacyMetadata[$name];
        }
        try {
            $info = $this->probe->inspect(new UploadedFile($this->storage->path($name), 'source', null, null, true));
        } catch (\Throwable) {
            throw new PublicationException('La vidéo ne peut pas être vérifiée. Vérifie sa présence dans la bibliothèque ou importe une nouvelle copie avant de publier.');
        }

        return $this->legacyMetadata[$name] = $info['technical'];
    }
}
