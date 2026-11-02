<?php

namespace App\Tests\Functional;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Entity\VideoAsset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class ResilienceTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('shortflow_test', $this->em->getConnection()->fetchOne('SELECT current_database()'));
        $this->em->getConnection()->beginTransaction();
        foreach (['youtube', 'instagram', 'tiktok', 'ai', 'story'] as $provider) {
            static::getContainer()->set($provider.'.http', new MockHttpClient(static function (): never {
                self::fail('No provider or inference HTTP is allowed in fallback tests.');
            }));
        }
        $this->owner = new User('fallback-'.bin2hex(random_bytes(6)).'@example.test');
        $this->owner->verify();
        $this->owner->setPassword('unused');
        $this->em->persist($this->owner);
        $this->em->flush();
        $this->client->loginUser($this->owner);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->clear();
        parent::tearDown();
    }

    public function testNativeSelectionWorksWhileBulkSelectionWaitsForScripts(): void
    {
        $video = new VideoAsset($this->owner, 'Source synthétique', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1000, 4, 96, 160, 'h264');
        $this->em->persist($video);
        $this->em->flush();
        $crawler = $this->client->request('GET', '/studio');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-selection-target="allControl"][hidden]');
        self::assertSelectorExists('input[name="videos[]"]:not([disabled])');
        $values = $crawler->selectButton('Préparer la sélection →')->form()->getPhpValues();
        $values['videos'] = [$video->getId()];
        $this->client->request('GET', '/studio/composer', $values);
        self::assertResponseRedirects(null, 303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', 'Source synthétique');

        $html = static::getContainer()->get('twig')->render('ai/_story_proposal.html.twig', ['writerAvailable' => true]);
        $view = new \Symfony\Component\DomCrawler\Crawler($html);
        self::assertCount(1, $view->filter('[data-story-writer-target="generate"][disabled]'));
        self::assertStringContainsString('nécessite JavaScript', $view->filter('[data-story-writer-target="javascript"]')->text());
    }

    public function testNativePlanActionPreservesManualTextWithoutInferring(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        $values = $crawler->selectButton('Ajouter un plan +')->form()->getPhpValues();
        $values['ai_idea'] = array_replace($values['ai_idea'], [
            'prompt' => 'Milo, un chat blanc, découvre un carton.', 'model' => 'kling-25', 'target' => '10', 'planCount' => '2',
            'shots' => [['prompt' => 'A white cat sniffs a cardboard box. Fixed camera.', 'seconds' => '5', 'promptOnly' => '1']],
        ]);
        $this->client->request('POST', '/studio/creation', $values);
        self::assertResponseRedirects(null, 303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame('Milo, un chat blanc, découvre un carton.', $this->client->getCrawler()->filter('#ai_idea_prompt')->text());
        self::assertSame('A white cat sniffs a cardboard box. Fixed camera.', $this->client->getCrawler()->filter('#ai_idea_shots_0_prompt')->text());
        self::assertSelectorCount(2, '[data-shot-editor-target="list"] > [data-shot-editor-row]');
        self::assertSelectorExists('[data-reference-picker-target="file"][disabled]');
        self::assertSelectorExists('[data-reference-picker-target="fit"][disabled]');
        self::assertSelectorTextContains('[data-reference-picker-target="status"]', 'nécessite JavaScript');
    }

    public function testDirectGenerationPostStillRequiresCsrfAndCostConsent(): void
    {
        $job = new AiGeneration($this->owner, 'Essai de garde', 'A white cat sits in a box.', 'kling-25', 5, '9:16', 35);
        $this->em->persist($job);
        $this->em->flush();
        $url = '/studio/ia/generations/'.$job->getId();
        $before = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages');
        $this->client->request('POST', $url, ['confirm' => '1']);
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', $url);
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', $url, ['_token' => $token]);
        self::assertResponseRedirects($url, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Confirme le coût fournisseur');
        self::assertSame('prepared', $this->em->find(AiGeneration::class, $job->getId())->getStatus());
        self::assertSame($before, $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }
}
