<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\MultiNetworkPrepareType;
use App\Form\PublicationPlanType;
use App\Repository\PublicationDraftRepository;
use App\Repository\VideoAssetRepository;
use App\Service\MultiNetworkPublishing;
use App\Service\PublicationDrafts;
use App\Service\PublicationException;
use App\Service\TikTokPublishing;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/studio/publications')]
final class MultiNetworkPublicationController extends AbstractController
{
    #[Route('/preparer', name: 'app_publication_prepare', methods: ['GET'])]
    public function start(Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos): Response
    {
        $ids = [];
        foreach ($request->query->all('videos') as $id) {
            if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw $this->createNotFoundException();
            }
            $ids[] = (int) $id;
        }
        $ids = array_values(array_unique($ids));
        if (!$ids || count($ids) > 20) {
            $this->addFlash('error', 'Sélectionne entre 1 et 20 vidéos dans ta bibliothèque.');

            return $this->redirectToRoute('app_library', status: 303);
        }
        foreach ($ids as $id) {
            $videos->owned($id, $user) ?? throw $this->createNotFoundException();
        }
        $drafts = $request->getSession()->get('publication_drafts', []);
        $drafts = array_filter($drafts, static fn (array $draft): bool => $draft['expires'] > time());
        $drafts = array_slice($drafts, -9, null, true);
        $key = bin2hex(random_bytes(32));
        $drafts[$key] = ['owner' => $user->getId(), 'ids' => $ids, 'networks' => [], 'accounts' => [], 'content' => [], 'revision' => bin2hex(random_bytes(16)), 'expires' => time() + 3600, 'completed' => false];
        $request->getSession()->set('publication_drafts', $drafts);

