<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PublicationBatchType;
use App\Repository\PublicationDraftRepository;
use App\Repository\VideoAssetRepository;
use App\Service\MultiNetworkPublishing;
use App\Service\PublicationBatch;
use App\Service\PublicationCalendar;
use App\Service\PublicationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PublicationBatchController extends AbstractController
{
    #[Route('/studio/composer/selection/{key}', name: 'app_publication_selection', requirements: ['key' => '[a-f0-9]{48}'], methods: ['GET', 'POST'])]
    public function prepare(string $key, Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos, PublicationDraftRepository $drafts, PublicationBatch $batch, PublicationCalendar $calendar, EntityManagerInterface $em, \App\Service\SocialAccounts $accounts): Response
    {
        $state = $request->getSession()->get('composer_workspaces', [])[$key] ?? null;
        if (!is_array($state) || $state['owner'] !== $user->getId() || !($state['individual'] ?? false)) {
            throw $this->createNotFoundException();
        }
        if ($state['expires'] < time()) {
            throw new GoneHttpException('Cette sélection a expiré. Retrouve les préparations enregistrées dans Vidéos ou choisis à nouveau tes vidéos.');
        }
        $result = [];
        foreach ($state['preparedDrafts'] ?? [] as $id) {
            $result[] = $drafts->owned($id, $user) ?? throw $this->createNotFoundException();
        }
        if ($result) {
            if ($request->isMethod('POST')) {
                return $this->redirectToRoute('app_publication_selection', ['key' => $key], 303);
            }

            return $this->render('publication/selection.html.twig', ['key' => $key, 'prepared' => $result, 'states' => $calendar->states($result), 'labels' => PublicationCalendar::LABELS, 'networkLabels' => MultiNetworkPublishing::LABELS], new Response(headers: ['Cache-Control' => 'private, no-store']));
        }
        $selected = [];
        $plans = [];
        $defaultAccounts = $accounts->defaults($user);
        foreach ($state['ids'] as $id) {
            $selected[$id] = $videos->owned($id, $user) ?? throw $this->createNotFoundException();
            $plans[$id] = ['networks' => [], 'accounts' => $defaultAccounts, 'mode' => $state['mode'], 'local' => $state['local'], 'timezone' => $state['timezone']];
        }
        $form = $this->createForm(PublicationBatchType::class, ['plans' => $plans], ['video_ids' => $state['ids'], 'revision' => $state['revision'], 'account_choices' => $accounts->choices($user), 'csrf_token_id' => 'publication_batch_'.$key]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $result = $batch->prepare($user, $key, $form->getData()['plans']);
                $state['preparedDrafts'] = array_map(static fn ($draft): int => (int) $draft->getId(), $result);
                $states = $request->getSession()->get('composer_workspaces', []);
                $states[$key] = $state;
                $request->getSession()->set('composer_workspaces', $states);

                return $this->redirectToRoute('app_publication_selection', ['key' => $key], 303);
            } catch (PublicationException $error) {
                $form->addError(new FormError($error->getMessage()));
                // Reload after a possible complete rollback; no partially flushed entity is reused.
                $user = $em->find(User::class, $user->getId());
                foreach ($state['ids'] as $id) {
                    $selected[$id] = $videos->owned($id, $user) ?? throw $this->createNotFoundException();
                }
            }
        }

        return $this->render('publication/selection.html.twig', ['prepared' => [], 'videos' => $selected, 'form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }
}
