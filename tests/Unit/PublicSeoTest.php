<?php

namespace App\Tests\Unit;

use App\Controller\SeoController;
use App\EventSubscriber\SeoSubscriber;
use App\Service\PublicSeo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicSeoTest extends TestCase
{
    public function testActivationIsRestrictedToTrustedOriginAndPublicRoutes(): void
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnMap([['app_home', [], 1, '/'], ['app_guide', [], 1, '/fonctionnement'], ['app_contact', [], 1, '/contact']]);
        $seo = new PublicSeo($router, 'https://vydflow.com', true);
        $request = Request::create('https://vydflow.com/');
        $request->attributes->set('_route', 'app_home');
        self::assertTrue($seo->indexable($request));
        $kernel = $this->createStub(HttpKernelInterface::class);
        $response = new Response();
        $subscriber = new SeoSubscriber($seo);
        $subscriber->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertFalse($response->headers->has('X-Robots-Tag'));
        $error = new Response(status: 404);
        $subscriber->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $error));
        self::assertSame('noindex, nofollow', $error->headers->get('X-Robots-Tag'));
        self::assertNull($seo->canonical('app_dashboard'));
        $controller = new SeoController();
        $xml = $controller->sitemap($request, $seo)->getContent();
        self::assertSame(3, substr_count($xml, '<loc>'));
        self::assertStringContainsString('https://vydflow.com/fonctionnement', $xml);
        self::assertStringNotContainsString('/studio', $xml);
        self::assertStringContainsString('Sitemap: https://vydflow.com/sitemap.xml', $controller->robots($request, $seo)->getContent());
        $request->attributes->set('_route', 'app_dashboard');
        self::assertFalse($seo->indexable($request));
        $request = Request::create('https://preview.example/');
        $request->attributes->set('_route', 'app_home');
        self::assertFalse($seo->indexable($request));
        self::assertStringNotContainsString('<loc>', $controller->sitemap($request, $seo)->getContent());
        $request = Request::create('https://vydflow.com/');
        $request->attributes->set('_route', 'app_home');
        self::assertFalse((new PublicSeo($router, 'https://vydflow.com', false))->indexable($request));
    }
}
