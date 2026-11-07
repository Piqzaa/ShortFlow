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
        $initial = array_replace($defaults, array_intersect_key($saved['rules'] ?? [], $defaults));
        $initial['dates'] = $saved ? array_map(static fn (array $slot): string => (new \DateTimeImmutable($slot['at']))->setTimezone(new \DateTimeZone($initial['timezone']))->format('Y-m-d\TH:i'), $saved['slots']) : array_fill(0, $initial['count'], '');
        $initial += ['revision' => (string) $series->getRevision(), 'snapshot' => ''];
        $intent = $request->request->getString('intent');
        $options = ['csrf_token_id' => 'series_calendar_'.$id, 'validation_groups' => 'fill' === $intent ? ['Default', 'rhythm'] : ['Default']];
        $form = $this->createForm(SeriesCalendarType::class, $initial, $options)->handleRequest($request);
        $preview = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                if ('save' === $intent) {
                    $calendar->save($owner, $series, $data);
                    $this->addFlash('success', 'Dates prévues enregistrées. Aucune génération ni publication n’a été activée.');

                    return $this->redirectToRoute('app_series_calendar', ['id' => $id], 303);
                }
                if ('resize' === $intent) {
                    $data['dates'] = array_pad(array_slice($data['dates'], 0, $data['count']), $data['count'], '');
                    $data['snapshot'] = '';
                    $form = $this->createForm(SeriesCalendarType::class, $data, $options);
                } else {
                    if ('fill' === $intent) {
                        $rhythm = $data;
                        unset($rhythm['dates']);
                        $generated = $calendar->preview($series, $rhythm);
                        $data['dates'] = array_map(static fn (array $slot): string => (new \DateTimeImmutable($slot['at']))->setTimezone(new \DateTimeZone($data['timezone']))->format('Y-m-d\TH:i'), $generated['slots']);
                        $form = $this->createForm(SeriesCalendarType::class, $data, $options);
                    }
                    $preview = $calendar->preview($series, $data);
                }
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

        $count = $form->get('count')->getData();
        $targets = $calendar->targets($series, is_int($count) && $count >= 1 && $count <= 90 ? $count : 4);

        return $this->render('series/calendar.html.twig', ['series' => $series, 'form' => $view, 'preview' => $preview, 'saved' => $saved, 'targets' => $targets, 'rhythmOpen' => 'fill' === $intent || ($form->isSubmitted() && !$form->isValid())], new Response(status: $form->isSubmitted() && !$form->isValid() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }
}
