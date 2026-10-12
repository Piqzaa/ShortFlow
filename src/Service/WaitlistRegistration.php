<?php

namespace App\Service;

use App\Entity\WaitlistEntry;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class WaitlistRegistration
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function register(string $email): void
    {
        $email = strtolower(trim($email));
        if ($this->entityManager->getRepository(WaitlistEntry::class)->findOneBy(['email' => $email])) {
            return;
        }
        $this->entityManager->persist(new WaitlistEntry($email));
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) { /* Concurrent duplicate: same neutral response. */
        }
    }
}
