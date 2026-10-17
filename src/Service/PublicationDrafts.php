<?php

namespace App\Service;

use App\Entity\PublicationDraft;
use App\Entity\PublicationDraftItem;
use App\Entity\User;
use App\Form\MultiNetworkPrepareType;
use App\Message\DispatchScheduledPublication;
use App\Repository\PublicationDraftRepository;
use App\Repository\VideoAssetRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class PublicationDrafts
{
    public function __construct(private ManagerRegistry $registry, private PublicationDraftRepository $drafts, private VideoAssetRepository $videos, private PublicationLock $lock, private MultiNetworkPublishing $publishing, private FormFactoryInterface $forms, private ClockInterface $clock, private PublicationTime $time, private MessageBusInterface $bus, #[Autowire(service: 'limiter.publication_draft')] private RateLimiterFactory $limiter)
    {
    }

    private function em(): EntityManagerInterface
    {
        $em = $this->registry->getManager();
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException('ORM required.');
        }

        return $em;
    }

    /** @param list<int> $ids @param list<string> $networks @param array<string, array<string, mixed>> $content @param array<string, ?string> $accounts */
    public function save(User $owner, array $ids, array $networks, array $content, array $accounts, string $title, ?int $id = null, ?int $revision = null): PublicationDraft
    {
        if (!$this->limiter->create('draft-'.$owner->getId())->consume()->isAccepted()) {
            throw new PublicationException('Trop de sauvegardes rapprochées. Réessaie dans une minute.');
        }
        if (!$ids || count($ids) > 20 || count(array_unique($ids)) !== count($ids) || array_diff($networks, array_keys(MultiNetworkPublishing::LABELS)) || count(array_unique($networks)) !== count($networks) || (in_array('tiktok', $networks, true) && count($ids) > 5) || mb_strlen($title) > 120) {
            throw new PublicationException('Vérifie le titre, les vidéos et les réseaux du brouillon.');
        }

        return $this->lock->run((int) $owner->getId(), function () use ($owner, $ids, $networks, $content, $accounts, $title, $id, $revision): PublicationDraft {
            return $this->em()->getConnection()->transactional(function () use ($owner, $ids, $networks, $content, $accounts, $title, $id, $revision): PublicationDraft {
                $this->em()->lock($owner, LockMode::PESSIMISTIC_WRITE);
                $selected = [];
                foreach ($ids as $videoId) {
                    $video = $this->videos->owned($videoId, $owner) ?? throw new PublicationException('Une vidéo sélectionnée est indisponible.');
                    $this->em()->refresh($video, LockMode::PESSIMISTIC_WRITE);
                    $selected[] = $video;
                }
                $knownContent = array_intersect_key($content, MultiNetworkPublishing::LABELS);
                foreach ($networks as $network) {
                    $knownContent[$network] ??= ['videos' => []];
                }
                // Static choices validate stored values, never grant account permissions or populate the UI.
                $structuralCreator = ['privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'], 'comment_disabled' => false, 'duet_disabled' => false, 'stitch_disabled' => false];
                $normalized = $this->validate($knownContent, $selected, array_keys($knownContent), $structuralCreator, true);
                $draft = null === $id ? new PublicationDraft($owner, $title ?: $selected[0]->getTitle(), $this->clock->now()) : $this->editable($owner, $id, $revision);
                $draft->edit($title ?: $selected[0]->getTitle(), $networks, array_intersect_key($accounts, array_flip($networks)), $this->clock->now());
                $previous = [];
                foreach ($draft->getItems() as $item) {
                    $previous[$item->getVideoId()] = $item->getContent();
                    $draft->removeItem($item);
                }
                foreach ($selected as $position => $video) {
                    $item = new PublicationDraftItem($draft, $video, $position);
                    $rowContent = $previous[$video->getId()] ?? [];
                    foreach ($normalized as $network => $rows) {
                        $rowContent[$network] = $rows['videos'][$video->getId()];
                    }
                    $item->setContent($rowContent);
                    $draft->addItem($item);
                }
                $this->em()->persist($draft);
                $this->em()->flush();

                return $draft;
            });
        });
    }

    /** @param array<string, array<string, mixed>> $content @param list<\App\Entity\VideoAsset> $videos @param list<string> $networks @param array<string, mixed> $creator @return array<string, array<string, mixed>> */
    private function validate(array $content, array $videos, array $networks, array $creator, bool $partial): array
    {
        $form = $this->forms->create(MultiNetworkPrepareType::class, null, ['videos' => $videos, 'networks' => $networks, 'creator' => $creator, 'draft_key' => 'stored', 'partial' => $partial, 'csrf_protection' => false]);
        $form->submit($content + ['draft_key' => 'stored']);
        if (!$form->isValid()) {
            throw new PublicationException('Vérifie les textes et réglages : un champ est invalide ou incomplet.');
        }

        return $form->getData();
    }

    public function editable(User $owner, int $id, ?int $revision): PublicationDraft
    {
        $draft = $this->drafts->owned($id, $owner) ?? throw new PublicationException('Cette préparation est indisponible.');
        $this->em()->refresh($draft, LockMode::PESSIMISTIC_WRITE);
        if ($draft->getRevision() !== $revision || 'draft' !== $draft->getStatus()) {
            throw new PublicationException('Cette préparation a changé ou est programmée. Recharge-la avant de modifier.');
        }

        return $draft;
    }

    /** @return array<string, mixed> */
    public function context(PublicationDraft $draft): array
    {
        $missing = [];
        foreach ($draft->getItems() as $item) {
            if (!$item->getSource() || $item->getSource()->getOwner()->getId() !== $draft->getOwner()->getId()) {
                $missing[] = $item->getTitle().' : la vidéo source est indisponible. Modifie la sélection.';
            }
        }
        if ($missing || $draft->getItems()->isEmpty()) {
            return ['videos' => [], 'destinations' => $this->publishing->destinations($draft->getOwner()), 'creator' => [], 'ready' => false, 'issues' => ['source' => $missing ?: ['Choisis au moins une vidéo.']]];
        }

        return $this->publishing->context($draft->getOwner(), $draft->videoIds(), $draft->getNetworks(), $draft->getAccounts(), $draft->content());
    }

    /** @return array<string, mixed> */
    public function review(PublicationDraft $draft): array
    {
        $context = $this->context($draft);
        if ($context['ready']) {
            try {
                $this->validate(array_intersect_key($draft->content(), array_flip($draft->getNetworks())), $context['videos'], $draft->getNetworks(), $context['creator'], false);
            } catch (PublicationException $error) {
                $context['ready'] = false;
                $context['issues']['source'][] = $error->getMessage().' Reviens aux textes et réglages.';
            }
        }

        return $context;
    }

    public function plan(User $owner, int $id, int $revision, ?string $local, string $zone): void
    {
        $date = null === $local ? null : $this->time->resolve($local, $zone);
        if ($date) {
            $this->time->validateFuture($date);
        }
        $this->mutate($owner, $id, $revision, function (PublicationDraft $draft) use ($date, $zone, $local): void {
            if (!in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
                throw new PublicationException('Fuseau invalide.');
            }
            $draft->plan($date, $zone, $local, $this->clock->now());
        });
    }

    public function confirm(User $owner, int $id, int $revision, bool $scheduled): void
    {
        $this->mutate($owner, $id, $revision, function (PublicationDraft $draft) use ($scheduled): void {
            $this->checkComplete($draft);
            if ($scheduled) {
                $date = $draft->getScheduledAt() ?? throw new PublicationException('Choisis un créneau avant de programmer.');
                $this->time->validateFuture($date);
                $draft->arm($this->clock->now());
                $this->em()->flush();
                $this->wake($draft);
            } else {
                $draft->plan(null, $draft->getTimezone(), null, $this->clock->now());
                $draft->beginImmediate($this->clock->now());
                $this->engage($draft, false);
            }
        });
    }

    private function checkComplete(PublicationDraft $draft): void
    {
        foreach ($draft->getItems() as $item) {
            if ($item->getSource()) {
                $this->em()->refresh($item->getSource(), LockMode::PESSIMISTIC_WRITE);
            }
        }
        $context = $this->context($draft);
        if (!$context['ready']) {
            throw new PublicationException('Vérifie les vidéos, les comptes et les réglages de toutes les destinations.');
        }
        $content = array_intersect_key($draft->content(), array_flip($draft->getNetworks()));
        $this->validate($content, $context['videos'], $draft->getNetworks(), $context['creator'], false);
    }

    private function engage(PublicationDraft $draft, bool $scheduled): void
    {
        $guard = function () use ($draft, $scheduled): void {
            if ($scheduled && $draft->getScheduledAt()) {
                $this->time->assertOnTime($draft->getScheduledAt());
            }
        };
        $guard();
        $this->publishing->enqueue($draft->getOwner(), $draft->videoIds(), $draft->getNetworks(), $draft->content(), (string) $draft->getSubmissionKey(), $draft->getAccounts(), $guard);
        $guard();
        $draft->dispatched($this->clock->now());
        $this->em()->flush();
    }

    public function wake(PublicationDraft $draft): void
    {
        $date = $draft->getScheduledAt();
        if ('scheduled' !== $draft->getStatus() || !$date) {
            throw new PublicationException('Cette préparation n’est pas programmée.');
        }
        $this->time->assertOnTime($date);
        $delay = max(0, ($date->getTimestamp() - $this->clock->now()->getTimestamp()) * 1000);
        $this->bus->dispatch(new DispatchScheduledPublication((int) $draft->getId(), $draft->getRevision()), [new DelayStamp($delay)]);
    }

    public function recover(int $id): void
    {
        $draft = $this->drafts->find($id) ?? throw new PublicationException('Préparation indisponible.');
        $this->lock->run((int) $draft->getOwner()->getId(), function () use ($draft): void {
            $this->em()->getConnection()->transactional(function () use ($draft): void {
                $this->em()->lock($draft->getOwner(), LockMode::PESSIMISTIC_WRITE);
                $this->em()->refresh($draft, LockMode::PESSIMISTIC_WRITE);
                $this->wake($draft);
            });
        });
    }

    public function dispatchDue(int $id, int $revision): void
    {
        $draft = $this->drafts->find($id);
        if (!$draft) {
            return;
        }
        $ownerId = (int) $draft->getOwner()->getId();
        $entered = false;
        try {
            $this->lock->run($ownerId, function () use ($id, $revision, &$entered): void {
                $entered = true;
                $this->em()->getConnection()->transactional(function () use ($id, $revision): void {
                    $draft = $this->drafts->find($id);
                    if (!$draft) {
                        return;
                    }
                    $this->em()->lock($draft->getOwner(), LockMode::PESSIMISTIC_WRITE);
                    $this->em()->refresh($draft, LockMode::PESSIMISTIC_WRITE);
                    if ('scheduled' !== $draft->getStatus() || $draft->getRevision() !== $revision || !$draft->getScheduledAt()) {
                        return;
                    }
                    if ($this->clock->now() < $draft->getScheduledAt()) {
                        $this->wake($draft);

                        return;
                    }
                    $this->time->assertOnTime($draft->getScheduledAt());
                    $this->checkComplete($draft);
                    $this->engage($draft, true);
                });
            });
        } catch (\Throwable $error) {
            // Lock contention is transient: Messenger retries without changing the preparation.
            if (!$entered) {
                throw $error;
            }
            if (!$error instanceof PublicationException) {
                $notice = 'Le départ est indisponible. Vérifie la préparation puis choisis un nouveau créneau.';
            } else {
                $notice = $error->getMessage();
            }
            $this->registry->resetManager();
            $this->lock->run($ownerId, function () use ($id, $revision, $notice): void {
                $this->em()->getConnection()->transactional(function () use ($id, $revision, $notice): void {
                    $draft = $this->drafts->find($id);
                    if (!$draft) {
                        return;
                    }
                    $this->em()->lock($draft->getOwner(), LockMode::PESSIMISTIC_WRITE);
                    $this->em()->refresh($draft, LockMode::PESSIMISTIC_WRITE);
                    if ('scheduled' === $draft->getStatus() && $draft->getRevision() === $revision) {
                        $draft->change('blocked', $notice, $this->clock->now());
                        $this->em()->flush();
                    }
                });
            });
        }
    }

    public function transition(User $owner, int $id, int $revision, string $action): void
    {
        $this->lock->run((int) $owner->getId(), function () use ($owner, $id, $revision, $action): void {
            $this->em()->getConnection()->transactional(function () use ($owner, $id, $revision, $action): void {
                $this->em()->lock($owner, LockMode::PESSIMISTIC_WRITE);
                $draft = $this->drafts->owned($id, $owner) ?? throw new PublicationException('Préparation indisponible.');
                $this->em()->refresh($draft, LockMode::PESSIMISTIC_WRITE);
                if ($revision !== $draft->getRevision() || 'dispatched' === $draft->getStatus()) {
                    throw new PublicationException('Le départ est déjà engagé ou la préparation a changé. Consulte son suivi.');
                }
                if ('delete' === $action) {
                    if (!in_array($draft->getStatus(), ['draft', 'cancelled'], true)) {
                        throw new PublicationException('Annule la programmation avant de supprimer.');
                    }
                    $this->em()->remove($draft);
                } elseif ('cancel' === $action) {
                    $draft->change('cancelled', 'Programmation annulée avant le départ.', $this->clock->now());
                } elseif ('edit' === $action) {
                    $draft->change('draft', null, $this->clock->now());
                } else {
                    throw new PublicationException('Action indisponible.');
                }
                $this->em()->flush();
            });
        });
    }

    private function mutate(User $owner, int $id, int $revision, callable $action): void
    {
        $this->lock->run((int) $owner->getId(), function () use ($owner, $id, $revision, $action): void {
            $this->em()->getConnection()->transactional(function () use ($owner, $id, $revision, $action): void {
                $this->em()->lock($owner, LockMode::PESSIMISTIC_WRITE);
                $draft = $this->editable($owner, $id, $revision);
                $action($draft);
                $this->em()->flush();
            });
        });
    }
}
