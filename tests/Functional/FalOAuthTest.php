<?php

namespace App\Tests\Functional;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\AiGenerations;
use App\Service\AiKeys;
use App\Service\FalMcpClient;
use App\Service\FalOAuth;
use App\Service\PrivateVideoStorage;
use App\Service\TokenVault;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FalOAuthTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private int $registrations = 0;
    private int $submissions = 0;
    private int $refreshes = 0;
    private string $activeAccount = 'account-one';
    private bool $pending = false;
    private bool $revoked = false;
    /** @var list<string> */ private array $files = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        $this->owner = new User('mcp-owner@example.test');
        $this->owner->verify();
        $this->owner->setPassword('unused');
        $this->em()->persist($this->owner);
        $this->em()->flush();
        $this->client->loginUser($this->owner);
        static::getContainer()->get('cache.rate_limiter')->clear();
        static::getContainer()->set('ai.http', new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame(0, $options['max_redirects']);
            if (str_contains($url, 'oauth-protected-resource')) {
                return $this->json(['resource' => FalMcpClient::RESOURCE, 'authorization_servers' => ['https://authkit.fal.ai']]);
            }
            if (str_contains($url, 'oauth-authorization-server')) {
                return $this->json(['issuer' => 'https://authkit.fal.ai', 'device_authorization_endpoint' => 'https://authkit.fal.ai/oauth2/device_authorization', 'registration_endpoint' => 'https://authkit.fal.ai/oauth2/register', 'token_endpoint' => 'https://authkit.fal.ai/oauth2/token']);
            }
            if (str_ends_with($url, '/oauth2/register')) {
                ++$this->registrations;
                self::assertSame('none', json_decode($options['body'], true)['token_endpoint_auth_method']);

                return $this->json(['client_id' => 'synthetic-client']);
            }
            if (str_ends_with($url, '/device_authorization')) {
                return $this->json(['device_code' => 'private-device-secret', 'user_code' => 'ABCD-EFGH', 'verification_uri' => 'https://authkit.fal.ai/activate', 'expires_in' => 600, 'interval' => 5]);
            }
            if (str_ends_with($url, '/oauth2/token')) {
                parse_str($options['body'], $body);
                self::assertSame('synthetic-client', $body['client_id']);
                if ('refresh_token' === $body['grant_type']) {
                    ++$this->refreshes;
                    self::assertSame('private-refresh-secret', $body['refresh_token']);
                    if ($this->revoked) {
                        return $this->json(['error' => 'invalid_grant'], 400);
                    }
                } elseif ($this->pending) {
                    return $this->json(['error' => 'authorization_pending'], 400);
                }

                return $this->json(['access_token' => 'private-access-secret', 'refresh_token' => 'private-refresh-secret', 'expires_in' => 3600, 'token_type' => 'Bearer']);
            }
            if (FalMcpClient::RESOURCE === $url) {
                self::assertSame('POST', $method);
                self::assertContains('Authorization: Bearer private-access-secret', $options['headers']);
                $body = json_decode($options['body'], true, 64, JSON_THROW_ON_ERROR);
                if ('initialize' === $body['method']) {
                    return $this->json(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['protocolVersion' => '2025-03-26']], 200, ['Mcp-Session-Id: synthetic-session']);
                }
                if ('notifications/initialized' === $body['method']) {
                    return new MockResponse('', ['http_code' => 202]);
                }
                self::assertContains('Mcp-Session-Id: synthetic-session', $options['headers']);
                $arguments = $body['params']['arguments'];
                $data = match ($body['params']['name']) {
                    'check_account_status' => ['status' => 'ready', 'account' => ['id' => $this->activeAccount]],
                    'submit_job' => $this->submitted($arguments),
                    'check_job' => ['status' => 'COMPLETED'],
                    'get_job_result' => ['video' => ['url' => 'https://v3.fal.media/mcp-result.mp4']],
                    default => throw new \LogicException('Unexpected MCP tool.'),
                };

                return $this->json(['jsonrpc' => '2.0', 'id' => 2, 'result' => ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_THROW_ON_ERROR)]]]]);
            }
            self::assertSame('https://v3.fal.media/mcp-result.mp4', $url);
            self::assertFalse(isset($options['normalized_headers']['authorization']));

            return new MockResponse(file_get_contents(dirname(__DIR__).'/Fixtures/library-valid.mp4'));
        }));
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function vault(): TokenVault
    {
        return new TokenVault($_ENV['AI_TOKEN_KEY']);
    }

    private function json(array $data, int $status = 200, array $headers = []): MockResponse
    {
        return new MockResponse(json_encode($data, JSON_THROW_ON_ERROR), ['http_code' => $status, 'response_headers' => $headers]);
    }

    private function submitted(array $arguments): array
    {
        ++$this->submissions;
        self::assertSame('fal-ai/kling-video/v2.5-turbo/pro/text-to-video', $arguments['endpoint_id']);
        self::assertSame('5', $arguments['input']['duration']);
        self::assertFalse($arguments['store_payload']);

        return ['request_id' => 'mcp-job', 'status_url' => 'https://queue.fal.run/fal-ai/kling-video/requests/mcp-job/status?account_receipt=private-receipt', 'response_url' => 'https://queue.fal.run/fal-ai/kling-video/requests/mcp-job?account_receipt=private-receipt'];
    }

    private function connect(): void
    {
        $crawler = $this->client->request('GET', '/studio/connexions/ia/fal');
        $this->client->submit($crawler->selectButton('Connecter mon compte Fal')->form());
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.ai-device-code', 'ABCD-EFGH');
        self::assertStringNotContainsString('private-device-secret', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('private-device-secret', $this->client->getRequest()->getSession()->get('fal_oauth'));
        $this->client->submit($crawler->selectButton('J’ai autorisé, vérifier la connexion')->form());
        self::assertResponseRedirects('/studio/connexions/ia', 303);
        $this->client->followRedirect();
        $this->owner = $this->em()->find(User::class, $this->owner->getId());
    }

    public function testBrowserAuthorizationPreservesTheApiKeyAndNeverRedisplaysPrivateTokens(): void
    {
        static::getContainer()->get(AiKeys::class)->save($this->owner, 'synthetic-fal-api-key-preserved');
        $this->client->request('POST', '/studio/connexions/ia/fal', ['intent' => 'start']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->registrations);
        $this->connect();
        self::assertSame(1, $this->registrations);
        self::assertSame('synthetic-fal-api-key-preserved', static::getContainer()->get(AiKeys::class)->plain($this->owner));
        $connection = static::getContainer()->get(FalOAuth::class)->connection($this->owner);
        self::assertNotNull($connection);
        self::assertStringNotContainsString('private-access-secret', $connection->getEncryptedKey());
        self::assertStringNotContainsString('private-refresh-secret', $this->client->getResponse()->getContent());
        self::assertSame(0, $this->submissions);
    }

    public function testMcpRefreshAndPrivateImportKeepTheOriginalReceiptAndNeverResubmit(): void
    {
        $this->connect();
        $this->expireAccess();
        $job = $this->job();
        $generations = static::getContainer()->get(AiGenerations::class);
        $generations->confirm($this->owner, $job->getId());
        self::assertSame('account-one', $job->getAccessAccount());
        self::assertSame(1, $this->refreshes);
        $generations->process($job->getId());
        self::assertSame('processing', $job->getStatus());
        self::assertStringNotContainsString('private-receipt', json_encode($job->getRemote()));
        $this->activeAccount = 'another-active-account';
        $generations->process($job->getId());
        self::assertSame('available', $job->getStatus());
        self::assertSame($this->owner->getId(), $job->getVideo()->getOwner()->getId());
        $this->files[] = static::getContainer()->get(PrivateVideoStorage::class)->path($job->getVideo()->getStorageName());
        $generations->process($job->getId());
        self::assertSame(1, $this->submissions);
        self::assertSame(1, $this->em()->getRepository(VideoAsset::class)->count([]));
    }

    public function testChangedActiveAccountBlocksBeforeThePaidCall(): void
    {
        $this->connect();
        $job = $this->job();
        $generations = static::getContainer()->get(AiGenerations::class);
        $generations->confirm($this->owner, $job->getId());
        $this->activeAccount = 'account-two';
        $generations->process($job->getId());
        self::assertSame('review', $job->getStatus());
        self::assertStringContainsString('compte MCP actif', $job->getIssue());
        self::assertSame(0, $this->submissions);
    }

    public function testRevokedRefreshStopsQueuedWorkWithoutLosingTheEntityManager(): void
    {
        $this->connect();
        $job = $this->job();
        $generations = static::getContainer()->get(AiGenerations::class);
        $generations->confirm($this->owner, $job->getId());
        $this->expireAccess();
        $this->revoked = true;
        $generations->process($job->getId());
        self::assertSame('waiting_key', $job->getStatus());
        self::assertTrue($this->em()->isOpen());
        self::assertSame(0, $this->submissions);
    }

    private function job(): AiGeneration
    {
        $job = new AiGeneration($this->owner, 'MCP privé', 'A forest', 'kling-25', 5, '9:16', 35);
        $job->useMcp();
        $this->em()->persist($job);
        $this->em()->flush();

        return $job;
    }

    private function expireAccess(): void
    {
        $connection = static::getContainer()->get(FalOAuth::class)->connection($this->owner);
        $data = json_decode($this->vault()->decrypt($connection->getEncryptedKey()), true, 32, JSON_THROW_ON_ERROR);
        $data['expires_at'] = time() - 10;
        $connection->replace($this->vault()->encrypt(json_encode($data, JSON_THROW_ON_ERROR)));
        $this->em()->flush();
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
}
