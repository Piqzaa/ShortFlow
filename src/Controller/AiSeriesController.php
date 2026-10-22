<?php

namespace App\Controller;

use App\Entity\AiSeries;
use App\Entity\User;
use App\Form\AiEpisodeType;
use App\Form\AiSeriesType;
use App\Service\AiCatalog;
use App\Service\AiKeys;
use App\Service\AiSeriesStudio;
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
    #[Route('/studio/series', name: 'app_series', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $repository = $em->getRepository(AiSeries::class);
        $pages = max(1, (int) ceil($repository->count(['owner' => $user]) / 12));
        $page = min($page, $pages);

        return $this->render('series/index.html.twig', ['seriesList' => $repository->findBy(['owner' => $user], ['createdAt' => 'DESC', 'id' => 'DESC'], 12, ($page - 1) * 12), 'page' => $page, 'pages' => $pages, 'models' => AiCatalog::MODELS], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/series/nouvelle', name: 'app_series_new', methods: ['GET', 'POST'])]
    #[Route('/studio/series/{id}/configuration', name: 'app_series_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function configure(Request $request, #[CurrentUser] User $user, AiSeriesStudio $studio, AiKeys $keys, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter, ?int $id = null): Response
    {
        $series = null === $id ? null : ($studio->owned($id, $user) ?? throw $this->createNotFoundException());
        $data = $series ? ['name' => $series->getName(), 'theme' => $series->getTheme(), 'style' => $series->getStyle(), 'characters' => $series->getCharacters(), 'revision' => (string) $series->getRevision()] + $series->getRecipe() : ['model' => 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'imageFit' => 'crop', 'revision' => '0'];
        // Stored image names never come from a hidden field or the submitted form.
        unset($data['reference'], $data['endReference']);
        $form = $this->createForm(AiSeriesType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$limiter->create('series-config-'.$user->getId())->consume()->isAccepted()) {
                    throw new \RuntimeException('Trop de demandes rapprochées. Réessaie dans quelques minutes.');
                }
                $saved = $studio->configure($user, $form->getData(), $series);
                $this->addFlash('success', $series ? 'Configuration enregistrée. Les prompts et préparations des épisodes sont conservés.' : 'Ta série et ses trois premiers épisodes sont enregistrés. Complète leurs prompts à ton rythme.');

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

    #[Route('/studio/series/{id}', name: 'app_series_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, #[CurrentUser] User $user, AiSeriesStudio $studio, FormFactoryInterface $forms): Response
    {
        $series = $studio->owned($id, $user) ?? throw $this->createNotFoundException();
        $episodes = $studio->episodes($series);
        $episodeForms = [];
        $invalid = false;
        foreach ($episodes as $episode) {
            if ($episode->getGeneration() && 'prepared' !== $episode->getGeneration()->getStatus()) {
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

        return $this->render('series/show.html.twig', ['series' => $series, 'episodes' => $episodes, 'episodeForms' => $episodeForms, 'model' => $model, 'models' => AiCatalog::MODELS, 'provider' => AiCatalog::PROVIDERS[$model['provider']]], new Response(status: $invalid ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }
}
