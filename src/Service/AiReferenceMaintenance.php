<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AiReferenceMaintenance
{
    public function __construct(private EntityManagerInterface $em, private AiReferenceStorage $storage, #[Autowire('%session.save_path%')] private ?string $sessionPath)
    {
    }

    /** @return list<string> */
    private function sessionReferences(): array
    {
        $path = $this->sessionPath ?: (string) ini_get('session.save_path');
        $path = '' === $path ? sys_get_temp_dir() : substr($path, strrpos(';'.$path, ';'));
        // Refuse unknown/non-local handlers or unreadable sessions instead of deleting blindly.
        if (str_contains($path, '://') || !is_dir($path) || !is_readable($path) || is_link($path)) {
            throw new \RuntimeException('Les sessions locales doivent être lisibles avant cet entretien. Aucune image supprimée.');
        }
        $names = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink() || !str_starts_with($file->getFilename(), 'sess_')) {
                continue;
            }
            if (!$file->isReadable() || $file->getSize() > 2097152) {
                throw new \RuntimeException('Une session ne peut pas être vérifiée. Aucune image supprimée.');
            }
            $raw = file_get_contents($file->getPathname());
            if (false === $raw) {
                throw new \RuntimeException('Une session ne peut pas être lue. Aucune image supprimée.');
            }
            preg_match_all('/[a-f0-9]{48}\.jpg/', $raw, $matches);
            array_push($names, ...$matches[0]);
        }

        return array_values(array_unique($names));
    }

    /** @return array{files:int,bytes:int} */
    public function inspect(User $owner, bool $apply = false): array
    {
        return $this->em->getConnection()->transactional(function () use ($owner, $apply): array {
            // Uploads and new preparations use this same lock.
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $keep = $this->sessionReferences();
            $queries = [
                'SELECT row_to_json(g)::text FROM ai_generation g WHERE g.owner_id = :owner',
                'SELECT row_to_json(a)::text FROM ai_series a WHERE a.owner_id = :owner',
                'SELECT row_to_json(e)::text FROM ai_episode e LEFT JOIN ai_series a ON a.id = e.series_id WHERE COALESCE(e.owner_id,a.owner_id) = :owner',
                'SELECT row_to_json(p)::text FROM ai_shot p JOIN ai_episode e ON e.id = p.episode_id LEFT JOIN ai_series a ON a.id = e.series_id WHERE COALESCE(e.owner_id,a.owner_id) = :owner',
                'SELECT row_to_json(r)::text FROM series_recurrence r JOIN ai_series a ON a.id = r.series_id WHERE a.owner_id = :owner',
                'SELECT row_to_json(p)::text FROM story_proposal p WHERE p.owner_id = :owner',
            ];
            foreach ($queries as $sql) {
                foreach ($this->em->getConnection()->iterateColumn($sql, ['owner' => $owner->getId()]) as $raw) {
                    preg_match_all('/[a-f0-9]{48}\.jpg/', (string) $raw, $matches);
                    array_push($keep, ...$matches[0]);
                }
            }

            return $this->storage->sweep($owner, array_values(array_unique($keep)), new \DateTimeImmutable('-30 days'), $apply);
        });
    }
}
