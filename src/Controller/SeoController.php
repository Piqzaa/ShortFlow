<?php

namespace App\Controller;

use App\Service\PublicSeo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController
{
    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(Request $request, PublicSeo $seo): Response
    {
        $request = clone $request;
        $request->attributes->set('_route', 'app_home');
        $body = "User-agent: *\nAllow: /\n";
        if ($seo->indexable($request)) {
            $body .= 'Sitemap: '.rtrim((string) $seo->canonical('app_home'), '/')."/sitemap.xml\n";
        }

        return new Response($body, headers: ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(Request $request, PublicSeo $seo): Response
    {
        $request = clone $request;
        $request->attributes->set('_route', 'app_home');
        $body = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        if ($seo->indexable($request)) {
            foreach (PublicSeo::ROUTES as $route) {
                $body .= '<url><loc>'.htmlspecialchars((string) $seo->canonical($route), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>';
            }
        }

        return new Response($body.'</urlset>', headers: ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
