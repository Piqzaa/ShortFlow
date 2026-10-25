<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\VideoAssetRepository;
use App\Service\AiGenerations;
use App\Service\NativeExtensions;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;

final class AiExtensionController extends AbstractController
{
    #[Route('/studio/videos/{id}/prolonger', name: 'app_ai_extend', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function prepare(int $id, Request $request, #[CurrentUser] User $user, VideoAssetRepository $videos, NativeExtensions $extensions, AiGenerations $generations, #[Autowire(service: 'limiter.publication')] RateLimiterFactory $limiter): Response
    {
        $video = $videos->owned($id, $user) ?? throw $this->createNotFoundException();
        $form = $this->createFormBuilder(['seconds' => 5, 'context' => 3], ['csrf_token_id' => 'extend_'.$id])
            ->add('seconds', IntegerType::class, ['label' => 'Secondes à ajouter', 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 2, max: 20)], 'attr' => ['min' => 2, 'max' => 20]])
            ->add('context', IntegerType::class, ['label' => 'Secondes de contexte à transmettre', 'help' => 'Cette portion de la vidéo est aussi facturée. Mouvement et son servent de référence.', 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 1, max: 20)], 'attr' => ['min' => 1, 'max' => 20]])
            ->add('prompt', TextareaType::class, ['label' => 'Comment continuer l’action ?', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 5000)], 'attr' => ['rows' => 4, 'maxlength' => 5000]])->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$limiter->create('ai-extension-'.$user->getId())->consume()->isAccepted()) {
                    throw new \RuntimeException('Trop de préparations rapprochées. Réessaie plus tard.');
                }
                $data = $form->getData();
                $hash = hash('sha256', serialize([$id, $data]));
                $previous = $request->getSession()->get('ai_extension_preparation', []);
                $job = ($previous['hash'] ?? '') === $hash ? $generations->owned((int) ($previous['id'] ?? 0), $user) : null;
                if (!$job || 'cancelled' === $job->getStatus()) {
                    $job = $extensions->prepare($user, $video, $data['seconds'], $data['context'], $data['prompt']);
                    $request->getSession()->set('ai_extension_preparation', ['hash' => $hash, 'id' => $job->getId()]);
                }

                return $this->redirectToRoute('app_ai_generation', ['id' => $job->getId()], 303);
            } catch (\RuntimeException|\InvalidArgumentException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('ai/extension.html.twig', ['video' => $video, 'form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/ia/extensions/{id}/abandonner', name: 'app_ai_extend_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, Request $request, #[CurrentUser] User $user, AiGenerations $generations, EntityManagerInterface $em): Response
    {
        $job = $generations->owned($id, $user) ?? throw $this->createNotFoundException();
        if ('ltx-23-extend' !== $job->getModel() || !$this->isCsrfTokenValid('extend_cancel_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->wrapInTransaction(function () use ($em, $user, $job): void {
            $em->lock($user, LockMode::PESSIMISTIC_WRITE);
            $em->refresh($job, LockMode::PESSIMISTIC_WRITE);
            if (in_array($job->getStatus(), ['prepared', 'queued', 'waiting_key'], true)) {
                $job->progress('cancelled', 'Préparation abandonnée avant envoi.');
            }
        });

        return $this->redirectToRoute('app_ai_generation', ['id' => $id], 303);
    }
}
