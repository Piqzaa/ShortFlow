<?php

namespace App\Message;

final readonly class TranscribeVideoEdit
{
    public function __construct(public int $id)
    {
    }
}
