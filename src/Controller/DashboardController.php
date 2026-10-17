<?php

namespace App\Controller;

use App\Entity\InstagramConnection;
use App\Entity\TikTokConnection;
use App\Entity\User;
use App\Entity\YouTubeConnection;
use App\Repository\VideoAssetRepository;
use App\Service\InstagramApi;
use App\Service\PublicationOverview;
use App\Service\TikTokApi;
use App\Service\YouTubeApi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    #[Route('/studio', name: 'app_dashboard', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, VideoAssetRepository $videos, PublicationOverview $publications, EntityManagerInterface $em, YouTubeApi $youtube, InstagramApi $instagram, TikTokApi $tiktok): Response
    {
        $recent = $videos->page($user, 1, 4);
        $youtubeConnection = $em->getRepository(YouTubeConnection::class)->findOneBy(['owner' => $user]);
        $instagramConnection = $em->getRepository(InstagramConnection::class)->findOneBy(['owner' => $user]);
        $tiktokConnection = $em->getRepository(TikTokConnection::class)->findOneBy(['owner' => $user]);
        $networks = [
            'TikTok' => !$tiktok->isReady() ? 'À configurer' : ($tiktokConnection ? ($tiktokConnection->isExpired() ? 'À reconnecter' : 'Connecté') : 'À connecter'),
            'YouTube' => !$youtube->isReady() ? 'À configurer' : ($youtubeConnection ? 'Connecté' : 'À connecter'),
            'Instagram' => !$instagram->isReady() ? 'À configurer' : ($instagramConnection ? ($instagramConnection->isExpired() ? 'À reconnecter' : ($instagramConnection->isExpiring() ? 'À renouveler' : 'Connecté')) : 'À connecter'),
        ];
        $counts = $publications->counts($user);
        $response = $this->render('studio/index.html.twig', ['videos' => $recent, 'usage' => $videos->usage($user), 'publishedPlatforms' => $videos->publishedPlatforms($user, $recent), 'counts' => $counts, 'attention' => $counts['attention'] ? $publications->page($user, state: 'attention', limit: 3)['items'] : [], 'active' => $counts['active'] ? $publications->page($user, state: 'active', limit: 3)['items'] : [], 'networks' => $networks]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
