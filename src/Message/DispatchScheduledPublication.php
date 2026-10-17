<?php

namespace App\Message;

final readonly class DispatchScheduledPublication
{
    public function __construct(public int $id, public int $revision)
    {
    }
}
