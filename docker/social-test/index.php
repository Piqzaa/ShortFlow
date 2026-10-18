<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

require_once dirname(__DIR__, 2).'/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    $kernel = new Kernel($context['APP_ENV'], false);
    $kernel->boot();
    $kernel->getContainer()->get('event_dispatcher')->addListener(KernelEvents::EXCEPTION, static function (ExceptionEvent $event): void {
        $error = $event->getThrowable();
        if ($error instanceof HttpExceptionInterface && $error->getStatusCode() < 500) {
            return;
        }
        $frames = [];
        foreach (array_slice($error->getTrace(), 0, 6) as $frame) {
            $frames[] = ['file' => $frame['file'] ?? '', 'line' => $frame['line'] ?? 0];
        }
        // Never record exception messages/arguments, requests, URLs, users or headers.
        $record = ['time' => gmdate('c'), 'class' => get_class($error), 'file' => $error->getFile(), 'line' => $error->getLine(), 'frames' => $frames];
        file_put_contents('/tmp/vydflow-social-errors.jsonl', json_encode($record, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
        @chmod('/tmp/vydflow-social-errors.jsonl', 0600);
    }, 256);

    return $kernel;
};
