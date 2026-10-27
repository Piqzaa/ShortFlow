<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\SeriesEditorialType;
use App\Repository\PublicationDraftRepository;
use App\Service\AiSeriesStudio;
use App\Service\PublicationEditorial;
use App\Service\PublicationException;
use App\Service\PublicationTexts;
use App\Service\StoryProposals;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PublicationEditorialController extends AbstractController
{
    #[Route('/studio/series/{id}/textes', name: 'app_series_editorial', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function profile(int $id, Request $request, #[CurrentUser] User $owner, AiSeriesStudio $seriesStudio, PublicationTexts $texts, EntityManagerInterface $em): Response
    {
        $series = $seriesStudio->owned($id, $owner) ?? throw $this->createNotFoundException();
        $form = $this->createForm(SeriesEditorialType::class, $texts->profile($series->getEditorialProfile()) + ['revision' => (string) $series->getRevision()], ['csrf_token_id' => 'editorial_profile_'.$id])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                $profile = $texts->profile($data);
                $em->getConnection()->transactional(function () use ($em, $owner, $series, $data, $profile): void {
                    $em->lock($owner, LockMode::PESSIMISTIC_WRITE);
                    $em->refresh($series, LockMode::PESSIMISTIC_WRITE);
                    if ($series->getRevision() !== (int) $data['revision']) {
                        throw new PublicationException('La série a changé. Recharge son profil avant de l’enregistrer.');
                    }
                    $series->configureEditorial($profile);
                    $em->flush();
                });
                $this->addFlash('success', 'Profil éditorial enregistré. Les textes existants restent à relire avant toute nouvelle application.');

                return $this->redirectToRoute('app_series_editorial', ['id' => $id], 303);
            } catch (PublicationException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('series/editorial.html.twig', ['series' => $series, 'form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/brouillons/{draft}/textes/{id}', name: 'app_publication_editorial_status', requirements: ['draft' => '\d+', 'id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function status(int $draft, string $id, #[CurrentUser] User $owner, PublicationDraftRepository $drafts, StoryProposals $jobs, PublicationEditorial $editorial): Response
    {
        $stored = $drafts->owned($draft, $owner) ?? throw $this->createNotFoundException();
        $job = $jobs->owned($owner, $id, 'editorial') ?? throw $this->createNotFoundException();
        if ($job->getScope() !== $stored->getId()) {
            throw $this->createNotFoundException();
        }
        $view = $editorial->view($stored);
        $stale = $view['stale'] || $view['job']?->getId() !== $job->getId();

        return $this->json(['id' => $job->getId(), 'status' => $stale ? 'stale' : $job->getStatus(), 'error' => $job->getError()] + $jobs->timing($job), headers: ['Cache-Control' => 'private, no-store']);
    }
}
