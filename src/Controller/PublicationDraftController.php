<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PublicationPlanType;
use App\Repository\PublicationDraftRepository;
use App\Repository\VideoAssetRepository;
use App\Service\MultiNetworkPublishing;
use App\Service\PublicationCalendar;
use App\Service\PublicationDrafts;
use App\Service\PublicationException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;

final class PublicationDraftController extends AbstractController
{
    #[Route('/studio/brouillons', name: 'app_drafts', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, PublicationDraftRepository $repository, PublicationCalendar $calendar): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $total = $repository->count(['owner' => $user]);
        $pages = max(1, (int) ceil($total / 20));
        $page = min($page, $pages);
        $drafts = $repository->findBy(['owner' => $user], ['updatedAt' => 'DESC', 'id' => 'DESC'], 20, ($page - 1) * 20);

        return $this->page('draft/index.html.twig', ['drafts' => $drafts, 'states' => $calendar->states($drafts), 'labels' => PublicationCalendar::LABELS, 'page' => $page, 'pages' => $pages]);
    }

    #[Route('/studio/calendrier', name: 'app_calendar', methods: ['GET'])]
    public function calendar(Request $request, #[CurrentUser] User $user, PublicationDraftRepository $repository, PublicationCalendar $calendar, ClockInterface $clock, VideoAssetRepository $videos): Response
    {
        $zone = $request->query->getString('zone', $user->getTimezone());
        if (!in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            throw $this->createNotFoundException('Fuseau invalide.');
        }
        $timezone = new \DateTimeZone($zone);
        $month = $request->query->getString('month', $clock->now()->setTimezone($timezone)->format('Y-m'));
        if (!preg_match('/^(19|20|21)\d{2}-(0[1-9]|1[0-2])$/D', $month)) {
            throw $this->createNotFoundException('Mois invalide.');
        }
        $first = new \DateTimeImmutable($month.'-01 00:00:00', $timezone);
        $start = $first->modify('-'.((int) $first->format('N') - 1).' days');
        $dayCount = (int) (ceil(((int) $first->format('N') - 1 + (int) $first->format('t')) / 7) * 7);
        $end = $start->modify('+'.$dayCount.' days');
        $drafts = $repository->month($user, $start->setTimezone(new \DateTimeZone('UTC')), $end->setTimezone(new \DateTimeZone('UTC')));
        $states = $calendar->states($drafts);
        $network = $request->query->getString('network');
        $state = $request->query->getString('state');
        if ($network && !isset(MultiNetworkPublishing::LABELS[$network])) {
            throw $this->createNotFoundException();
        }
        if ($state && !isset(PublicationCalendar::LABELS[$state])) {
            throw $this->createNotFoundException();
        }
        $days = [];
        for ($i = 0; $i < $dayCount; ++$i) {
            $date = $start->modify('+'.$i.' days');
            $days[$date->format('Y-m-d')] = ['date' => $date, 'outside' => $date->format('Y-m') !== $month, 'entries' => []];
        }
        foreach ($drafts as $draft) {
            if (($network && !in_array($network, $draft->getNetworks(), true)) || ($state && $states[$draft->getId()] !== $state)) {
                continue;
            }
            $date = $draft->getScheduledAt() ?? $draft->getDispatchedAt();
            if ($date) {
                $days[$date->setTimezone($timezone)->format('Y-m-d')]['entries'][] = $draft;
            }
        }

        return $this->page('draft/calendar.html.twig', ['libraryVideos' => $videos->page($user), 'libraryTotal' => $videos->count(['owner' => $user]), 'weeks' => (int) ($dayCount / 7), 'days' => $days, 'month' => $first, 'previous' => $first->modify('-1 month')->format('Y-m'), 'next' => $first->modify('+1 month')->format('Y-m'), 'zone' => $zone, 'network' => $network, 'state' => $state, 'states' => $states, 'labels' => PublicationCalendar::LABELS, 'networks' => MultiNetworkPublishing::LABELS, 'undated' => $repository->count(['owner' => $user, 'scheduledAt' => null, 'status' => 'draft'])]);
    }

    #[Route('/studio/calendrier/videos', name: 'app_calendar_videos', methods: ['GET'])]
    public function videos(Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos): Response
    {
        $query = mb_substr(trim($request->query->getString('q')), 0, 120);
        $result = $videos->search($user, $query, $request->query->getInt('page', 1));

        return $this->json([
            'html' => $this->renderView('draft/_video_choices.html.twig', ['libraryVideos' => $result['items']]),
            'total' => $result['total'],
            'nextPage' => $result['page'] < $result['pages'] ? $result['page'] + 1 : null,
        ], headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'same-origin']);
    }

    #[Route('/studio/brouillons/{id}', name: 'app_draft', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, #[CurrentUser] User $user, PublicationDraftRepository $repository, PublicationDrafts $service, PublicationCalendar $calendar, MultiNetworkPublishing $publishing, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $draft = $repository->owned($id, $user) ?? throw $this->createNotFoundException();
        $form = $this->createForm(PublicationPlanType::class, ['mode' => $draft->getScheduledAt() ? 'scheduled' : 'immediate', 'local' => $draft->getLocalTime(), 'timezone' => $draft->getTimezone()], ['revision' => $draft->getRevision(), 'csrf_token_id' => 'draft_plan_'.$id])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$limiter->create('draft-'.$user->getId())->consume()->isAccepted()) {
                    throw new PublicationException('Trop de modifications rapprochées. Réessaie plus tard.');
                }
                $data = $form->getData();
                $service->plan($user, $id, (int) $data['revision'], 'scheduled' === $data['mode'] ? ($data['local'] ?: '') : null, $data['timezone']);

                return $this->redirectToRoute('app_draft_confirm', ['id' => $id], 303);
            } catch (PublicationException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }
        $viewVideos = [];
        foreach ($draft->getItems() as $item) {
            $viewVideos[] = ['id' => $item->getVideoId(), 'title' => $item->getTitle(), 'source' => $item->getSource()];
        }
        $destinations = $publishing->destinations($user);
        foreach ($destinations as $network => &$destination) {
            if (($draft->getAccounts()[$network] ?? null) !== $destination['account']) {
                $destination['name'] = 'Compte à revérifier';
            }
        }
        unset($destination);

        // Consultation stays local: no creator-info or statistics API call on a calendar/detail GET.
        return $this->page('draft/show.html.twig', ['draft' => $draft, 'form' => $form, 'states' => $calendar->states([$draft]), 'labels' => PublicationCalendar::LABELS, 'videos' => $viewVideos, 'content' => $draft->content(), 'networks' => $draft->getNetworks(), 'destinations' => $destinations], $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/studio/brouillons/{id}/confirmer', name: 'app_draft_confirm', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function confirm(int $id, Request $request, #[CurrentUser] User $user, PublicationDraftRepository $repository, PublicationDrafts $service, ManagerRegistry $registry, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $draft = $repository->owned($id, $user) ?? throw $this->createNotFoundException();
        if ('dispatched' === $draft->getStatus()) {
            return $this->redirectToRoute('app_publications', status: 303);
        }
        if ('draft' !== $draft->getStatus()) {
            return $this->redirectToRoute('app_draft', ['id' => $id], 303);
        }
        $context = $service->review($draft);
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'draft_confirm_'.$id])
            ->add('revision', HiddenType::class, ['data' => (string) $draft->getRevision(), 'constraints' => [new Assert\EqualTo((string) $draft->getRevision())]])
            ->add('confirm', CheckboxType::class, ['label' => 'Je confirme ce créneau, les vidéos, comptes, textes et réglages et dispose des droits nécessaires. Pour TikTok, j’accepte la confirmation d’utilisation de la musique et la politique relative aux contenus de marque pour les partenariats.', 'row_attr' => ['class' => 'publication-checkbox'], 'constraints' => [new Assert\IsTrue()]])->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$limiter->create('publish-'.$user->getId())->consume()->isAccepted()) {
                    throw new PublicationException('Trop de confirmations rapprochées.');
                }
                $scheduled = null !== $draft->getScheduledAt();
                $service->confirm($user, $id, (int) $form->getData()['revision'], $scheduled);
                $this->addFlash('success', $scheduled ? 'Programmation confirmée. Les envois démarreront au créneau indiqué.' : 'Les publications sont dans la file d’envoi.');

                return $scheduled ? $this->redirectToRoute('app_draft', ['id' => $id], 303) : $this->redirectToRoute('app_publications', status: 303);
            } catch (PublicationException $error) {
                $registry->resetManager();
                $this->addFlash('error', $error->getMessage());

                return $this->redirectToRoute('app_draft', ['id' => $id], 303);
            }
        }
        $viewVideos = [];
        foreach ($draft->getItems() as $item) {
            $viewVideos[] = ['id' => $item->getVideoId(), 'title' => $item->getTitle(), 'source' => $item->getSource()];
        }

        return $this->page('draft/confirm.html.twig', $context + ['draft' => $draft, 'form' => $form, 'viewVideos' => $viewVideos, 'content' => $draft->content(), 'networks' => $draft->getNetworks()], $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/studio/brouillons/{id}/{action}', name: 'app_draft_action', requirements: ['id' => '\d+', 'action' => 'edit|cancel|delete'], methods: ['GET', 'POST'])]
    public function action(int $id, string $action, Request $request, #[CurrentUser] User $user, PublicationDraftRepository $repository, PublicationDrafts $service, VideoAssetRepository $videos): Response
    {
        $draft = $repository->owned($id, $user) ?? throw $this->createNotFoundException();
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'draft_'.$action.'_'.$id])->add('revision', HiddenType::class, ['data' => (string) $draft->getRevision(), 'constraints' => [new Assert\EqualTo((string) $draft->getRevision())]])->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->transition($user, $id, (int) $form->getData()['revision'], $action);
                if ('delete' === $action) {
                    $this->addFlash('success', 'Brouillon supprimé. Les vidéos de la bibliothèque sont conservées.');

                    return $this->redirectToRoute('app_dashboard', ['tab' => 'drafts'], 303);
                }
                if ('cancel' === $action) {
                    $this->addFlash('success', 'Programmation annulée avant le départ.');

                    return $this->redirectToRoute('app_draft', ['id' => $id], 303);
                }
                $draft = $repository->owned($id, $user);
                foreach ($draft->getItems() as $item) {
                    if (!$item->getSource()) {
                        $this->addFlash('error', 'Modifie la sélection pour retirer ou remplacer la source manquante.');

                        return $this->redirectToRoute('app_draft_sources', ['id' => $id], 303);
                    }
                }

                return $this->redirectToRoute('app_composer_start', ['draft' => $id], 303);
            } catch (PublicationException $error) {
                $this->addFlash('error', $error->getMessage());

                return $this->redirectToRoute('app_draft', ['id' => $id], 303);
            }
        }

        return $this->page('draft/action.html.twig', ['draft' => $draft, 'action' => $action, 'form' => $form], $form->isSubmitted() ? 422 : 200);
    }

    #[Route('/studio/brouillons/{id}/videos', name: 'app_draft_sources', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function sources(int $id, Request $request, #[CurrentUser] User $user, PublicationDraftRepository $repository, VideoAssetRepository $videos, PublicationDrafts $service): Response
    {
        $draft = $repository->owned($id, $user) ?? throw $this->createNotFoundException();
        $choices = [];
        foreach ($videos->findBy(['owner' => $user], ['id' => 'DESC']) as $video) {
            $choices[$video->getTitle().' · #'.$video->getId()] = $video->getId();
        }
        $selected = array_values(array_intersect($draft->videoIds(), array_values($choices)));
        $form = $this->createFormBuilder(['videos' => $selected], ['csrf_token_id' => 'draft_sources_'.$id])->add('revision', HiddenType::class, ['data' => (string) $draft->getRevision(), 'constraints' => [new Assert\EqualTo((string) $draft->getRevision())]])->add('videos', ChoiceType::class, ['label' => 'Vidéos de ta bibliothèque', 'choices' => $choices, 'multiple' => true, 'expanded' => true, 'constraints' => [new Assert\Count(min: 1, max: in_array('tiktok', $draft->getNetworks(), true) ? 5 : 20)]])->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                $ids = array_map('intval', $data['videos']);
                $content = $draft->content();
                foreach ($content as &$network) {
                    $network['videos'] = array_intersect_key($network['videos'], array_flip($ids));
                }
                unset($network);
                $service->save($user, $ids, $draft->getNetworks(), $content, $draft->getAccounts(), $draft->getTitle(), $id, (int) $data['revision']);
                $this->addFlash('success', 'Sélection mise à jour. Vérifie les textes des vidéos ajoutées.');

                return $this->redirectToRoute('app_draft', ['id' => $id], 303);
            } catch (PublicationException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->page('draft/sources.html.twig', ['draft' => $draft, 'form' => $form], $form->isSubmitted() ? 422 : 200);
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, array $data, int $status = 200): Response
    {
        return $this->render($template, $data, new Response(status: $status, headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'same-origin']));
    }
}
