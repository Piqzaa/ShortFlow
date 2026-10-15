<?php

namespace App\Message;

final readonly class PublishYouTube
{
    public function __construct(public int $publicationId)
    {
    }
}
