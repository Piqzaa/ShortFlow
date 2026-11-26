<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class StripeBillingEvent
{
    #[ORM\Id]
    #[ORM\Column(length: 96)]
    private string $id;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    public function __construct(string $id, \DateTimeImmutable $receivedAt)
    {
        $this->id = $id;
        $this->receivedAt = $receivedAt;
    }
}
