<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\SeriesMontageType;
use App\Service\AiSeriesStudio;
use App\Service\SeriesMontage;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SeriesMontageController extends AbstractController
{
    #[Route('/studio/series/{id}/montage', name: 'app_series_montage', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function configure(int $id, Request $request, #[CurrentUser] User $user, AiSeriesStudio $studio, EntityManagerInterface $em): Response
    {
        $series = $studio->owned($id, $user) ?? throw $this->createNotFoundException();
        $preview = SeriesMontage::normalize($series->getRecipe()['montage'] ?? []);
        $form = $this->createForm(SeriesMontageType::class, $preview + ['revision' => (string) $series->getRevision()], ['csrf_token_id' => 'series_montage_'.$id])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                $settings = SeriesMontage::normalize(['sourceVolume' => $data['sourceVolume'], 'intro' => $data['intro'], 'fit' => $data['fit']]);
                $em->getConnection()->transactional(function () use ($em, $user, $series, $data, $settings): void {
                    $em->lock($user, LockMode::PESSIMISTIC_WRITE);
                    $em->refresh($series, LockMode::PESSIMISTIC_WRITE);
                    if ($series->getOwner()->getId() !== $user->getId()) {
                        throw $this->createNotFoundException();
                    }
                    if ($series->getRevision() !== (int) $data['revision']) {
                        throw new \RuntimeException('Cette série a changé dans un autre onglet. Recharge avant de modifier son montage.');
                    }
                    $series->configure($series->getName(), $series->getTheme(), $series->getStyle(), $series->getCharacters(), array_replace($series->getRecipe(), ['montage' => $settings]));
                    $em->flush();
                });
                $this->addFlash('success', 'Profil de montage enregistré pour les prochaines préparations. Les vidéos déjà préparées conservent leurs réglages.');

                return $this->redirectToRoute('app_series_montage', ['id' => $id], 303);
            } catch (\RuntimeException|\InvalidArgumentException $error) {
                if ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    throw $error;
                }
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('series/montage.html.twig', ['series' => $series, 'montageForm' => $form, 'preview' => $preview], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }
}