        return $this->redirectToRoute('app_publication_batch', ['key' => $key], 303);
    }

    #[Route('/preparation/{key}', name: 'app_publication_batch', requirements: ['key' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function networks(string $key, Request $request, #[CurrentUser] User $user, MultiNetworkPublishing $publishing, PublicationDrafts $drafts, \App\Service\SocialAccounts $social): Response
    {
        $draft = $this->draft($request, $user, $key);
        if ($draft['completed']) {
            return $this->redirectToRoute('app_publications', status: 303);
        }
        $context = $this->context($publishing, $user, $draft);
        $saving = $request->isMethod('POST') && $request->request->has('save_draft');
        $choices = $social->choices($user);
        foreach ($draft['accounts'] as $network => $identity) {
            if ($identity && !in_array($identity, $choices[$network], true)) {
                $choices[$network]['Compte enregistré indisponible · '.$identity] = $identity;
            }
        }
        $form = $this->createFormBuilder(['networks' => $draft['networks'], 'accounts' => $draft['accounts'] + $social->defaults($user)], ['csrf_token_id' => 'batch_networks_'.$key])
            ->add('accounts', \App\Form\SocialAccountsType::class, ['choices_by_network' => $choices])
            ->add('networks', ChoiceType::class, ['label' => 'Destinations', 'choices' => array_flip(MultiNetworkPublishing::LABELS), 'multiple' => true, 'expanded' => true, 'constraints' => [new Assert\Count(min: $saving ? 0 : 1, max: 3, minMessage: 'Choisis au moins un réseau.')]])->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $draft['networks'] = $form->getData()['networks'];
            try {
                $draft['accounts'] = $social->select($user, $draft['networks'], $form->getData()['accounts']);
            } catch (PublicationException $error) {
                $form->addError(new FormError($error->getMessage()));

                return $this->page('publication/batch_networks.html.twig', $context + ['form' => $form, 'key' => $key, 'step' => 1], 422);
            }
            if (in_array('tiktok', $draft['networks'], true) && count($draft['ids']) > 5) {
                $form->addError(new FormError('Pour inclure TikTok, sélectionne au maximum 5 vidéos. Tu peux aussi continuer sans TikTok.'));
            } else {
                $draft['revision'] = bin2hex(random_bytes(16));
                unset($draft['reviewKey']);
                $this->save($request, $key, $draft);
                if ($saving) {
                    try {
                        $this->persist($request, $user, $key, $draft, $drafts, $draft['title'] ?? '');
                        $this->addFlash('success', 'Brouillon enregistré. Tu pourras le retrouver après déconnexion.');

                        return $this->redirectToRoute('app_draft', ['id' => $draft['persistentId']], 303);
                    } catch (PublicationException $error) {
                        $form->addError(new FormError($error->getMessage()));

                        return $this->page('publication/batch_networks.html.twig', $context + ['form' => $form, 'key' => $key, 'step' => 1], 422);
                    }
                }

                return $this->redirectToRoute('app_publication_batch_edit', ['key' => $key], 303);
            }
        }

        return $this->page('publication/batch_networks.html.twig', $context + ['form' => $form, 'key' => $key, 'step' => 1], $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/preparation/{key}/textes', name: 'app_publication_batch_edit', requirements: ['key' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function edit(string $key, Request $request, #[CurrentUser] User $user, MultiNetworkPublishing $publishing, TikTokPublishing $tiktok, PublicationDrafts $drafts): Response
    {
        $draft = $this->draft($request, $user, $key);
        if ($draft['completed']) {
            return $this->redirectToRoute('app_publications', status: 303);
        }
        if (!$draft['networks']) {
            return $this->redirectToRoute('app_publication_batch', ['key' => $key], 303);
        }
        $context = $this->context($publishing, $user, $draft);
        $saving = $request->isMethod('POST') && $request->request->has('save_draft');
        $data = $draft['content'];
        foreach ($draft['networks'] as $network) {
            foreach ($context['videos'] as $video) {
                $data[$network]['videos'][$video->getId()] ??= match ($network) {
                    'youtube' => ['title' => mb_substr(str_replace(['<', '>'], '', $video->getTitle()), 0, 100), 'description' => '', 'privacy' => 'public', 'audience' => 'yes', 'synthetic' => false],
                    'instagram' => ['caption' => $video->getTitle(), 'shareToFeed' => true, 'synthetic' => false],
                    'tiktok' => ['caption' => $video->getTitle(), 'privacy' => null, 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => false],
                    default => throw $this->createNotFoundException(),
                };
                if ('tiktok' === $network) {
                    $row = &$data[$network]['videos'][$video->getId()];
                    if (!in_array($row['privacy'], $context['creator']['privacy_level_options'] ?? [], true)) {
                        $row['privacy'] = null;
                    }
                    foreach (['comment', 'duet', 'stitch'] as $flag) {
                        if ($context['creator'][$flag.'_disabled'] ?? true) {
                            $row[$flag] = false;
                        }
                    }
                    unset($row);
                }
            }
        }
        $data = array_intersect_key($data, array_flip($draft['networks']));
        $form = $this->createForm(MultiNetworkPrepareType::class, $data, ['videos' => $context['videos'], 'networks' => $draft['networks'], 'draft_key' => $draft['revision'], 'creator' => $context['creator'], 'partial' => $saving, 'draft_title' => $draft['title'] ?? ''])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$saving && $context['creator']) {
                foreach ($form->getData()['tiktok']['videos'] ?? [] as $row) {
                    try {
                        // Cross-field commercial, UTF-16 and permission rules also apply before review.
                        $tiktok->options($row, $context['creator']);
                    } catch (PublicationException $error) {
                        $form->addError(new FormError($error->getMessage()));
                    }
                }
            }
            if ($form->isValid()) {
                $draft['content'] = array_replace($draft['content'], $form->getData());
                try {
                    $this->persist($request, $user, $key, $draft, $drafts, $form->get('draft_title')->getData() ?? '');
                } catch (PublicationException $error) {
                    $form->addError(new FormError($error->getMessage()));

                    return $this->page('publication/batch_edit.html.twig', $context + ['form' => $form, 'key' => $key, 'networks' => $draft['networks'], 'step' => 2], 422);
                }
                if ($saving) {
                    unset($draft['reviewKey']);
                    $this->save($request, $key, $draft);
                    $this->addFlash('success', 'Brouillon enregistré. Aucun envoi n’a été lancé.');

                    return $this->redirectToRoute('app_draft', ['id' => $draft['persistentId']], 303);
                }
                $draft['reviewKey'] = bin2hex(random_bytes(16));
                $draft['revision'] = bin2hex(random_bytes(16));
                $this->save($request, $key, $draft);

                return $this->redirectToRoute('app_publication_batch_confirm', ['key' => $key], 303);
            }
        }

        return $this->page('publication/batch_edit.html.twig', $context + ['form' => $form, 'key' => $key, 'networks' => $draft['networks'], 'step' => 2], $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/preparation/{key}/confirmer', name: 'app_publication_batch_confirm', requirements: ['key' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function confirm(string $key, Request $request, #[CurrentUser] User $user, MultiNetworkPublishing $publishing, PublicationDrafts $drafts, PublicationDraftRepository $repository, ManagerRegistry $registry, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $draft = $this->draft($request, $user, $key);
        if ($draft['completed']) {
            return $this->redirectToRoute('app_publications', status: 303);
        }
        if (!isset($draft['reviewKey'])) {
            return $this->redirectToRoute('app_publication_batch_edit', ['key' => $key], 303);
        }
        $context = $this->context($publishing, $user, $draft);
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'batch_confirm_'.$key])
            ->add('review_key', HiddenType::class, ['data' => $draft['reviewKey'], 'constraints' => [new Assert\EqualTo($draft['reviewKey'], message: 'Le récapitulatif a changé. Recharge la page avant de confirmer.')]])
            ->add('confirm', CheckboxType::class, ['label' => 'Je confirme les vidéos, les comptes et tous les réglages ci-dessus, et je dispose des droits nécessaires pour les publier.', 'row_attr' => ['class' => 'publication-checkbox'], 'constraints' => [new Assert\IsTrue()]])
            ->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$context['ready']) {
                $form->addError(new FormError('Toutes les destinations choisies doivent être prêtes. Aucun envoi n’a été lancé.'));
            } elseif (!$limiter->create('publish-'.$user->getId())->consume()->isAccepted()) {
                $form->addError(new FormError('Trop de publications rapprochées. Réessaie dans 15 minutes.'));
            } else {
                try {
                    if (!isset($draft['persistentId'], $draft['persistentRevision'])) {
                        throw new PublicationException('Enregistre de nouveau les textes avant de confirmer.');
                    }
                    $drafts->confirm($user, $draft['persistentId'], $draft['persistentRevision'], false);
                    $draft['completed'] = true;
                    $this->save($request, $key, $draft);
                    $this->addFlash('success', count($draft['ids']) * count($draft['networks']).' publications sont dans la file d’envoi. Le suivi est distinct pour chaque vidéo et destination.');

                    return $this->redirectToRoute('app_publications', status: 303);
                } catch (PublicationException $error) {
                    $registry->resetManager();
                    $this->addFlash('error', $error->getMessage());

                    return $this->redirectToRoute('app_publication_batch_confirm', ['key' => $key], 303);
                }
            }
        }

        $stored = isset($draft['persistentId']) ? $repository->owned($draft['persistentId'], $user) : null;
        $plan = $stored ? $this->createForm(PublicationPlanType::class, ['mode' => 'scheduled', 'local' => $stored->getLocalTime(), 'timezone' => $stored->getTimezone()], ['revision' => $draft['persistentRevision'], 'scheduled_only' => true, 'csrf_token_id' => 'draft_plan_'.$stored->getId(), 'action' => $this->generateUrl('app_draft', ['id' => $stored->getId()])]) : null;

        return $this->page('publication/batch_confirm.html.twig', $context + ['form' => $form, 'plan' => $plan, 'key' => $key, 'networks' => $draft['networks'], 'content' => $draft['content'], 'persistentId' => $draft['persistentId'] ?? null, 'step' => 3], $form->isSubmitted() ? 422 : 200);
    }

    /** @return array<string, mixed> */
    private function draft(Request $request, User $user, string $key): array
    {
        $draft = $request->getSession()->get('publication_drafts', [])[$key] ?? null;
        if (!$draft || $draft['owner'] !== $user->getId()) {
            throw $this->createNotFoundException();
        }
        if ($draft['expires'] <= time()) {
            throw new GoneHttpException('La préparation a expiré. Sélectionne à nouveau tes vidéos dans la bibliothèque.');
        }

        return $draft;
    }

    /** @param array<string, mixed> $draft */
    private function save(Request $request, string $key, array $draft): void
    {
        $drafts = $request->getSession()->get('publication_drafts', []);
        $drafts[$key] = $draft;
        $request->getSession()->set('publication_drafts', $drafts);
    }

    /** @param array<string, mixed> $draft */
    private function persist(Request $request, User $user, string $key, array &$draft, PublicationDrafts $service, string $title): void
    {
        $stored = $service->save($user, $draft['ids'], $draft['networks'], $draft['content'], $draft['accounts'], $title, $draft['persistentId'] ?? null, $draft['persistentRevision'] ?? null);
        $draft['persistentId'] = $stored->getId();
        $draft['persistentRevision'] = $stored->getRevision();
        $draft['title'] = $stored->getTitle();
        $this->save($request, $key, $draft);
    }

    /** @param array<string, mixed> $draft @return array<string, mixed> */
    private function context(MultiNetworkPublishing $publishing, User $user, array $draft): array
    {
        try {
            return $publishing->context($user, $draft['ids'], $draft['networks'], $draft['accounts'], $draft['content']);
        } catch (PublicationException) {
            throw $this->createNotFoundException('Une vidéo sélectionnée est indisponible.');
        }
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, array $data, int $status): Response
    {
        return $this->render($template, $data, new Response(status: $status, headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'same-origin']));
    }
}
