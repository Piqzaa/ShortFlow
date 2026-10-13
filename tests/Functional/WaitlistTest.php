<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WaitlistTest extends WebTestCase
{
    public function testLandingAndPrivacyAreAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tes vidéos.');
        self::assertSelectorTextContains('title', 'VydFlow');
        self::assertSelectorExists('input[name="waitlist[_token]"]');
        $client->request('GET', '/confidentialite');
        self::assertResponseIsSuccessful();
    }

    public function testRegistrationIsPersistedAndDuplicateIsNeutral(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '192.0.2.10']);
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM waitlist_entry');
        foreach (['Creator@example.fr', 'creator@example.fr'] as $email) {
            $crawler = $client->request('GET', '/');
            $client->submit($crawler->selectButton('Rejoindre la liste')->form(['waitlist[email]' => $email]));
            self::assertResponseRedirects('/#rejoindre', 303);
            $client->followRedirect();
            self::assertSelectorExists('.notice.success');
        }
        self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM waitlist_entry'));
    }

    public function testInvalidEmailAndMissingCsrfAreRejected(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '192.0.2.20']);
        $crawler = $client->request('GET', '/');
        $client->submit($crawler->selectButton('Rejoindre la liste')->form(['waitlist[email]' => 'bad']));
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', '/', ['waitlist' => ['email' => 'valid@example.fr', 'website' => '']]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testHoneypotDoesNotPersistAndRateLimitApplies(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '192.0.2.30']);
        for ($i = 0; $i < 6; ++$i) {
            $crawler = $client->request('GET', '/');
            $client->submit($crawler->selectButton('Rejoindre la liste')->form(['waitlist[email]' => 'bot@example.fr', 'waitlist[website]' => 'spam']));
            self::assertResponseStatusCodeSame(5 === $i ? 429 : 303);
        }
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne("SELECT COUNT(*) FROM waitlist_entry WHERE email = 'bot@example.fr'"));
    }
}
