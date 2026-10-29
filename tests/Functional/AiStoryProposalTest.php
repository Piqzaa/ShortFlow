<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AiStoryProposalTest extends WebTestCase
{
    public function testRequestedCountAndManualLayoutAreValidatedBeforeAnyInference(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $user = new User(bin2hex(random_bytes(5)).'@example.test');
        $user->verify();
        $user->setPassword('unused');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);
        static::getContainer()->get('cache.rate_limiter')->clear();
        static::getContainer()->set('story.http', new MockHttpClient(static function (): MockResponse {
            self::fail('Preparing the requested count must not infer text synchronously.');
        }));
        static::getContainer()->set(\App\Service\LocalStoryWriter::class, new \App\Service\LocalStoryWriter(static::getContainer()->get('story.http'), true, 'http://host.docker.internal:11434', 'synthetic'));
        $crawler = $client->request('GET', '/studio/creation');
        $payload = ['_token' => $crawler->filter('[data-story-writer-token-value]')->attr('data-story-writer-token-value'), 'idea' => 'Milo est un chat blanc qui ouvre une boîte puis traverse la pièce.', 'model' => 'kling-3', 'target' => 30, 'tone' => 'auto', 'fingerprint' => 'chosen-count'];
        $before = $db->fetchOne('SELECT count(*) FROM ai_generation');
        foreach ([['independent', 3, [10, 10, 10]], ['linked', 4, [8, 8, 7, 7]]] as [$continuity, $count, $expected]) {
            $client->request('POST', '/studio/creation/scenario', $payload + ['continuity' => $continuity, 'planCount' => $count]);
            self::assertResponseStatusCodeSame(202);
            $result = json_decode($client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR);
            $job = $em->find(\App\Entity\StoryProposal::class, $result['id']);
            self::assertSame($expected, $job->getInput()['durations']);
            self::assertSame($continuity, $job->getInput()['continuity']);
            $job->fail('Synthetic test completed without inference.');
            $em->flush();
        }
        foreach ([['planCount' => 3, 'layout' => '15+15'], ['planCount' => 0], ['planCount' => 9], ['planCount' => 7, 'model' => 'kling-25']] as $invalid) {
            $client->request('POST', '/studio/creation/scenario', array_replace($payload + ['continuity' => 'independent'], $invalid));
            self::assertResponseStatusCodeSame(422);
        }
        self::assertSame($before, $db->fetchOne('SELECT count(*) FROM ai_generation'));
        $db->executeStatement("DELETE FROM messenger_messages WHERE queue_name = 'story_writing'");
        $db->executeStatement('DELETE FROM app_user WHERE id = ?', [$user->getId()]);
    }

    /** @param array<string,mixed>|null $queued
     * @return array<string,mixed>
     */
    private function finishProposal(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, ?array $queued = null): array
    {
        if (!$queued) {
            self::assertResponseStatusCodeSame(202);
        }
        $job = $queued ?? json_decode($client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame('queued', $job['status']);
        static::getContainer()->get(\App\Service\StoryProposals::class)->process($job['id']);
        static::getContainer()->get(\App\Service\StoryProposals::class)->process($job['id']);
        $client->request('GET', $job['url']);
        self::assertResponseIsSuccessful();
        $result = json_decode($client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame('ready', $result['status']);
        self::assertSame('story', $result['kind']);
        self::assertNotNull($result['startedAt']);
        // PostgreSQL timestamps may round a persisted phase to the next second.
        self::assertLessThanOrEqual(1, (new \DateTimeImmutable($result['startedAt']))->getTimestamp() - (new \DateTimeImmutable($result['observedAt']))->getTimestamp());

        return $result['result'];
    }

    public function testProposalIsPrivateAndDoesNotCreateAGeneration(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('shortflow_test', static::getContainer()->get(Connection::class)->fetchOne('SELECT current_database()'));
        $user = new User(bin2hex(random_bytes(5)).'@example.test');
        $user->verify();
        $user->setPassword('unused');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);
        static::getContainer()->get('cache.rate_limiter')->clear();
        $client->request('GET', '/studio/creation');
        $crawler = $client->getCrawler();
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM ai_credential WHERE owner_id = ?', [$user->getId()]));
        self::assertSelectorExists('[data-guided-model-picker][data-creation-target="picker"]');
        self::assertSelectorTextContains('[data-creation-target="accessDetails"]', 'sans clé vidéo');
        self::assertSelectorExists('[data-story-writer-target="elapsed"][aria-live="off"]');
        self::assertSelectorNotExists('progress[data-story-writer-target="progress"]');
        $token = $crawler->filter('[data-story-writer-token-value]')->attr('data-story-writer-token-value');
        $calls = 0;
        $suspicious = false;
        static::getContainer()->set('story.http', new MockHttpClient(function (string $method, string $url, array $options) use (&$calls, &$suspicious): MockResponse {
            ++$calls;
            self::assertSame('POST', $method);
            self::assertSame('http://host.docker.internal:11434/api/chat', $url);
            $body = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
            self::assertFalse($body['stream']);
            if (3 === $calls) {
                $brief = json_decode($body['messages'][1]['content'], true, 32, JSON_THROW_ON_ERROR);
                self::assertSame('white cat', $brief['context']['characters']);
                self::assertCount(1, $brief['context']['previous']);
            }
            $data = 1 === $calls % 2 ? ['title' => 'Milo et le robot', 'summary' => 'Milo poursuit le robot puis s’installe dessus.', 'identity' => 'white playful cat', 'beats' => ['Milo découvre le robot.', 'Milo poursuit le robot.', 'Milo monte sur le robot.']] : ['identity' => 'White playful cat Milo', 'shots' => [['seconds' => 10, 'prompt' => 'The same white cat Milo discovers a small robot vacuum in a sunny living room.'], ['seconds' => 10, 'prompt' => 'The same white cat Milo chases the robot vacuum around a chair in the living room.'], ['seconds' => 10, 'prompt' => 'The same white cat Milo sits proudly on the robot vacuum, riding it across the room.']]];
            if (isset($data['shots'])) {
                $data['identity'] .= $suspicious ? ' wearing a leather collar' : '';
                $input = json_decode($body['messages'][1]['content'], true, 32, JSON_THROW_ON_ERROR);
                foreach ($data['shots'] as $i => &$shot) {
                    $shot['seconds'] = $input['seconds'][$i];
                }
                unset($shot);
            }

            return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($data, JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR));
        }));
        static::getContainer()->set(\App\Service\LocalStoryWriter::class, new \App\Service\LocalStoryWriter(static::getContainer()->get('story.http'), true, 'http://host.docker.internal:11434', 'synthetic'));
        $payload = ['_token' => $token, 'idea' => 'Milo mon chat blanc joueur', 'model' => 'kling-25', 'target' => '30', 'continuity' => 'independent', 'tone' => 'auto'];
        $before = static::getContainer()->get(Connection::class)->fetchOne('SELECT count(*) FROM ai_generation');
        $queuedBefore = (int) static::getContainer()->get(Connection::class)->fetchOne("SELECT count(*) FROM messenger_messages WHERE queue_name = 'story_writing'");
        $client->request('POST', '/studio/creation/scenario', $payload);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(0, $calls);
        $queued = json_decode($client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR);
        self::assertNull($queued['startedAt']);
        self::assertSame('story', $queued['kind']);
        $client->request('POST', '/studio/creation/scenario', $payload);
        self::assertResponseStatusCodeSame(202);
        self::assertSame($queued['id'], json_decode($client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR)['id']);
        self::assertSame($queuedBefore + 1, (int) static::getContainer()->get(Connection::class)->fetchOne("SELECT count(*) FROM messenger_messages WHERE queue_name = 'story_writing'"));
        $client->request('GET', '/studio/series');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $calls);
        $data = $this->finishProposal($client, $queued);
        self::assertSame('Milo et le robot', $data['title']);
        self::assertCount(3, $data['shots']);
        self::assertSame([], $data['warnings']);
        self::assertSame($before, static::getContainer()->get(Connection::class)->fetchOne('SELECT count(*) FROM ai_generation'));
        self::assertSame(2, $calls);
        $client->request('POST', '/studio/creation/scenario', array_replace($payload, ['_token' => 'bad']));
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/studio/creation/scenario', array_replace($payload, ['target' => 32]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(2, $calls);
        $other = new User(bin2hex(random_bytes(5)).'@example.test');
        $other->verify();
        $other->setPassword('unused');
        $em->persist($other);
        $series = new AiSeries($other, 'Private', 'private theme', '', '', ['model' => 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $em->persist($series);
        $episode = new AiEpisode($series, 1);
        $em->persist($episode);
        $em->flush();
        $client->request('POST', '/studio/creation/scenario', $payload + ['episode' => $episode->getId()]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(2, $calls);
        $client->loginUser($other);
        $client->request('GET', $queued['url']);
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/studio/creation/scenario/dernier');
        self::assertResponseIsSuccessful();
        self::assertSame('none', json_decode($client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR)['status']);
        $client->loginUser(static::getContainer()->get(EntityManagerInterface::class)->find(User::class, $user->getId()));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $series = new AiSeries($em->getReference(User::class, $user->getId()), 'Milo', 'Milo et les objets', '', 'white cat', $series->getRecipe());
        $em->persist($series);
        $em->persist(new AiEpisode($series, 1));
        $next = new AiEpisode($series, 2);
        $em->persist($next);
        $em->flush();
        $client->request('POST', '/studio/creation/scenario', $payload + ['episode' => $next->getId()]);
        $this->finishProposal($client);
        self::assertSame(4, $calls);
        $client->request('POST', '/studio/creation/scenario', array_replace($payload, ['model' => 'kling-3', 'layout' => '5+15+10']));
        $custom = $this->finishProposal($client);
        self::assertSame([5, 15, 10], array_column($custom['shots'], 'seconds'));
        $client->request('POST', '/studio/creation/scenario', $payload + ['layout' => '5+15+10']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(6, $calls);
        $suspicious = true;
        static::getContainer()->get('cache.rate_limiter')->clear();
        $client->request('POST', '/studio/creation/scenario', $payload);
        $flagged = $this->finishProposal($client);
        self::assertCount(1, $flagged['warnings']);
        self::assertStringContainsString('accessoire', $flagged['warnings'][0]);
        $client->request('GET', '/studio/creation/scenario/dernier');
        self::assertSame($flagged['warnings'], json_decode($client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR)['result']['warnings']);
        self::assertSame($before, static::getContainer()->get(Connection::class)->fetchOne('SELECT count(*) FROM ai_generation'));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $expired = new \App\Entity\StoryProposal($em->getReference(User::class, $user->getId()), 0, 'expired', []);
        (new \ReflectionProperty($expired, 'createdAt'))->setValue($expired, new \DateTimeImmutable('-11 minutes'));
        $expired->phase('story', new \DateTimeImmutable('-11 minutes'));
        $em->persist($expired);
        $em->flush();
        $client->request('GET', '/studio/creation/scenario/'.$expired->getId());
        self::assertResponseIsSuccessful();
        self::assertSame('failed', json_decode($client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR)['status']);
        static::getContainer()->get(\App\Service\StoryProposals::class)->process($expired->getId());
        self::assertSame(8, $calls);
        static::getContainer()->get(Connection::class)->executeStatement("DELETE FROM messenger_messages WHERE queue_name = 'story_writing'");
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM app_user WHERE id IN (?, ?)', [$other->getId(), $user->getId()]);
    }
}
