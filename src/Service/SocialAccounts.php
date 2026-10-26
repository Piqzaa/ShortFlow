<?php

namespace App\Service;

use App\Entity\InstagramConnection;
use App\Entity\TikTokConnection;
use App\Entity\User;
use App\Entity\YouTubeConnection;
use Doctrine\ORM\EntityManagerInterface;

final class SocialAccounts
{
    private const ENTITIES = ['youtube' => YouTubeConnection::class, 'instagram' => InstagramConnection::class, 'tiktok' => TikTokConnection::class];
    private const IDENTITIES = ['youtube' => 'channelId', 'instagram' => 'instagramId', 'tiktok' => 'openId'];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return list<YouTubeConnection|InstagramConnection|TikTokConnection> */
    public function all(User $owner, string $network): array
    {
        return $this->em->getRepository(self::ENTITIES[$network])->findBy(['owner' => $owner], ['id' => 'ASC']);
    }

    public function find(User $owner, string $network, ?string $identity = null): YouTubeConnection|InstagramConnection|TikTokConnection|null
    {
        if (null !== $identity) {
            return '' === $identity ? null : $this->em->getRepository(self::ENTITIES[$network])->findOneBy(['owner' => $owner, self::IDENTITIES[$network] => $identity]);
        }
        $connections = $this->all($owner, $network);

        return 1 === count($connections) ? $connections[0] : null;
    }

    public static function identity(YouTubeConnection|InstagramConnection|TikTokConnection $connection): string
    {
        return match (true) {
            $connection instanceof YouTubeConnection => $connection->getChannelId(),
            $connection instanceof InstagramConnection => $connection->getInstagramId(),
            $connection instanceof TikTokConnection => $connection->getOpenId(),
        };
    }

    public static function label(YouTubeConnection|InstagramConnection|TikTokConnection $connection): string
    {
        return match (true) {
            $connection instanceof YouTubeConnection => $connection->getChannelTitle(),
            $connection instanceof InstagramConnection => '@'.$connection->getUsername(),
            $connection instanceof TikTokConnection => $connection->getDisplayName(),
        };
    }

    /** @return array<string,array<string,string>> */
    public function choices(User $owner): array
    {
        $choices = [];
        foreach (array_keys(self::ENTITIES) as $network) {
            $choices[$network] = [];
            foreach ($this->all($owner, $network) as $connection) {
                $identity = self::identity($connection);
                $choices[$network][self::label($connection).' · '.$identity] = $identity;
            }
        }

        return $choices;
    }

    /** @return array<string,?string> */
    public function defaults(User $owner): array
    {
        $result = [];
        foreach (array_keys(self::ENTITIES) as $network) {
            $connection = $this->find($owner, $network);
            $result[$network] = $connection ? self::identity($connection) : null;
        }

        return $result;
    }

    /** @param list<string> $networks @param array<string,?string> $input
     * @return array<string,?string>
     */
    public function select(User $owner, array $networks, array $input, bool $allowMissing = true): array
    {
        $result = [];
        foreach ($networks as $network) {
            $identity = $input[$network] ?? null;
            $connection = $this->find($owner, $network, array_key_exists($network, $input) ? ($identity ?? '') : null);
            if ($identity && !$connection) {
                throw new PublicationException('Le compte choisi est indisponible ou ne t’appartient pas. Choisis un compte connecté.');
            }
            if (!$connection && !$allowMissing) {
                throw new PublicationException('Choisis le compte destinataire pour chaque réseau.');
            }
            $result[$network] = $connection ? self::identity($connection) : null;
        }

        return $result;
    }
}
