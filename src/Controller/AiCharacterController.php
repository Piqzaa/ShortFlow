<?php

namespace App\Controller;

use App\Entity\AiCharacter;
use App\Entity\User;
use App\Form\AiCharacterType;
use App\Service\AiCharacters;
use App\Service\AiReferenceStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AiCharacterController extends AbstractController
{
    #[Route('/studio/personnages', name: 'app_characters', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $archived = $request->query->getBoolean('archives');
        $repo = $em->getRepository(AiCharacter::class);
        $criteria = ['owner' => $user, 'archived' => $archived];
        $pages = max(1, (int) ceil($repo->count($criteria) / 12));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        return $this->render('character/index.html.twig', ['characters' => $repo->findBy($criteria, ['id' => 'DESC'], 12, ($page - 1) * 12), 'page' => $page, 'pages' => $pages, 'archived' => $archived], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/personnages/nouveau', name: 'app_character_new', methods: ['GET', 'POST'])]
    #[Route('/studio/personnages/{id}', name: 'app_character_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, #[CurrentUser] User $user, AiCharacters $characters, ?int $id = null): Response
    {
        $character = $id ? ($characters->owned($user, $id) ?? throw $this->createNotFoundException()) : null;
        $form = $this->createForm(AiCharacterType::class, ['name' => $character?->getName() ?? '', 'description' => $character?->getDescription() ?? '', 'revision' => (string) ($character?->getRevision() ?? 0)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $characters->save($user, $id, (int) $data['revision'], $data['name'], $data['description'] ?? '', $data['portrait'], $data['views'] ?? [], $data['clearViews'] ?? false);
                $this->addFlash('success', 'Personnage enregistré. Les préparations existantes restent conservées.');

                return $this->redirectToRoute('app_characters', status: 303);
            } catch (\RuntimeException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('character/edit.html.twig', ['character' => $character, 'characterForm' => $form], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/studio/personnages/{id}/archiver', name: 'app_character_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(int $id, Request $request, #[CurrentUser] User $user, AiCharacters $characters): Response
    {
        $characters->owned($user, $id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('character_archive_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $characters->archive($user, $id, $request->request->getInt('revision'), $request->request->getBoolean('archive'));
            $this->addFlash('success', 'État du personnage mis à jour. Ses images restent conservées.');
        } catch (\RuntimeException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_characters', ['archives' => $request->request->getBoolean('archive') ? 0 : 1], 303);
    }

    #[Route('/studio/personnages/{id}/image/{slot}', name: 'app_character_image', requirements: ['id' => '\d+', 'slot' => 'portrait|0|1|2'], methods: ['GET', 'HEAD'])]
    public function image(int $id, string $slot, #[CurrentUser] User $user, AiCharacters $characters, AiReferenceStorage $storage): Response
    {
        $character = $characters->owned($user, $id) ?? throw $this->createNotFoundException();
        $name = 'portrait' === $slot ? $character->getPortrait() : ($character->getViews()[(int) $slot] ?? null);
        if (!$name) {
            throw $this->createNotFoundException();
        }
        try {
            $path = $storage->path($user, $name);
        } catch (\RuntimeException) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path, headers: ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
