<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class PublicationLock
{
    public function __construct(private Connection $db)
    {
    }

    public function run(int $ownerId, callable $action): mixed
    {
        if (!$this->db->fetchOne('SELECT pg_try_advisory_lock(85402, ?)', [$ownerId])) {
            throw new PublicationException('Un envoi ou une connexion est en cours. Réessaie dans quelques instants.');
        }
        try {
            return $action();
        } finally {
            $this->db->executeQuery('SELECT pg_advisory_unlock(85402, ?)', [$ownerId]);
        }
    }
}
