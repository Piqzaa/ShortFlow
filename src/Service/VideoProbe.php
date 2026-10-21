<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class VideoProbe
{
    /** @return array{mime: string, extension: string, duration: float, width: int, height: int, codec: string} */
    public function inspect(UploadedFile $file): array
    {
        $process = new Process(['ffprobe', '-v', 'error', '-max_alloc', '33554432', '-protocol_whitelist', 'file', '-format_whitelist', 'mov,matroska,webm', '-enable_drefs', '0', '-probesize', '5000000', '-analyzeduration', '5000000', '-show_entries', 'format=format_name,duration:stream=codec_type,codec_name,width,height,pix_fmt:stream_disposition=attached_pic', '-of', 'json', '-i', $file->getPathname()]);
        $process->setTimeout(10);
        try {
            $process->mustRun();
            $data = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new VideoLibraryException('Impossible de lire cette vidéo. Choisis un MP4 H.264 ou un WebM VP8/VP9/AV1 valide.');
        }
        $streams = $data['streams'] ?? [];
        $videos = array_values(array_filter($streams, static fn (array $stream): bool => 'video' === ($stream['codec_type'] ?? '') && 0 === ($stream['disposition']['attached_pic'] ?? 0)));
        $audio = array_values(array_filter($streams, static fn (array $stream): bool => 'audio' === ($stream['codec_type'] ?? '')));
        $format = $data['format']['format_name'] ?? '';
        $mp4 = in_array('mp4', explode(',', $format), true);
        $webm = in_array('webm', explode(',', $format), true);
        $mime = $file->getMimeType();
        if ((!$mp4 && !$webm) || ($mp4 && 'video/mp4' !== $mime) || ($webm && 'video/webm' !== $mime) || 1 !== count($videos) || count($audio) > 1 || count($streams) !== count($videos) + count($audio)) {
            throw new VideoLibraryException('Choisis une vidéo MP4 ou WebM avec une seule piste vidéo et au maximum une piste audio.');
        }
        $video = $videos[0];
        $codec = $video['codec_name'] ?? '';
        $allowed = $mp4 ? ['h264'] : ['vp8', 'vp9', 'av1'];
        if (!in_array($codec, $allowed, true) || !in_array($video['pix_fmt'] ?? '', ['yuv420p', 'yuvj420p'], true) || ($audio && !in_array($audio[0]['codec_name'] ?? '', $mp4 ? ['aac'] : ['opus', 'vorbis'], true))) {
            throw new VideoLibraryException('Pour la lecture, exporte en MP4 H.264 8 bits avec audio AAC, ou WebM VP8/VP9/AV1 8 bits avec audio Opus/Vorbis.');
        }
        $duration = (float) ($data['format']['duration'] ?? 0);
        $width = (int) ($video['width'] ?? 0);
        $height = (int) ($video['height'] ?? 0);
        if (is_finite($duration) && $duration > 180) {
            throw new VideoLibraryException('Cette vidéo dépasse 3 minutes. Raccourcis-la avant de l’importer (3 minutes maximum).');
        }
        if (!is_finite($duration) || $duration <= 0 || $duration > 180 || $width < 2 || $height < 2 || $width > 3840 || $height > 3840) {
            throw new VideoLibraryException('Limites de la bêta : 3 minutes maximum, dimensions de 2 à 3 840 pixels par côté.');
        }

        return ['mime' => $mp4 ? 'video/mp4' : 'video/webm', 'extension' => $mp4 ? 'mp4' : 'webm', 'duration' => $duration, 'width' => $width, 'height' => $height, 'codec' => $codec];
    }
}
