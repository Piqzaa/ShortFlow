<?php

namespace App\Controller;

use App\Entity\AiEpisode;
use App\Entity\StoryProposal;
use App\Entity\User;
use App\Service\AiCatalog;
use App\Service\AiEpisodeFlow;
use App\Service\AiShotPrompts;
use App\Service\LocalStoryWriter;
use App\Service\StoryProposals;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AiStoryProposalController extends AbstractController
{
    #[Route('/studio/creation/scenario', name: 'app_ai_story_proposal', methods: ['POST'])]
    public function propose(Request $request, #[CurrentUser] User $user, AiEpisodeFlow $flow, AiCatalog $catalog, LocalStoryWriter $writer, StoryProposals $proposals, EntityManagerInterface $em, AiShotPrompts $prompts, #[Autowire(service: 'limiter.story_proposal')] RateLimiterFactory $limiter): Response
    {
        $headers = ['Cache-Control' => 'private, no-store'];
        if (!$this->isCsrfTokenValid('story_proposal', $request->request->getString('_token'))) {
            return $this->json(['error' => 'La page a expiré. Recharge-la avant de réessayer.'], 403, $headers);
        }
        $episode = null;
        if ($id = $request->request->getInt('episode')) {
            $episode = $flow->owned($id, $user) ?? throw $this->createNotFoundException();
            if (!in_array($episode->getFlowState(), [null, 'draft'], true)) {
                return $this->json(['error' => 'Cet épisode a déjà été lancé.'], 409, $headers);
            }
        }
        try {
            if (!$writer->available()) {
                throw new \RuntimeException('La rédaction locale n’est pas activée. Tu peux écrire tes scènes directement.');
            }
            $idea = trim($request->request->getString('idea'));
            $target = $request->request->getInt('target');
            $continuity = $request->request->getString('continuity', 'independent');
            $tone = $request->request->getString('tone', 'auto');
            if ('' === $idea || mb_strlen($idea) > 1500 || $target < 1 || $target > 60 || !in_array($continuity, ['single', 'independent', 'linked'], true) || !in_array($tone, ['auto', 'humour', 'aventure', 'calme'], true)) {
                throw new \InvalidArgumentException('Décris ton idée et choisis une durée entre 1 et 60 secondes.');
            }
            $durations = $flow->durations($request->request->getString('model'), $target);
            $layout = $request->request->getString('layout');
            if ('' !== $layout) {
                if (!preg_match('/^\d{1,2}(\+\d{1,2}){0,7}$/D', $layout)) {
                    throw new \InvalidArgumentException('Le découpage doit contenir de 1 à 8 clips compatibles.');
                }
                $durations = array_map('intval', explode('+', $layout));
                if (array_sum($durations) !== $target || array_diff($durations, $catalog->model($request->request->getString('model'))['seconds'])) {
                    throw new \InvalidArgumentException('Ajuste les durées des scènes pour respecter le modèle et le total souhaité.');
                }
            }
            if ('single' === $continuity && (1 !== count($durations) || !in_array($target, $catalog->model($request->request->getString('model'))['seconds'], true))) {
                throw new \InvalidArgumentException('Prépare un seul clip de durée native avant de demander un scénario en plan unique.');
            }
            if (!$limiter->create('story-'.$user->getId())->consume()->isAccepted()) {
                return $this->json(['error' => 'Plusieurs scénarios ont déjà été demandés. Réessaie dans quelques minutes.'], 429, $headers);
            }
            $context = [];
            if ($episode && !$episode->isStandalone()) {
                $series = $episode->getSeries();
                $context = ['theme' => $series->getTheme(), 'style' => $series->getStyle(), 'characters' => $series->getCharacters()];
                $previous = $em->createQueryBuilder()->select('e')->from(AiEpisode::class, 'e')->where('e.series = :series AND e.number < :number')->setParameter('series', $series)->setParameter('number', $episode->getNumber())->orderBy('e.number', 'DESC')->setMaxResults(3)->getQuery()->getResult();
                $context['previous'] = array_map(static fn (AiEpisode $e): array => ['title' => $e->getTitle(), 'scenes' => array_map(static fn ($shot): string => mb_substr($shot->getPrompt(), 0, 180), array_slice($flow->shots($e), 0, 3))], $previous);
            }
            $context['generation'] = $prompts->writingContext($request->request->getString('model'));
            $job = $proposals->create($user, $id ?: 0, $request->request->getString('fingerprint'), ['idea' => $idea, 'durations' => $durations, 'continuity' => $continuity, 'tone' => $tone, 'context' => $context]);

            return $this->json($this->payload($job), 202, $headers);
        } catch (\RuntimeException|\InvalidArgumentException $error) {
            return $this->json(['error' => $error->getMessage()], 422, $headers);
        }
    }

    #[Route('/studio/creation/scenario/dernier', name: 'app_ai_story_latest', methods: ['GET'])]
    public function latest(Request $request, #[CurrentUser] User $user, StoryProposals $proposals): Response
    {
        $job = $proposals->latest($user, max(0, $request->query->getInt('scope')));

        return $this->json($job ? $this->payload($job) : ['status' => 'none'], 200, ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/studio/creation/scenario/{id}', name: 'app_ai_story_status', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function status(string $id, #[CurrentUser] User $user, StoryProposals $proposals): Response
    {
        $job = $proposals->owned($user, $id) ?? throw $this->createNotFoundException();

        return $this->json($this->payload($job), 200, ['Cache-Control' => 'private, no-store']);
    }

    /** @return array<string,mixed> */
    private function payload(StoryProposal $job): array
    {
        return ['id' => $job->getId(), 'createdAt' => $job->getCreatedAt()->format(DATE_ATOM), 'status' => $job->getStatus(), 'url' => $this->generateUrl('app_ai_story_status', ['id' => $job->getId()]), 'fingerprint' => $job->getFingerprint(), 'result' => $job->getResult(), 'error' => $job->getError()];
    }
}
