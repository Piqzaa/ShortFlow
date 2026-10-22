<?php

namespace App\Service;

use App\Message\BuildEditWaveform;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Process;

final class EditWaveforms
{
    public function __construct(private MessageBusInterface $bus, #[Autowire('%kernel.project_dir%/var/storage')] private string $root, #[Autowire('%kernel.project_dir%/var/storage/edit-waveforms/%kernel.environment%')] private string $directory)
    {
    }

    private function cache(string $path): string
    {
        $real = realpath($path);
        if (!$real || !str_starts_with($real, $this->root.'/') || is_link($path) || !is_file($real)) {
            throw new VideoLibraryException('Source sonore indisponible.');
        }
        if (is_link($this->directory) || (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory))) {
            throw new VideoLibraryException('Aperçu sonore indisponible.');
        }

        return $this->directory.'/'.hash('sha256', $real.':'.filemtime($real).':'.filesize($real)).'.json';
    }

    /** @return array<string,mixed> */
    public function read(string $path): array
    {
        $cache = $this->cache($path);
        $stored = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;
        if (is_array($stored) && ('pending' !== ($stored['status'] ?? '') || filemtime($cache) > time() - 180)) {
            return $stored;
        }
        $pending = ['status' => 'pending'];
        file_put_contents($cache, json_encode($pending), LOCK_EX);
        chmod($cache, 0640);
        $this->bus->dispatch(new BuildEditWaveform($path));

        return $pending;
    }

    public function build(string $path): void
    {
        $cache = $this->cache($path);
        try {
            $process = new Process(['ffmpeg', '-v', 'error', '-nostdin', '-threads', '1', '-protocol_whitelist', 'file', '-i', $path, '-t', '600', '-vn', '-ac', '1', '-ar', '800', '-c:a', 'pcm_f32le', '-f', 'f32le', '-']);
            $process->setTimeout(30);
            $process->mustRun();
            $samples = unpack('g*', $process->getOutput());
            $count = count($samples);
            $peaks = array_fill(0, 256, 0.0);
            foreach ($samples as $index => $sample) {
                if (is_finite($sample)) {
                    $bucket = min(255, (int) floor(($index - 1) * 256 / max(1, $count)));
                    $peaks[$bucket] = round(min(1, max($peaks[$bucket], abs($sample))), 4);
                }
            }
            $result = ['status' => 'ready', 'duration' => $count / 800, 'peaks' => $peaks];
        } catch (\Throwable) {
            $result = ['status' => 'unavailable'];
        }
        $temporary = $cache.'.'.bin2hex(random_bytes(4));
        file_put_contents($temporary, json_encode($result), LOCK_EX);
        chmod($temporary,0640);
        rename($temporary,$cache);
    }
}
