<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\SeriesCalendarType;
use App\Service\AiSeriesStudio;
use App\Service\PublicationException;
use App\Service\SeriesCalendar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SeriesCalendarController extends AbstractController
{
    #[Route('/studio/series/{id}/calendrier', name: 'app_series_calendar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function calendar(int $id, Request $request, #[CurrentUser] User $owner, AiSeriesStudio $studio, SeriesCalendar $calendar, ClockInterface $clock): Response
    {
        $series = $studio->owned($id, $owner) ?? throw $this->createNotFoundException();
        $saved = $series->getCalendarPlan();
        $defaults = ['start' => $clock->now()->setTimezone(new \DateTimeZone($owner->getTimezone()))->modify('+1 day')->format('Y-m-d'), 'end' => null, 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'hour' => '18:00', 'timezone' => $owner->getTimezone(), 'count' => 4];
        $form = $this->createForm(SeriesCalendarType::class, ($saved['rules'] ?? $defaults) + ['revision' => (string) $series->getRevision(), 'snapshot' => ''], ['csrf_token_id' => 'series_calendar_'.$id])->handleRequest($request);
        $preview = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                if ('save' === $request->request->getString('intent')) {
                    $calendar->save($owner, $series, $data);
                    $this->addFlash('success', 'Dates prévues enregistrées. Aucune génération ni publication n’a été activée.');

                    return $this->redirectToRoute('app_series_calendar', ['id' => $id], 303);
                }
                $preview = $calendar->preview($series, $data);
            } catch (PublicationException $error) {
                $form->addError(new FormError($error->getMessage()));
                try {
                    $preview = $calendar->preview($series, $data);
                } catch (PublicationException) {
                    // Invalid dates remain in the form; the previously saved plan is untouched.
                }
            }
        }
        $view = $form->createView();
        if ($preview) {
            $view->children['snapshot']->vars['value'] = $preview['hash'];
        }

        return $this->render('series/calendar.html.twig', ['series' => $series, 'form' => $view, 'preview' => $preview, 'saved' => $saved], new Response(status: $form->isSubmitted() && !$form->isValid() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }
}
