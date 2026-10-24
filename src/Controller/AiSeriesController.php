<?php

namespace App\Controller;

use App\Entity\AiSeries;
use App\Entity\User;
use App\Form\AiEpisodeBatchType;
use App\Form\AiEpisodeType;
use App\Form\AiSeriesType;
use App\Service\AiCatalog;
use App\Service\AiKeys;
use App\Service\AiSeriesStudio;
use App\Service\EpisodePublications;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AiSeriesController extends AbstractController
{
    #[Route('/studio/series/{id}/supprimer', name: 'app_series_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function delete(int $id, Request $request, #[CurrentUser] User $user, AiSeriesStudio $studio): Response
    {
        $series = $studio->owned($id, $user) ?? throw $this->createNotFoundException();
        $form = $this->createFormBuilder(['revision' => (string) $series->getRevision()], ['csrf_token_id' => 'series_delete_'.$id])
            ->add('revision', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, ['constraints' => [new \Symfony\Component\Validator\Constraints\Regex('/^\d+$/D')]])
            ->add('confirm', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, ['label' => 'Je confirme la suppression définitive de cette série et de ses épisodes.', 'constraints' => [new \Symfony\Component\Validator\Constraints\IsTrue(message: 'Confirme la suppression de la série.')]])
            ->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $studio->delete($series, $user, (int) $form->getData()['revision']);
                $this->addFlash('success', 'Série supprimée. Tes vidéos, ton historique de générations et tes préparations de publication sont conservés.');

                return $this->redirectToRoute('app_series', status: 303);
            } catch (\RuntimeException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('series/delete.html.twig', ['series' => $series, 'deleteForm' => $form], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series', name: 'app_series', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em, \App\Service\EpisodeCovers $covers): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $repository = $em->getRepository(AiSeries::class);
        $pages = max(1, (int) ceil($repository->count(['owner' => $user]) / 12));
        $page = min($page, $pages);

        $seriesList = $repository->findBy(['owner' => $user], ['createdAt' => 'DESC', 'id' => 'DESC'], 12, ($page - 1) * 12);

        return $this->render('series/index.html.twig', ['covers' => $covers->series($user, $seriesList), 'seriesList' => $seriesList, 'page' => $page, 'pages' => $pages, 'models' => AiCatalog::MODELS], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series/nouvelle', name: 'app_series_new', methods: ['GET', 'POST'])]
    #[Route('/studio/series/{id}/configuration', name: 'app_series_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function configure(Request $request, #[CurrentUser] User $user, AiSeriesStudio $studio, AiKeys $keys, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter, ?int $id = null): Response
    {
        $series = null === $id ? null : ($studio->owned($id, $user) ?? throw $this->createNotFoundException());
        $data = $series ? ['name' => $series->getName(), 'theme' => $series->getTheme(), 'style' => $series->getStyle(), 'characters' => $series->getCharacters(), 'revision' => (string) $series->getRevision()] + $series->getRecipe() : ['model' => 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'imageFit' => 'crop', 'revision' => '0'];
        $data['totalSeconds'] ??= $series ? ($series->getRecipe()['totalSeconds'] ?? 30) : 30;
        // Stored image names never come from a hidden field or the submitted form.
        unset($data['reference'], $data['endReference']);
        if (!$series) {
            $data['episodeCount'] = 1;
        }
        $form = $this->createForm(AiSeriesType::class, $data, ['creating' => null === $series, 'subjects' => $series?->getRecipe()['subjects'] ?? []]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$limiter->create('series-config-'.$user->getId())->consume()->isAccepted()) {
                    throw new \RuntimeException('Trop de demandes rapprochées. Réessaie dans quelques minutes.');
                }
                $saved = $studio->configure($user, $form->getData(), $series);
                $this->addFlash('success', $series ? 'Configuration enregistrée. Les préparations existantes sont conservées.' : 'Ta série est enregistrée. Prépare tes épisodes à ton rythme.');

                return $this->redirectToRoute('app_series_show', ['id' => $saved->getId()], 303);
            } catch (\RuntimeException|\InvalidArgumentException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }
        $access = [];
        foreach (AiCatalog::PROVIDERS as $provider => $name) {
            $access[$name] = $keys->hasAccess($user, $provider, 'api');
        }
        $access['Fal par navigateur'] = $keys->hasAccess($user, 'fal', 'mcp');

        return $this->render('series/configure.html.twig', ['series' => $series, 'seriesForm' => $form, 'models' => AiCatalog::MODELS, 'access' => $access], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series/{id}/episodes', name: 'app_series_add_episodes', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addEpisodes(int $id, Request $request, #[CurrentUser] User $user, AiSeriesStudio $studio, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $series = $studio->owned($id, $user) ?? throw $this->createNotFoundException();
        $form = $this->createForm(AiEpisodeBatchType::class, null, ['csrf_token_id' => 'series_add_'.$id])->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Vérifie le nombre d’épisodes et recharge la page.');
        }
        try {
            if (!$limiter->create('series-add-'.$user->getId())->consume()->isAccepted()) {
                throw new \RuntimeException('Trop d’ajouts rapprochés. Réessaie dans quelques minutes.');
            }
            $data = $form->getData();
            $studio->addEpisodes($user, $series, $data['count'], (int) $data['revision']);
            $this->addFlash('success', 'Les épisodes à préparer ont été ajoutés.');
        } catch (\RuntimeException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_series_show', ['id' => $id], 303);
    }

    #[Route('/studio/series/{id}/recurrence/pause', name: 'app_series_recurrence_pause', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function pauseRecurrence(int $id, Request $request, #[CurrentUser] User $user, AiSeriesStudio $studio, \App\Service\SeriesRecurrences $recurrences): Response
    {
        $series = $studio->owned($id, $user) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('series_pause_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $recurrence = $recurrences->find($series) ?? throw $this->createNotFoundException();
        try {
            $recurrences->pause($recurrence, $request->request->getInt('revision'));
            $this->addFlash('success', 'La récurrence est en pause.');
        } catch (\RuntimeException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_series_show', ['id' => $id], 303);
    }

    #[Route('/studio/series/{id}', name: 'app_series_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, #[CurrentUser] User $user, AiSeriesStudio $studio, FormFactoryInterface $forms, EntityManagerInterface $em, \App\Service\SeriesRecurrences $recurrences, EpisodePublications $publications, \App\Service\EpisodeCovers $covers): Response
    {
        $series = $studio->owned($id, $user) ?? throw $this->createNotFoundException();
        $episodes = $studio->episodes($series);
        $recurrence = $recurrences->find($series);
        $recurrenceError = null;
        $recurrencePreview = null;
        $invalid = false;
        try {
            $recurrencePreview = $recurrences->preview($series);
        } catch (\RuntimeException|\InvalidArgumentException $error) {
            $recurrenceError = $error->getMessage();
        }
        $settings = $recurrence?->getSettings() ?? [];
        $data = array_intersect_key($settings, array_flip(['weekday', 'hour', 'timezone', 'networks', 'limit', 'tone', 'continuity'])) + ['weekday' => 2, 'hour' => '18:00', 'timezone' => $user->getTimezone(), 'networks' => [], 'limit' => 4, 'tone' => 'auto', 'continuity' => 'independent'];
        $recurrenceForm = $this->createForm(\App\Form\SeriesRecurrenceType::class, $data + ['revision' => (string) ($recurrence?->getRevision() ?? 0), 'quote' => $recurrencePreview['hash'] ?? str_repeat('0', 64), 'consent' => false], ['csrf_token_id' => 'recurrence_'.$id]);
        if ($recurrencePreview) {
            $recurrenceForm->handleRequest($request);
            if ($recurrenceForm->isSubmitted()) {
                $invalid = true;
                if ($recurrenceForm->isValid()) {
                    try {
                        $recurrences->activate($series, $recurrenceForm->getData());
                        $this->addFlash('success', 'Récurrence activée avec ton autorisation de coûts. Chaque vidéo attendra ta validation avant publication.');

                        return $this->redirectToRoute('app_series_show', ['id' => $id], 303);
                    } catch (\RuntimeException|\InvalidArgumentException $error) {
                        $recurrenceForm->addError(new FormError($error->getMessage()));
                    }
                }
            }
        }
        $occurrences = $recurrence ? $em->getRepository(\App\Entity\SeriesOccurrence::class)->findBy(['recurrence' => $recurrence], ['scheduledAt' => 'DESC']) : [];
        $occurrenceByEpisode = [];
        $missed = [];
        foreach ($occurrences as $occurrence) {
            if ($occurrence->getEpisode()) {
                $occurrenceByEpisode[$occurrence->getEpisode()->getId()] = $occurrence;
            } elseif (count($missed) < 3) {
                $missed[] = $occurrence;
            }
        }
        $episodeForms = [];
        foreach ($episodes as $episode) {
            if ($episode->getFlowState() || ($episode->getGeneration() && 'prepared' !== $episode->getGeneration()->getStatus())) {
                continue;
            }
            $form = $forms->createNamed('episode_'.$episode->getId(), AiEpisodeType::class, ['title' => $episode->getTitle(), 'prompt' => $episode->getPrompt(), 'revision' => (string) $episode->getRevision(), 'seriesRevision' => (string) $series->getRevision()], ['csrf_token_id' => 'episode_'.$episode->getId()]);
            $form->handleRequest($request);
            if ($form->isSubmitted()) {
                $invalid = true;
                if ($form->isValid()) {
                    try {
                        $job = $studio->saveEpisode($user, $episode, $form->getData(), 'prepare' === $request->request->getString('intent'));
                        if ($job) {
                            return $this->redirectToRoute('app_ai_generation', ['id' => $job->getId()], 303);
                        }
                        $this->addFlash('success', 'Le prompt de '.$episode->getTitle().' est enregistré.');

                        return $this->redirectToRoute('app_series_show', ['id' => $id, '_fragment' => 'episode-'.$episode->getId()], 303);
                    } catch (\RuntimeException|\InvalidArgumentException $error) {
                        $form->addError(new FormError($error->getMessage()));
                    }
                }
            }
            $episodeForms[$episode->getId()] = $form->createView();
        }
        // A repeated POST after a successful preparation follows the existing result.
        if ($request->isMethod('POST') && !$invalid) {
            foreach ($episodes as $episode) {
                $submitted = $request->request->all('episode_'.$episode->getId());
                if ($episode->getGeneration() && $submitted && 'prepare' === $request->request->getString('intent') && $this->isCsrfTokenValid('episode_'.$episode->getId(), $submitted['_token'] ?? '')) {
                    return $this->redirectToRoute('app_ai_generation', ['id' => $episode->getGeneration()->getId()], 303);
                }
            }
            throw $this->createAccessDeniedException();
        }
        $model = AiCatalog::MODELS[$series->getRecipe()['model']];

        return $this->render('series/show.html.twig', ['episodeCovers' => $covers->episodes($user, $episodes), 'recurrenceTotalCents' => ($recurrencePreview['cents'] ?? 0) * (is_int($recurrenceForm->get('limit')->getData()) ? $recurrenceForm->get('limit')->getData() : 0), 'recurrence' => $recurrence, 'recurrenceForm' => $recurrencePreview ? $recurrenceForm->createView() : null, 'recurrencePreview' => $recurrencePreview, 'recurrenceError' => $recurrenceError, 'recurrenceMissed' => $missed, 'occurrenceByEpisode' => $occurrenceByEpisode, 'publicationSummaries' => $publications->summaries($episodes), 'series' => $series, 'episodes' => $episodes, 'addEpisodesForm' => $this->createForm(AiEpisodeBatchType::class, ['count' => 1, 'revision' => (string) $series->getRevision()], ['csrf_token_id' => 'series_add_'.$id, 'action' => $this->generateUrl('app_series_add_episodes', ['id' => $id])]), 'episodeForms' => $episodeForms, 'model' => $model, 'models' => AiCatalog::MODELS, 'provider' => AiCatalog::PROVIDERS[$model['provider']]], new Response(status: $invalid ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }
}
