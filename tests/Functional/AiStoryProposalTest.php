<?php

namespace App\Tests\Functional;

use App\Entity\StoryProposal;
use App\Entity\User;
use App\Service\StoryProposals;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class AiStoryProposalTest extends WebTestCase
{
    public function testRetiredScenarioRouteAndQueuedJobNeverCallTheWriter(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $user = new User('disabled-writer-'.bin2hex(random_bytes(5)).'@example.test');
        $user->verify();
        $user->setPassword('unused');
        $em->persist($user);
        $em->flush();
        $http = new MockHttpClient(static fn () => throw new \LogicException('Rendering must never probe or infer with the local writer.'));
        static::getContainer()->set('ai.http', $http);
        static::getContainer()->set(\App\Service\LocalStoryWriter::class, new \App\Service\LocalStoryWriter($http, true, 'http://localhost:11434', 'synthetic'));
        $client->loginUser($user);
        $client->request('GET', '/studio/creation');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-controller~="story-writer"]');
        self::assertSelectorExists('#ai-idea-form[data-shot-editor-model-value="{}"]');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/studio-direct-prompt.html', (string) $client->getResponse()->getContent());
        self::assertSelectorExists('[data-shot-editor-target="add"]');

        $legacy = new StoryProposal($user, 0, 'ancienne-proposition', [], 'story');
        $em->persist($legacy);
        $em->flush();
        $before = $db->fetchOne('SELECT COUNT(*) FROM messenger_messages');
        $client->request('POST', '/studio/creation/scenario');
        self::assertResponseStatusCodeSame(410);
        static::getContainer()->get(StoryProposals::class)->process($legacy->getId());
        self::assertSame('failed', $db->fetchOne('SELECT status FROM story_proposal WHERE id = ?', [$legacy->getId()]));
        self::assertSame($before, $db->fetchOne('SELECT COUNT(*) FROM messenger_messages'));

        $client->request('GET', '/studio/creation/scenario/'.$legacy->getId());
        self::assertResponseIsSuccessful();
        self::assertSame(0, $http->getRequestsCount());
        $client->request('GET', '/studio');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.workspace-import-cta[href^="/studio/bibliotheque/importer"]');
        self::assertSelectorExists('.dashboard-publication-links a[href="/studio/series/videos/nouvelle"]');
        self::assertSelectorExists('.dashboard-publication-links a[href="/studio/publications"]');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/studio-publication-entry.html', (string) $client->getResponse()->getContent());
        $db->executeStatement('DELETE FROM app_user WHERE id = ?', [$user->getId()]);
    }
}
