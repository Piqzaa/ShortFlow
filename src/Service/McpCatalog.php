<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\VideoAsset;
use App\Repository\VideoAssetRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class McpCatalog
{
    public function __construct(private VideoAssetRepository $videos, private SocialAccounts $accounts, private PublicationDrafts $drafts, private UrlGeneratorInterface $urls, #[Autowire('%env(APP_PUBLIC_URL)%')] private string $publicUrl)
    {
    }

    /** @return array{videos: list<array{id: int|null, title: string, durationSeconds: float}>, page: int, pages: int} */
    public function videos(User $owner, string $search, int $page): array
    {
        $results = $this->videos->search($owner, mb_substr(trim($search), 0, 120), $page);

        return [
            'videos' => array_map(static fn (VideoAsset $video): array => [
                'id' => $video->getId(), 'title' => $video->getTitle(), 'durationSeconds' => $video->getDuration(),
            ], $results['items']),
            'page' => $results['page'],
            'pages' => $results['pages'],
        ];
    }

    /** @return list<array{network: string, accountId: int|null, label: string}> */
    public function accounts(User $owner): array
    {
        $results = [];
        foreach (array_keys(MultiNetworkPublishing::LABELS) as $network) {
            foreach ($this->accounts->all($owner, $network) as $account) {
                $results[] = ['network' => $network, 'accountId' => $account->getId(), 'label' => SocialAccounts::label($account)];
            }
        }

        return $results;
    }

    /** @return array{url: string, video: string} */
    public function preparation(User $owner, int $videoId, ?string $date): array
    {
        $video = $this->videos->owned($videoId, $owner) ?? throw new \InvalidArgumentException('Vidéo indisponible.');
        $parameters = ['videos' => [$videoId], 'zone' => $owner->getTimezone()];
        if (null !== $date) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new \InvalidArgumentException('Indique une date au format AAAA-MM-JJ.');
            }
            $parameters['date'] = $date;
        }

        return ['url' => rtrim($this->publicUrl, '/').$this->urls->generate('app_composer_start', $parameters), 'video' => $video->getTitle()];
    }

    /** @return array{url: string, status: string} */
    public function draft(User $owner, int $videoId, string $network, int $accountId, string $title, string $text, string $hashtags): array
    {
        if (!isset(MultiNetworkPublishing::LABELS[$network])) {
            throw new PublicationException('Choisis YouTube, Instagram ou TikTok.');
        }
        foreach ($this->accounts->all($owner, $network) as $account) {
            if ($account->getId() !== $accountId) {
                continue;
            }
            $caption = trim(trim($text)."\n\n".trim($hashtags));
            $row = 'youtube' === $network ? ['title' => $title, 'description' => $caption] : ['caption' => $caption];
            $draft = $this->drafts->save($owner, [$videoId], [$network], [$network => ['videos' => [$videoId => $row]]], [$network => SocialAccounts::identity($account)], $title);

            return ['url' => rtrim($this->publicUrl, '/').$this->urls->generate('app_composer_start', ['draft' => $draft->getId()]), 'status' => 'draft'];
        }

        throw new PublicationException('Compte destinataire indisponible.');
    }
}
