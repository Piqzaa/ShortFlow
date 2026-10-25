<?php

namespace App\Service;

use App\Entity\AiShot;
use App\Entity\PublicationDraft;
use App\Entity\StoryProposal;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class PublicationEditorial
{
    public function __construct(private EntityManagerInterface $em, private StoryProposals $jobs, private PublicationDrafts $drafts, private PublicationLock $lock, private PublicationTexts $texts, private LocalStoryWriter $writer, #[Autowire(service: 'limiter.story_proposal')] private RateLimiterFactory $limiter)
    {
    }

    /** @return array{source:array<string,mixed>,profile:array<string,string>,networks:list<string>,hash:string} */
    private function snapshot(PublicationDraft $draft): array
    {
        $episode = $draft->getEpisode();
        if ('draft' !== $draft->getStatus() || !$episode || 'ready' !== $episode->getFlowState() || !$episode->getApprovedVideo() || $episode->getOwner()->getId() !== $draft->getOwner()->getId() || $draft->videoIds() !== [$episode->getApprovedVideo()->getId()]) {
            throw new PublicationException('Les textes doivent être préparés depuis la version actuellement validée de l’épisode. Les anciens brouillons conservent leur vidéo et leurs textes.');
        }
        $networks = $draft->getNetworks();
        $this->texts->schema($networks);
        $profile = $this->texts->profile($episode->isStandalone() ? [] : $episode->getSeries()->getEditorialProfile());
        $shots = $this->em->getRepository(AiShot::class)->findBy(['episode' => $episode], ['position' => 'ASC']);
        $source = ['episode' => $episode->getId(), 'revision' => $episode->getRevision(), 'video' => $episode->getApprovedVideo()->getId(), 'title' => $episode->getTitle(), 'videoTitle' => $episode->getApprovedVideo()->getTitle(), 'scenes' => array_map(static fn (AiShot $shot): string => mb_substr($shot->getPrompt(), 0, 600), array_slice($shots, 0, 8)), 'description' => $draft->getEditorial()['brief'] ?? ''];

        return ['source' => $source, 'profile' => $profile, 'networks' => $networks, 'hash' => hash('sha256', serialize([$source, $profile, $networks, PublicationTexts::VERSION]))];
    }

    private function refresh(PublicationDraft $draft, User $owner): void
    {
        $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
        $this->em->refresh($draft, LockMode::PESSIMISTIC_WRITE);
        if ($draft->getOwner()->getId() !== $owner->getId() || 'draft' !== $draft->getStatus()) {
            throw new PublicationException('Ce brouillon est indisponible ou déjà programmé.');
        }
        if ($episode = $draft->getEpisode()) {
            $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
            if (!$episode->isStandalone()) {
                $this->em->refresh($episode->getSeries());
            }
        }
    }

    public function request(User $owner, PublicationDraft $draft, string $brief = ''): StoryProposal
    {
        if (!$this->writer->available()) {
            throw new PublicationException('La rédaction locale n’est pas activée. Tu peux rédiger tes textes directement.');
        }
        if (mb_strlen($brief) > 700) {
            throw new PublicationException('Décris cette version en 700 caractères maximum.');
        }
        if (!$this->limiter->create('story-'.$owner->getId())->consume()->isAccepted()) {
            throw new PublicationException('Plusieurs rédactions ont déjà été demandées. Réessaie dans quelques minutes ; tes textes restent conservés.');
        }

        return $this->em->getConnection()->transactional(function () use ($owner, $draft, $brief): StoryProposal {
            $this->refresh($draft, $owner);
            $metadata = $draft->getEditorial();
            $metadata['brief'] = trim($brief);
            $draft->setEditorial($metadata);
            $snapshot = $this->snapshot($draft);
            $baseline = [];
            foreach ($this->fields($draft) as $name => $field) {
                $baseline[$name] = hash('sha256', $field['value']);
            }
            $this->em->flush();

            return $this->jobs->create($owner, (int) $draft->getId(), $snapshot['hash'], ['source' => $snapshot['source'], 'profile' => $snapshot['profile'], 'networks' => $snapshot['networks'], 'baseline' => $baseline], 'editorial');
        });
    }

    /** @return array<string,array{value:string,origin:string,network:string,field:string}> */
    public function fields(PublicationDraft $draft): array
    {
        $fields = [];
        $content = $draft->content();
        $metadata = $draft->getEditorial();
        $video = $draft->videoIds()[0] ?? 0;
        foreach ($draft->getNetworks() as $network) {
            foreach (PublicationTexts::FIELDS[$network] ?? [] as $field) {
                $value = (string) ($content[$network]['videos'][$video][$field] ?? '');
                $human = $metadata['human'][$network][$field] ?? false;
                $generated = !$human && ($metadata['generated'][$network][$field] ?? null) === hash('sha256', $value);
                $fields[$network.'.'.$field] = ['value' => $value, 'origin' => $generated ? 'generated' : ($human ? 'human' : ('' !== $value ? 'existing' : 'empty')), 'network' => $network, 'field' => $field];
            }
        }

        return $fields;
    }

    /** @return array<string,mixed> */
    public function view(PublicationDraft $draft): array
    {
        $job = $this->jobs->latest($draft->getOwner(), (int) $draft->getId(), 'editorial');
        $view = ['job' => $job, 'fields' => $this->fields($draft), 'available' => $this->writer->available(), 'stale' => false, 'review' => false, 'notice' => null, 'brief' => $draft->getEditorial()['brief'] ?? '', 'texts' => []];
        try {
            $snapshot = $this->snapshot($draft);
            $view['stale'] = $job && !hash_equals($job->getFingerprint(), $snapshot['hash']);
            $view['review'] = isset($draft->getEditorial()['source']) && !hash_equals($draft->getEditorial()['source'], $snapshot['hash']);
            if ($job && 'ready' === $job->getStatus() && !$view['stale']) {
                $view['texts'] = $job->getResult()['texts'] ?? [];
            }
        } catch (PublicationException $error) {
            $view['notice'] = $error->getMessage();
            $view['available'] = false;
            $view['stale'] = true;
        }

        return $view;
    }

    /** @param array<mixed> $selected */
    public function apply(User $owner, PublicationDraft $draft, string $jobId, array $selected, bool $replaceChanged = false): PublicationDraft
    {
        return $this->lock->run((int) $owner->getId(), fn (): PublicationDraft => $this->em->getConnection()->transactional(function () use ($owner, $draft, $jobId, $selected, $replaceChanged): PublicationDraft {
            $this->refresh($draft, $owner);
            $snapshot = $this->snapshot($draft);
            $job = $this->jobs->owned($owner, $jobId, 'editorial');
            if (!$job || $job->getScope() !== $draft->getId() || 'ready' !== $job->getStatus() || !hash_equals($job->getFingerprint(), $snapshot['hash'])) {
                throw new PublicationException('Cette proposition ne correspond plus à la version validée, au profil ou aux réseaux. Demande une nouvelle proposition. Tes textes restent conservés.');
            }
            $fields = $this->fields($draft);
            if (!$selected || !array_is_list($selected) || count($selected) > 4 || array_filter($selected, static fn ($name): bool => !is_string($name)) || array_diff($selected, array_keys($fields))) {
                throw new PublicationException('Choisis les champs à appliquer dans l’aperçu.');
            }
            $result = $job->getResult();
            $content = $draft->content();
            $metadata = $draft->getEditorial();
            $video = $draft->videoIds()[0];
            foreach (array_unique($selected) as $name) {
                $field = $fields[$name];
                if (!$replaceChanged && ($result['baseline'][$name] ?? null) !== hash('sha256', $field['value'])) {
                    throw new PublicationException('Un champ coché a changé depuis la demande. Tes saisies sont enregistrées. Relis les choix puis autorise explicitement leur remplacement, ou garde ton texte.');
                }
                $value = $result['texts'][$field['network']][$field['field']] ?? null;
                if (!is_string($value)) {
                    throw new PublicationException('Cette proposition est incomplète. Tes textes restent conservés.');
                }
                $content[$field['network']]['videos'][$video][$field['field']] = $value;
                $metadata['generated'][$field['network']][$field['field']] = hash('sha256', $value);
                unset($metadata['human'][$field['network']][$field['field']]);
            }
            $saved = $this->drafts->save($owner, $draft->videoIds(), $draft->getNetworks(), $content, $draft->getAccounts(), $draft->getTitle(), $draft->getId(), $draft->getRevision());
            $saved->setEditorial($metadata + ['version' => PublicationTexts::VERSION]);
            $metadata = $saved->getEditorial();
            $metadata['source'] = $snapshot['hash'];
            $metadata['video'] = $video;
            $metadata['proposal'] = $job->getId();
            $metadata['profile'] = $snapshot['profile'];
            $saved->setEditorial($metadata);
            $this->em->flush();

            return $saved;
        }));
    }
}
