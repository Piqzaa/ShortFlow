<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LegalPagesTest extends WebTestCase
{
    public function testDraftDocumentsArePublicConnectedAndNeverClaimToBeFinal(): void
    {
        $client = static::createClient();
        foreach (['/informations-legales', '/mentions-legales', '/cgu', '/cgv', '/confidentialite', '/cookies', '/suppression-donnees'] as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
            self::assertSelectorExists('main#contenu h1');
            self::assertSelectorTextContains('.legal-draft', 'Documents en préparation');
            self::assertSelectorCount(5, 'nav[aria-label="Informations légales"] a');
            self::assertSelectorExists('a[href="mailto:dev@alexbuild.fr"]');
        }
        $client->request('GET', '/mentions-legales');
        self::assertSelectorTextContains('.legal-copy', '10864041800019');
        self::assertSelectorTextContains('.legal-copy', 'Entrepreneur individuel');
        $client->request('GET', '/confidentialite');
        self::assertSelectorTextContains('.legal-copy', 'Instagram');
        self::assertSelectorTextContains('.legal-copy', 'purge automatique');
        $client->request('GET', '/cgv');
        self::assertSelectorTextContains('.legal-copy', 'Aucune commande');
        $client->request('GET', '/inscription');
        self::assertSelectorExists('form a[href="/confidentialite"]');
        self::assertSelectorExists('form a[href="/cgu"]');
        self::assertSelectorNotExists('input[type="checkbox"][name*="consent"]');
    }
}
