<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\PublicationException;
use App\Service\PublicationStatistics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PublicationStatisticsController extends AbstractController
{
    #[Route('/studio/publications/{network}/{id}/statistiques', name: 'app_publication_statistics', requirements: ['network' => 'youtube|instagram|tiktok', 'id' => '\d+'], methods: ['POST'])]
    public function refresh(Request $request, #[CurrentUser] User $user, string $network, int $id, PublicationStatistics $statistics, #[Autowire(service: 'limiter.publication_statistics')] RateLimiterFactory $limiter): Response
    {
        if (!$statistics->owned($user, $network, $id)) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('statistics_'.$network.'_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$limiter->create('statistics-'.$user->getId())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Patiente avant de demander une autre mise à jour.');
        }
        try {
            $queued = $statistics->queue($user, $network, $id);
            $this->addFlash('success', $queued ? 'La mise à jour des statistiques est en attente. Actualise la liste dans quelques instants.' : 'Une mise à jour a déjà été demandée récemment. Patiente une minute entre deux demandes.');
        } catch (PublicationException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_publications', ['network' => $network, 'state' => 'published'], 303);
    }
}
