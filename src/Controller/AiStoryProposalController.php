<?php

namespace App\Controller;

use App\Entity\StoryProposal;
use App\Entity\User;
use App\Service\StoryProposals;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AiStoryProposalController extends AbstractController
{
    #[Route('/studio/creation/scenario', name: 'app_ai_story_proposal', methods: ['POST'])]
    public function propose(): Response
    {
        return $this->json(['error' => 'La proposition de scénarios a été retirée. Décris directement ta vidéo ou chacun de tes plans.'], Response::HTTP_GONE, ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/studio/creation/scenario/dernier', name: 'app_ai_story_latest', methods: ['GET'])]
    public function latest(Request $request, #[CurrentUser] User $user, StoryProposals $proposals): Response
    {
        $job = $proposals->latest($user, max(0, $request->query->getInt('scope')));

        return $this->json($job ? $this->payload($job, $proposals) : ['status' => 'none'], 200, ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/studio/creation/scenario/{id}', name: 'app_ai_story_status', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function status(string $id, #[CurrentUser] User $user, StoryProposals $proposals): Response
    {
        $job = $proposals->owned($user, $id) ?? throw $this->createNotFoundException();

        return $this->json($this->payload($job, $proposals), 200, ['Cache-Control' => 'private, no-store']);
    }

    /** @return array<string,mixed> */
    private function payload(StoryProposal $job, StoryProposals $proposals): array
    {
        return ['id' => $job->getId(), 'status' => $job->getStatus(), 'url' => $this->generateUrl('app_ai_story_status', ['id' => $job->getId()]), 'fingerprint' => $job->getFingerprint(), 'result' => $job->getResult(), 'error' => $job->getError()] + $proposals->timing($job);
    }
}
