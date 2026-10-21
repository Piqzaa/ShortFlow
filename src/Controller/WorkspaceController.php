<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\PublicationDraftRepository;
use App\Repository\VideoAssetRepository;
use App\Service\PublicationCalendar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class WorkspaceController extends AbstractController
{
    #[Route('/studio', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos, PublicationDraftRepository $drafts, PublicationCalendar $calendar, ClockInterface $clock, \Doctrine\ORM\EntityManagerInterface $em): Response
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

        return $this->render('studio/workspace.html.twig', ['editProjects' => $em->getRepository(\App\Entity\VideoEdit::class)->findBy(['owner' => $user], ['updatedAt' => 'DESC'], 50), 'editLabels' => \App\Entity\VideoEdit::LABELS, 'query' => $query, 'resultCount' => $results['total'] ?? 0, 'tab' => $tab, 'usage' => $usage, 'videos' => $recent, 'draftCount' => $draftCount, 'drafts' => 'drafts' === $tab ? $drafts->findBy(['owner' => $user, 'status' => 'draft'], ['updatedAt' => 'DESC', 'id' => 'DESC'], 12, ($page - 1) * 12) : [], 'publishedPlatforms' => $videos->publishedPlatforms($user, $recent), 'preparations' => $preparations, 'preparationStates' => $calendar->states(array_merge($preparations['upcoming'], $preparations['problems'])), 'preparationLabels' => PublicationCalendar::LABELS, 'pages' => $pages, 'page' => $page, 'choosing' => $request->query->getBoolean('choose'), 'date' => $date, 'zone' => $zone], new Response(headers: ['Cache-Control' => 'no-store, private']));
    }
}
