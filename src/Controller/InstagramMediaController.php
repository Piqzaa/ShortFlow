<?php

namespace App\Controller;

use App\Entity\InstagramPublication;
use App\Service\InstagramMediaLink;
use App\Service\PrivateVideoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;

final class InstagramMediaController extends AbstractController
{
    #[Route('/media/instagram/{id}/{expires}/{signature}', name: 'app_instagram_media', requirements: ['id' => '\d+', 'expires' => '\d+', 'signature' => '[a-f0-9]{64}'], methods: ['GET', 'HEAD'])]
    public function media(int $id, int $expires, string $signature, EntityManagerInterface $em, InstagramMediaLink $links, PrivateVideoStorage $storage): BinaryFileResponse
    {
        $job = $em->find(InstagramPublication::class, $id);
        if (!$job || !$links->valid($job, $expires, $signature) || !$job->getSource() || $job->getSource()->getOwner()->getId() !== $job->getOwner()->getId()) {
            throw $this->createNotFoundException();
        }
        $path = $storage->path($job->getSource()->getStorageName());
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path, headers: ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
