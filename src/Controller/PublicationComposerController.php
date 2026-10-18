<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\MultiNetworkPrepareType;
use App\Form\PublicationComposerType;
use App\Repository\PublicationDraftRepository;
use App\Repository\VideoAssetRepository;
use App\Service\MultiNetworkPublishing;
use App\Service\PublicationDrafts;
use App\Service\PublicationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PublicationComposerController extends AbstractController
{
    #[Route('/studio/composer', name: 'app_composer_start', methods: ['GET'])]
    public function start(Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos, PublicationDraftRepository $drafts): Response
    {
        $stored = null;
        $ids = [];
        if ($request->query->has('draft')) {
            $stored = $drafts->owned($request->query->getInt('draft'), $user) ?? throw $this->createNotFoundException();
            if ('draft' !== $stored->getStatus()) {
                return $this->redirectToRoute('app_draft', ['id' => $stored->getId()], 303);
            }
            foreach ($stored->getItems() as $item) {
                if (!$item->getSource()) {
                    return $this->redirectToRoute('app_draft_sources', ['id' => $stored->getId()], 303);
                }
            }
            $ids = $stored->videoIds();
        } else {
            foreach ($request->query->all('videos') as $id) {
                if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) {
                    throw $this->createNotFoundException();
                }
                $ids[] = (int) $id;
            }
            $ids = array_values(array_unique($ids));
        }
        $date = $request->query->getString('date');
        $zone = $request->query->getString('zone', $user->getTimezone());
        if (!in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            throw $this->createNotFoundException();
        }
        if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            throw $this->createNotFoundException();
        }
        if (!$ids) {
            return $this->redirectToRoute('app_dashboard', array_filter(['choose' => 1, 'date' => $date, 'zone' => $zone]), 303);
        }
        if (count($ids) > 20) {
            throw $this->createNotFoundException();
        }
        foreach ($ids as $id) {
            $videos->owned($id, $user) ?? throw $this->createNotFoundException();
        }
        $key = bin2hex(random_bytes(24));
        $state = ['owner' => $user->getId(), 'ids' => $ids, 'networks' => $stored?->getNetworks() ?? [], 'accounts' => $stored?->getAccounts() ?? [], 'content' => $stored?->content() ?? [], 'title' => $stored?->getTitle() ?? '', 'revision' => bin2hex(random_bytes(16)), 'expires' => time() + 3600, 'persistentId' => $stored?->getId(), 'persistentRevision' => $stored?->getRevision(), 'mode' => ($stored?->getScheduledAt() || $date) ? 'scheduled' : 'immediate', 'local' => $stored?->getLocalTime() ?? ($date ? $date.'T12:00' : null), 'timezone' => $stored?->getTimezone() ?? $zone];
        $states = $request->getSession()->get('composer_workspaces', []);
        foreach ($states as $oldKey => $old) {
            if (($old['expires'] ?? 0) < time()) {
                unset($states[$oldKey]);
            }
        }
        $states[$key] = $state;
        $request->getSession()->set('composer_workspaces', array_slice($states, -10, null, true));

        return $this->redirectToRoute('app_composer', ['key' => $key], 303);
    }

    #[Route('/studio/composer/{key}', name: 'app_composer', requirements: ['key' => '[a-f0-9]{48}'], methods: ['GET', 'POST'])]
    public function compose(string $key, Request $request, #[CurrentUser] User $user, MultiNetworkPublishing $publishing, PublicationDrafts $drafts, PublicationDraftRepository $repository): Response
    {
        $state = $request->getSession()->get('composer_workspaces', [])[$key] ?? null;
        if (!is_array($state) || $state['owner'] !== $user->getId()) {
            throw $this->createNotFoundException();
        }
        if ($state['expires'] < time()) {
            throw new GoneHttpException('Cette préparation a expiré. Rouvre le brouillon ou choisis à nouveau tes vidéos.');
        }
        if ($state['persistentId']) {
            $stored = $repository->owned($state['persistentId'], $user) ?? throw $this->createNotFoundException();
            if ('draft' !== $stored->getStatus()) {
                return $this->redirectToRoute('app_draft', ['id' => $stored->getId()], 303);
            }
        }
        if (strlen($request->getContent()) > 4000000) {
            throw $this->createNotFoundException();
        }
        $submitted = $request->isMethod('POST') ? $request->request->all('publication_composer') : [];
        $intent = $request->request->getString('intent', 'review');
        $partial = in_array($intent, ['refresh', 'save'], true);
        $selected = $state['networks'];
        if ($submitted) {
            $values = $submitted['networks'] ?? [];
            $selected = is_array($values) ? array_values(array_unique(array_intersect(array_filter($values, 'is_string'), array_keys(MultiNetworkPublishing::LABELS)))) : [];
        }
        $destinations = $publishing->destinations($user);
        $accounts = $state['accounts'];
        foreach ($selected as $network) {
            if ('refresh' === $intent || !in_array($network, $state['networks'], true)) {
                $accounts[$network] = $destinations[$network]['account'];
            }
        }
        try {
            $context = $publishing->context($user, $state['ids'], $selected, $accounts, $state['content']);
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());

            return $state['persistentId']
                ? $this->redirectToRoute('app_draft_sources', ['id' => $state['persistentId']], 303)
                : $this->redirectToRoute('app_dashboard', ['choose' => 1], 303);
        }
        $accountChanged = false;
        foreach ($selected as $network) {
            $accountChanged = $accountChanged || ($accounts[$network] ?? null) !== $destinations[$network]['account'];
        }
        $settings = array_intersect_key($state['content'], array_flip($selected));
        foreach ($selected as $network) {
            foreach ($context['videos'] as $video) {
                $settings[$network]['videos'][$video->getId()] ??= match ($network) {
                    'youtube' => ['title' => mb_substr(str_replace(['<', '>'], '', $video->getTitle()), 0, 100), 'description' => '', 'privacy' => 'public', 'audience' => 'yes', 'synthetic' => false],
                    'instagram' => ['caption' => $video->getTitle(), 'shareToFeed' => true, 'synthetic' => false],
                    'tiktok' => ['caption' => $video->getTitle(), 'privacy' => null, 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => false],
                    default => throw $this->createNotFoundException(),
                };
            }
        }
        $form = $this->createForm(PublicationComposerType::class, ['networks' => $selected, 'settings' => $settings, 'mode' => $state['mode'], 'local' => $state['local'], 'timezone' => $state['timezone']], ['videos' => $context['videos'], 'networks' => $selected, 'creator' => $context['creator'], 'revision' => $state['revision'], 'draft_title' => $state['title'], 'partial' => $partial, 'csrf_token_id' => 'composer_'.$key]);
        $rawSettings = [];
        if ($submitted) {
            $rawSettings = is_array($submitted['settings'] ?? null) ? $submitted['settings'] : [];
            $submitted['settings'] = array_intersect_key($rawSettings, array_flip(array_merge($selected, ['draft_key', 'draft_title'])));
            foreach ($selected as $network) {
                if (!array_key_exists($network, $rawSettings)) {
                    $submitted['settings'][$network] = $settings[$network];
                }
            }
            $form->submit($submitted);
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $next = $state;
            // Preserve valid text from a network that was just unchecked, without granting any capability.
            $retainedNetworks = array_values(array_intersect(array_keys($rawSettings), array_keys(MultiNetworkPublishing::LABELS)));
            if ($retainedNetworks) {
                $retained = $this->createForm(MultiNetworkPrepareType::class, null, ['videos' => $context['videos'], 'networks' => $retainedNetworks, 'creator' => ['privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'], 'comment_disabled' => false, 'duet_disabled' => false, 'stitch_disabled' => false], 'draft_key' => $state['revision'], 'partial' => true, 'csrf_protection' => false]);
                $retained->submit(array_intersect_key($rawSettings, array_flip(array_merge($retainedNetworks, ['draft_key', 'draft_title']))));
                if ($retained->isValid()) {
                    $next['content'] = array_replace($next['content'], $retained->getData());
                }
            }
            $next['networks'] = $data['networks'];
            $next['accounts'] = array_intersect_key($accounts, array_flip($next['networks']));
            $next['content'] = array_replace($next['content'], $data['settings']);
            $next['title'] = $form->get('settings')->get('draft_title')->getData() ?? '';
            foreach (['mode', 'local', 'timezone'] as $field) {
                $next[$field] = $data[$field];
            }
            try {
                if ('refresh' !== $intent) {
                    $stored = $drafts->save($user, $next['ids'], $next['networks'], array_intersect_key($next['content'], MultiNetworkPublishing::LABELS), $next['accounts'], $next['title'], $next['persistentId'], $next['persistentRevision']);
                    $next['persistentId'] = $stored->getId();
                    $next['persistentRevision'] = $stored->getRevision();
                    // Save the draft before planning; a bad date must never lose edited text.
                    $this->save($request, $key, $next);
                    $state = $next;
                    $local = 'scheduled' === $next['mode'] ? ($next['local'] ?: ('review' === $intent ? '' : null)) : null;
                    $drafts->plan($user, (int) $stored->getId(), $stored->getRevision(), $local, $next['timezone']);
                    $next['persistentRevision'] = $stored->getRevision();
                    $this->save($request, $key, $next);
                    if ('review' === $intent) {
                        return $this->redirectToRoute('app_draft_confirm', ['id' => $stored->getId()], 303);
                    }
                    $this->addFlash('success', 'Brouillon enregistré. Aucun envoi n’a été lancé.');
                }
                $next['revision'] = bin2hex(random_bytes(16));
                $this->save($request, $key, $next);

                return $this->redirectToRoute('app_composer', ['key' => $key], 303);
            } catch (PublicationException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('publication/composer.html.twig', $context + ['form' => $form, 'key' => $key, 'selectedNetworks' => $selected, 'state' => $state, 'networkLabels' => MultiNetworkPublishing::LABELS, 'accountChanged' => $accountChanged], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'no-store, private']));
    }

    /** @param array<string, mixed> $state */
    private function save(Request $request, string $key, array $state): void
    {
        $states = $request->getSession()->get('composer_workspaces', []);
        $states[$key] = $state;
        $request->getSession()->set('composer_workspaces', $states);
    }
}
