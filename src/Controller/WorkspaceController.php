<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\PublishedVideoTrace;
use App\Repository\PublicationDraftRepository;
use App\Repository\VideoAssetRepository;
use App\Service\PublicationCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class WorkspaceController extends AbstractController
{
    #[Route('/studio', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos, PublicationDraftRepository $drafts, PublicationCalendar $calendar, ClockInterface $clock, EntityManagerInterface $em): Response
    {
        $tab = $request->query->getString('tab', 'videos');
        if (!in_array($tab, ['videos', 'drafts'], true)) {
            throw $this->createNotFoundException();
        }
        $usage = $videos->usage($user);
        $draftCount = $drafts->count(['owner' => $user, 'status' => 'draft']);
        $query = 'videos' === $tab ? mb_substr(trim($request->query->getString('q')), 0, 120) : '';
        $results = 'videos' === $tab ? $videos->search($user, $query, $request->query->getInt('page', 1)) : null;
        $pages = $results['pages'] ?? max(1, (int) ceil($draftCount / 12));
        $page = $results['page'] ?? min($pages, max(1, $request->query->getInt('page', 1)));
        $recent = $results['items'] ?? [];
        $preparations = $drafts->dashboard($user, $clock->now());
        $date = $request->query->getString('date');
        $zone = $request->query->getString('zone', $user->getTimezone());
        if (!in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            throw $this->createNotFoundException();
        }
        if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            throw $this->createNotFoundException();
        }

        $choosing = $request->query->getBoolean('choose');
        $traces = 'videos' === $tab && !$choosing && '' === $query && 1 === $page
            ? $em->getRepository(PublishedVideoTrace::class)->findBy(['owner' => $user], ['removedAt' => 'DESC', 'id' => 'DESC'], 6)
            : [];

        return $this->render('studio/workspace.html.twig', ['editProjects' => $em->getRepository(\App\Entity\VideoEdit::class)->findBy(['owner' => $user], ['updatedAt' => 'DESC'], 50), 'editLabels' => \App\Entity\VideoEdit::LABELS, 'query' => $query, 'resultCount' => $results['total'] ?? 0, 'tab' => $tab, 'usage' => $usage, 'videos' => $recent, 'draftCount' => $draftCount, 'drafts' => 'drafts' === $tab ? $drafts->findBy(['owner' => $user, 'status' => 'draft'], ['updatedAt' => 'DESC', 'id' => 'DESC'], 12, ($page - 1) * 12) : [], 'publishedPlatforms' => $videos->publishedPlatforms($user, $recent), 'preparations' => $preparations, 'preparationStates' => $calendar->states(array_merge($preparations['upcoming'], $preparations['problems'])), 'preparationLabels' => PublicationCalendar::LABELS, 'pages' => $pages, 'page' => $page, 'choosing' => $choosing, 'date' => $date, 'zone' => $zone, 'traces' => $traces], new Response(headers: ['Cache-Control' => 'no-store, private']));
    }

    #[Route('/studio/videos-publiees/{id}/apercu', name: 'app_published_video_poster', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function poster(int $id, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $trace = $em->getRepository(PublishedVideoTrace::class)->findOneBy(['id' => $id, 'owner' => $user]);
        $poster = $trace?->getPoster();
        if (!$poster || false === ($image = base64_decode($poster, true))) {
            throw $this->createNotFoundException('Aperçu indisponible.');
        }

        return new Response($image, headers: ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
