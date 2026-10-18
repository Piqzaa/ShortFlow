<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Service\ProfileAvatarStorage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProfileSettingsTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    /** @var list<string> */ private array $files = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        foreach (['owner', 'other'] as $key) {
            $user = new User($key.'-profile@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$key = $user;
        }
        $this->em()->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
        $this->client->loginUser($this->owner);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function testNameIsPrivateEscapedAndDoesNotChangeOtherAccountOrIdentity(): void
    {
        $crawler = $this->client->request('GET', '/studio/mon-compte');
        $this->client->submit($crawler->selectButton('Enregistrer mon profil')->form(['profile[displayName]' => '<b>Alex</b>']));
        self::assertResponseRedirects('/studio/mon-compte?section=profile', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.studio-profile strong', '<b>Alex</b>');
        self::assertStringNotContainsString('<b>Alex</b>', $this->client->getResponse()->getContent());
        self::assertSame('', $this->em()->find(User::class, $this->other->getId())->getDisplayName());
        self::assertSame('owner-profile@example.test', $this->em()->find(User::class, $this->owner->getId())->getEmail());
        self::assertSelectorExists('.account-sections a[aria-current="page"]');
    }

    public function testTimezonePreferenceIsValidatedAndUsedByCalendar(): void
    {
        $crawler = $this->client->request('GET', '/studio/mon-compte?section=preferences');
        $this->client->submit($crawler->selectButton('Enregistrer mes préférences')->form(['profile_preferences[timezone]' => 'America/Toronto']));
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/studio/calendrier');
        self::assertSelectorExists('option[value="America/Toronto"][selected]');
        $crawler = $this->client->request('GET', '/studio/mon-compte?section=preferences');
        $payload = $crawler->selectButton('Enregistrer mes préférences')->form()->getPhpValues();
        $payload['profile_preferences']['timezone'] = '../invalid';
        $this->client->request('POST', '/studio/mon-compte?section=preferences', $payload);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('America/Toronto', $this->em()->find(User::class, $this->owner->getId())->getTimezone());
    }

    public function testAvatarIsNormalizedPrivateAndRemovalRevokesItsUrl(): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'vf-avatar-');
        copy(dirname(__DIR__, 2).'/public/media/creators/craft-thumb.webp', $temp);
        $this->files[] = $temp;
        $crawler = $this->client->request('GET', '/studio/mon-compte');
        $payload = $crawler->selectButton('Enregistrer mon profil')->form()->getPhpValues();
        $this->client->request('POST', '/studio/mon-compte', $payload, ['profile' => ['avatar' => new UploadedFile($temp, 'photo.webp', 'image/webp', null, true)]]);
        self::assertResponseStatusCodeSame(303);
        $user = $this->em()->find(User::class, $this->owner->getId());
        $name = $user->getAvatarName();
        self::assertNotNull($name);
        $storage = static::getContainer()->get(ProfileAvatarStorage::class);
        $path = $storage->path($name);
        $this->files[] = $path;
        self::assertStringContainsString('/avatars/test/', $path);
        $size = getimagesize($path);
        self::assertSame([256, 256], [$size[0], $size[1]]);
        self::assertSame('image/jpeg', $size['mime']);
        $url = '/studio/mon-compte/photo/'.$name;
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        self::assertStringContainsString('private', $this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->loginUser($this->other);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/studio/mon-compte');
        $this->client->submit($crawler->selectButton('Enregistrer mon profil')->form(['profile[removeAvatar]' => true]));
        self::assertResponseStatusCodeSame(303);
        self::assertFileDoesNotExist($path);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidImageAndCsrfCannotModifyProfile(): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'vf-avatar-bad-');
        file_put_contents($temp, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->files[] = $temp;
        $crawler = $this->client->request('GET', '/studio/mon-compte');
        $payload = $crawler->selectButton('Enregistrer mon profil')->form()->getPhpValues();
        $this->client->request('POST', '/studio/mon-compte', $payload, ['profile' => ['avatar' => new UploadedFile($temp, 'photo.png', 'image/png', null, true)]]);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->em()->find(User::class, $this->owner->getId())->getAvatarName());
        $this->client->request('POST', '/studio/mon-compte', ['profile' => ['displayName' => 'forged', '_token' => 'wrong']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('', $this->em()->find(User::class, $this->owner->getId())->getDisplayName());
    }
}
