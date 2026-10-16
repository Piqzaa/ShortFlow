<?php

namespace App\Message;

final readonly class PublishInstagram
{
    public function __construct(public int $id)
    {
    }
}
