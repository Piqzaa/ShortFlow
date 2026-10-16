<?php

namespace App\Message;

final readonly class PublishTikTok
{
    public function __construct(public int $id)
    {
    }
}
