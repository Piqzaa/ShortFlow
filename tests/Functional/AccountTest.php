<?php

namespace App\Tests\Functional;

use App\Entity\AccountToken;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['REMOTE_ADDR' => '192.0.2.'.random_int(50, 240)]);
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM account_token');
        $db->executeStatement('DELETE FROM app_user');
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testAnonymousCannotAccessStudio(): void
    {
        $this->client->request('GET', '/studio');
        self::assertResponseRedirects('http://localhost/connexion');
    }

    public function testRegistrationHashesPasswordAndDuplicateIsNeutral(): void
    {
        foreach (['Creator@example.fr', 'creator@example.fr'] as $email) {
            $crawler = $this->client->request('GET', '/inscription');
            $this->client->submit($crawler->selectButton('Créer mon compte')->form([
                'registration[email]' => $email,
                'registration[password][first]' => 'une phrase vraiment longue',
                'registration[password][second]' => 'une phrase vraiment longue',
            ]));
            self::assertResponseRedirects('/connexion', 303);
            $this->client->followRedirect();
            self::assertSelectorTextContains('.account-notice.success', 'Si cette adresse');
        }
        $user = static::getContainer()->get(UserRepository::class)->findByEmail('CREATOR@example.fr');
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'une phrase vraiment longue'));
        self::assertNotSame('une phrase vraiment longue', $user->getPassword());
        self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM app_user'));
        self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM account_token'));
    }

    public function testRegistrationRejectsMissingCsrfAndWeakPassword(): void
    {
        $this->client->request('POST', '/inscription', ['registration' => ['email' => 'invalid@example.fr', 'password' => ['first' => 'une phrase vraiment longue', 'second' => 'une phrase vraiment longue']]]);
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->request('GET', '/inscription');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form(['registration[email]' => 'invalid@example.fr', 'registration[password][first]' => 'short', 'registration[password][second]' => 'short']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', '12 caractères');
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM app_user'));
    }

    public function testLoginIsCaseInsensitiveAndLogoutRequiresCsrf(): void
    {
        $this->createUser('alice@example.fr');
        $this->login(' ALICE@example.fr ');
        self::assertResponseRedirects('/studio');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.studio-profile', 'alice@example.fr');
        self::assertSelectorNotExists('.studio-verification');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'));
        $logout = $crawler->selectButton('Se déconnecter ↗')->form();
        $this->client->request('POST', '/deconnexion');
        self::assertResponseStatusCodeSame(403);
        $this->client->submit($logout);
        self::assertResponseRedirects('/');
        $this->client->request('GET', '/studio');
        self::assertResponseRedirects('http://localhost/connexion');
    }

    public function testLoginRejectsMissingCsrfWrongPasswordAndThrottles(): void
    {
        $this->createUser('alice@example.fr');
        $this->client->request('POST', '/connexion', ['_username' => 'alice@example.fr', '_password' => 'une phrase vraiment longue']);
        self::assertResponseRedirects('/connexion');
        $this->client->request('GET', '/studio');
        self::assertResponseRedirects('http://localhost/connexion');
        for ($i = 0; $i < 6; ++$i) {
            $this->login('alice@example.fr', 'incorrect');
        }
        $this->login('alice@example.fr');
        self::assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        self::assertSelectorExists('.account-notice.error');
    }

    public function testVerificationIsExplicitSingleUseAndPurposeBound(): void
    {
        $this->createUser('alice@example.fr', false);
        $raw = $this->createToken('alice@example.fr', AccountToken::VERIFY);
        $this->client->request('GET', '/compte/verifier');
        self::assertFalse(static::getContainer()->get(UserRepository::class)->findByEmail('alice@example.fr')->isVerified());
        $this->redeem('/compte/nouveau-mot-de-passe', 'Enregistrer mon mot de passe', $raw, 'ma nouvelle phrase secrète');
        self::assertResponseStatusCodeSame(422);
        $this->redeem('/compte/verifier', 'Confirmer mon adresse', $raw);
        self::assertResponseRedirects('/connexion', 303);
        self::assertTrue(static::getContainer()->get(UserRepository::class)->findByEmail('alice@example.fr')->isVerified());
        $this->redeem('/compte/verifier', 'Confirmer mon adresse', $raw);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'déjà utilisé');
    }

    public function testExpiredTokenAndMissingCsrfAreRejected(): void
    {
        $this->createUser('alice@example.fr', false);
        $raw = $this->createToken('alice@example.fr', AccountToken::VERIFY);
        $this->client->request('POST', '/compte/verifier', ['account_token' => ['token' => $raw]]);
        self::assertResponseStatusCodeSame(422);
        static::getContainer()->get(Connection::class)->executeStatement("UPDATE account_token SET expires_at = CURRENT_TIMESTAMP - INTERVAL '1 minute'");
        $this->redeem('/compte/verifier', 'Confirmer mon adresse', $raw);
        self::assertResponseStatusCodeSame(422);
        self::assertFalse(static::getContainer()->get(UserRepository::class)->findByEmail('alice@example.fr')->isVerified());
    }

    public function testResetChangesOnlyOwnerAndInvalidatesAllResetLinksAndSession(): void
    {
        $this->createUser('alice@example.fr');
        $this->createUser('bob@example.fr');
        $raw = $this->createToken('alice@example.fr', AccountToken::RESET);
        $older = $this->createToken('alice@example.fr', AccountToken::RESET);
        $this->login('alice@example.fr');
        $this->client->followRedirect();
        $this->redeem('/compte/nouveau-mot-de-passe', 'Enregistrer mon mot de passe', $raw, 'ma nouvelle phrase secrète');
        self::assertResponseRedirects('/connexion', 303);
        $this->client->request('GET', '/studio');
        self::assertResponseRedirects('http://localhost/connexion');
        $this->redeem('/compte/nouveau-mot-de-passe', 'Enregistrer mon mot de passe', $older, 'encore une phrase très longue');
        self::assertResponseStatusCodeSame(422);
        $users = static::getContainer()->get(UserRepository::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($users->findByEmail('alice@example.fr'), 'ma nouvelle phrase secrète'));
        self::assertTrue($hasher->isPasswordValid($users->findByEmail('bob@example.fr'), 'une phrase vraiment longue'));
        $this->login('alice@example.fr', 'ma nouvelle phrase secrète');
        self::assertResponseRedirects('/studio');
    }

    public function testRecoveryResponseIsNeutralAndIpLimited(): void
    {
        $this->createUser('alice@example.fr');
        foreach (['alice@example.fr', 'absent@example.fr'] as $email) {
            $crawler = $this->client->request('GET', '/mot-de-passe-oublie');
            $this->client->submit($crawler->selectButton('Recevoir un lien')->form(['account_email[email]' => $email]));
            self::assertResponseRedirects('/connexion', 303);
            $this->client->followRedirect();
            self::assertSelectorTextContains('.account-notice.success', 'Si un compte correspond');
        }
        for ($i = 0; $i < 4; ++$i) {
            $crawler = $this->client->request('GET', '/mot-de-passe-oublie');
            $this->client->submit($crawler->selectButton('Recevoir un lien')->form(['account_email[email]' => 'absent@example.fr']));
            self::assertResponseStatusCodeSame(3 === $i ? 429 : 303);
        }
    }

    public function testEmailContainsTrustedOriginFragmentAndOnlyHashIsStored(): void
    {
        $this->createUser('alice@example.fr');
        $crawler = $this->client->request('GET', '/mot-de-passe-oublie', server: ['HTTP_HOST' => 'malicious.example']);
        $this->client->submit($crawler->selectButton('Recevoir un lien')->form(['account_email[email]' => 'alice@example.fr']));
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        $body = $email->getTextBody();
        self::assertStringContainsString('http://localhost:8000/compte/nouveau-mot-de-passe#token=', $body);
        self::assertStringNotContainsString('malicious.example', $body);
        preg_match('/#token=([a-f0-9]{64})/', $body, $match);
        self::assertSame(hash('sha256', $match[1]), static::getContainer()->get(Connection::class)->fetchOne('SELECT token_hash FROM account_token'));
    }

    public function testUnverifiedAccountCannotLoginUntilEmailIsConfirmed(): void
    {
        $this->createUser('alice@example.fr', false);
        $this->login('alice@example.fr', 'incorrect');
        self::assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        self::assertSelectorTextNotContains('.account-notice.error', 'Confirme ton adresse');
        $this->login('alice@example.fr');
        self::assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.account-notice.error', 'Confirme ton adresse email');
        self::assertSelectorExists('.account-notice.error a[href="/compte/renvoyer-confirmation"]');
        $this->client->request('GET', '/studio');
        self::assertResponseRedirects('http://localhost/connexion');
        $reset = $this->createToken('alice@example.fr', AccountToken::RESET);
        $this->redeem('/compte/nouveau-mot-de-passe', 'Enregistrer mon mot de passe', $reset, 'ma nouvelle phrase secrète');
        self::assertResponseRedirects('/connexion', 303);
        self::assertFalse(static::getContainer()->get(UserRepository::class)->findByEmail('alice@example.fr')->isVerified());
        $this->login('alice@example.fr', 'ma nouvelle phrase secrète');
        self::assertResponseRedirects('/connexion');
        $raw = $this->createToken('alice@example.fr', AccountToken::VERIFY);
        $this->redeem('/compte/verifier', 'Confirmer mon adresse', $raw);
        self::assertResponseRedirects('/connexion', 303);
        $this->login('alice@example.fr', 'ma nouvelle phrase secrète');
        self::assertResponseRedirects('/studio');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testExistingUnverifiedSessionCannotAccessStudio(): void
    {
        $this->createUser('alice@example.fr', false);
        $user = static::getContainer()->get(UserRepository::class)->findByEmail('alice@example.fr');
        $this->client->loginUser($user);
        $this->client->request('GET', '/studio');
        self::assertResponseStatusCodeSame(403);
    }

    private function createUser(string $email, bool $verified = true): void
    {
        $user = new User($email);
        if ($verified) {
            $user->verify();
        }
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'une phrase vraiment longue'));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();
    }

    private function createToken(string $email, string $purpose): string
    {
        $raw = bin2hex(random_bytes(32));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist(new AccountToken(static::getContainer()->get(UserRepository::class)->findByEmail($email), hash('sha256', $raw), $purpose));
        $em->flush();

        return $raw;
    }

    private function login(string $email, string $password = 'une phrase vraiment longue'): void
    {
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => $email, '_password' => $password]));
    }

    private function redeem(string $path, string $button, string $raw, ?string $password = null): void
    {
        $crawler = $this->client->request('GET', $path);
        $values = ['account_token[token]' => $raw];
        if (null !== $password) {
            $values['account_token[password][first]'] = $password;
            $values['account_token[password][second]'] = $password;
        }
        $this->client->submit($crawler->selectButton($button)->form($values));
    }
}
