<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\AiSeries;
use App\Entity\AiShot;
use App\Entity\User;
use App\Service\AiEpisodeFlow;
use App\Service\AiKeys;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class AiSpeechNoticeTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $this->em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        foreach (['ai.http', 'story.http', 'youtube.http', 'instagram.http', 'tiktok.http'] as $id) {
            static::getContainer()->set($id, new MockHttpClient(static function (): never { throw new \LogicException('Reading the speech notice must not call any provider.'); }));
        }
        $this->owner = new User('speech-notice@example.test');
        $this->owner->verify();
        $this->owner->setPassword('unused');
        $this->em->persist($this->owner);
        $this->em->flush();
        static::getContainer()->get(AiKeys::class)->save($this->owner, 'synthetic-speech-test-key-never-real-12345');
        $this->client->loginUser($this->owner);
    }

    private function assertNoticeBefore(string $action): void
    {
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-speech-notice]', 'pas encore prises en charge');
        self::assertSelectorTextContains('[data-speech-notice]', 'qui parle, les mots exacts et leur langue');
        self::assertSelectorTextContains('[data-speech-notice]', 'synchronisation des lèvres');
        $notice = $this->client->getCrawler()->filter('[data-speech-notice]')->first();
        self::assertSame(0.0, $notice->evaluate('count(ancestor::*[@hidden or (self::details and not(@open))])')[0], 'The limitation must not be hidden in an optional panel.');
        $ordered = $this->client->getCrawler()->filter('[data-speech-notice],'.$action);
        self::assertGreaterThanOrEqual(2, $ordered->count());
        self::assertSame('aside', $ordered->first()->nodeName(), 'The limitation must precede the action.');
        self::assertSelectorTextContains('[data-quality-notice]', 'Même sans alerte');
        self::assertSelectorTextContains('[data-quality-notice]', 'descriptions et les prompts');
        $quality = $this->client->getCrawler()->filter('[data-quality-notice]')->first();
        self::assertSame(0.0, $quality->evaluate('count(ancestor::*[@hidden or (self::details and not(@open))])')[0]);
        self::assertSame('aside', $this->client->getCrawler()->filter('[data-quality-notice],'.$action)->first()->nodeName());
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testStudioExplainsTheLimitationBeforeScenarioProposal(): void
    {
        $this->client->request('GET', '/studio/creation');
        $this->assertNoticeBefore('[data-story-writer-target="generate"]');
        self::assertSelectorTextNotContains('[data-speech-notice]', 'Le modèle choisi produit des clips sans audio');
    }

    /** @return iterable<string,array{string,string}> */
    public static function generations(): iterable
    {
        foreach (['prepared', 'waiting_key'] as $state) {
            foreach (['kling-25', 'kling-3'] as $model) {
                yield $state.' '.$model => [$state, $model];
            }
        }
    }

    #[DataProvider('generations')]
    public function testIndividualGenerationAndRetryExplainAudioBeforeConsent(string $state, string $model): void
    {
        $prompt = 'Lea says in French: « Bonjour Hugo ! »';
        $job = new AiGeneration($this->owner, 'Dialogue à vérifier', $prompt, $model, 5, '9:16', 35);
        if ('waiting_key' === $state) {
            $job->progress($state);
        }
        $this->em->persist($job);
        $this->em->flush();
        $this->client->request('GET', '/studio/ia/generations/'.$job->getId());
        $this->assertNoticeBefore('input[name="confirm"]');
        $text = $this->client->getCrawler()->filter('[data-speech-notice]')->text();
        self::assertSame('kling-25' === $model, str_contains($text, 'clips sans audio'));
        self::assertSelectorExists('input[name="confirm"][required]');
        $this->em->refresh($job);
        self::assertSame($state, $job->getStatus());
        self::assertSame($prompt, $job->getPrompt());
        self::assertSame(35, $job->getEstimatedCents());
    }

    /** @return array<string,mixed> */
    private function recipe(string $model): array
    {
        return ['model' => $model, 'seconds' => 5, 'totalSeconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop'];
    }

    /** @return iterable<string,array{bool,bool}> */
    public static function episodes(): iterable
    {
        foreach ([false, true] as $standalone) {
            foreach ([false, true] as $mute) {
                yield ($standalone ? 'Studio' : 'series').($mute ? ' muted' : ' retained') => [$standalone, $mute];
            }
        }
    }

    #[DataProvider('episodes')]
    public function testCommonQuoteExplainsSpeechAndTheFinalAudioWithoutChangingTheQuote(bool $standalone, bool $mute): void
    {
        $recipe = $this->recipe('kling-3');
        $series = new AiSeries($this->owner, 'Deux amis', 'Deux amis se rencontrent.', 'Réaliste', '', $recipe);
        if (!$standalone) {
            $this->em->persist($series);
        }
        $episode = new AiEpisode($standalone ? $this->owner : $series, 1, 'Deux amis', '', $standalone ? $recipe : null);
        $episode->configurePreparation($recipe, 'Léa dit « Bonjour Hugo ! ».', 'single', $mute);
        $shot = new AiShot($episode, 1, 5);
        $shot->revise('Lea says in French: « Bonjour Hugo ! ». Static camera.', 5);
        $this->em->persist($episode);
        $this->em->persist($shot);
        $this->em->flush();
        $flow = static::getContainer()->get(AiEpisodeFlow::class);
        $before = $flow->quote($episode);
        $this->client->request('GET', '/studio/series/episodes/'.$episode->getId().'?review=1');
        $this->assertNoticeBefore('#story-quote input[name="confirm"]');
        $text = $this->client->getCrawler()->filter('#story-quote [data-speech-notice]')->text();
        self::assertSame($mute, str_contains($text, 'couper le son des clips au montage'));
        self::assertSelectorExists('#story-quote input[name="confirm"][required]');
        self::assertSame($before, $flow->quote($episode));
        self::assertStringContainsString('« Bonjour Hugo ! »', $shot->getPrompt());
        self::assertNull($episode->getConsentHash());
    }

    public function testRecurrenceExplainsSpeechOutsideTheCollapsedCostAuthorization(): void
    {
        $series = new AiSeries($this->owner, 'Deux amis', 'Deux amis se rencontrent.', 'Réaliste', '', $this->recipe('kling-25'));
        $this->em->persist($series);
        $this->em->flush();
        $this->client->request('GET', '/studio/series/'.$series->getId());
        $this->assertNoticeBefore('.series-recurrence input[type="checkbox"]');
        self::assertSelectorTextContains('.series-recurrence [data-speech-notice]', 'clips sans audio');
        self::assertSelectorTextContains('.series-recurrence', 'n’autorise aucun appel vidéo payant');
        self::assertSelectorTextContains('.series-recurrence [data-speech-notice]', 'Relis les prompts');
        self::assertSelectorTextNotContains('.series-recurrence [data-speech-notice]', 'sans relecture intermédiaire du scénario');
        self::assertSelectorExists('.series-recurrence input[name="series_recurrence[consent]"]');
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM series_recurrence'));
    }
}
