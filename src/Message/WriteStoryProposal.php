<?php

namespace App\Message;

final readonly class WriteStoryProposal
{
    public function __construct(public string $id)
    {
    }
}
