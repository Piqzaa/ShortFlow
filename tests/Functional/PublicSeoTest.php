<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicSeoTest extends WebTestCase
{
    public function testExplicitOpeningOnTrustedDomainWithoutDebug(): void
    {
        $previousEnv = $_ENV['SEO_INDEXING_ENABLED'] ?? null;
        $previousServer = $_SERVER['SEO_INDEXING_ENABLED'] ?? null;
        $_ENV['SEO_INDEXING_ENABLED'] = $_SERVER['SEO_INDEXING_ENABLED'] = '1';
        try {
            $client = static::createClient(['debug' => false]);
            $client->request('GET', 'https://vydflow.com/fonctionnement');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('meta[name="robots"][content="index, follow"]');
            self::assertFalse($client->getResponse()->headers->has('X-Robots-Tag'));
            $client->request('GET', 'https://vydflow.com/sitemap.xml');
            self::assertSame(3, substr_count($client->getResponse()->getContent(), '<loc>'));
            $client->request('GET', 'https://preview.example/fonctionnement');
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        } finally {
            static::ensureKernelShutdown();
            if (null === $previousEnv) {
                unset($_ENV['SEO_INDEXING_ENABLED']);
            } else {
                $_ENV['SEO_INDEXING_ENABLED'] = $previousEnv;
            }
            if (null === $previousServer) {
                unset($_SERVER['SEO_INDEXING_ENABLED']);
            } else {
                $_SERVER['SEO_INDEXING_ENABLED'] = $previousServer;
            }
        }
    }

    public function testPublicMetadataAndClosedIndexing(): void
    {
        $client = static::createClient();
        foreach (['/' => '', '/fonctionnement' => '/fonctionnement', '/contact' => '/contact'] as $path => $suffix) {
            $crawler = $client->request('GET', $path.'?utm_source=test');
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
            self::assertSame('https://vydflow.com'.$suffix, rtrim($crawler->filter('link[rel="canonical"]')->attr('href'), '/'));
            self::assertSame($crawler->filter('title')->text(), $crawler->filter('meta[property="og:title"]')->attr('content'));
            self::assertSame($crawler->filter('meta[name="description"]')->attr('content'), $crawler->filter('meta[property="og:description"]')->attr('content'));
        }
        $client->request('GET', '/connexion');
        self::assertSelectorNotExists('link[rel="canonical"]');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        $client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('<loc>', $client->getResponse()->getContent());
        $client->request('GET', '/robots.txt');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Sitemap:', $client->getResponse()->getContent());
        self::assertStringContainsString('Allow: /', $client->getResponse()->getContent());
        $client->request('GET', '/studio');
        self::assertResponseRedirects();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
    }
}
