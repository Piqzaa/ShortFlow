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

    public function testStudioKeepsTheGenerationFormFreeOfSpeechWarnings(): void
    {
        $this->client->request('GET', '/studio/creation');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-speech-notice], [data-quality-notice]');
        self::assertSelectorExists('[data-preparation-target="recap"]');
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
        self::assertResponseIsSuccessful();
        if ('kling-25' === $model) {
            self::assertSelectorTextContains('[data-speech-notice]', 'Modèle sans audio.');
        } else {
            self::assertSelectorNotExists('[data-speech-notice]');
        }
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
        self::assertResponseIsSuccessful();
        if ($mute) {
            self::assertSelectorTextContains('#story-quote [data-speech-notice]', 'Montage sans le son des clips.');
        } else {
            self::assertSelectorNotExists('#story-quote [data-speech-notice]');
        }
        self::assertSelectorExists('#story-quote input[name="confirm"][required]');
        self::assertSame($before, $flow->quote($episode));
        self::assertStringContainsString('« Bonjour Hugo ! »', $shot->getPrompt());
        self::assertNull($episode->getConsentHash());
    }

    public function testNewSeriesUsesManualEpisodePreparation(): void
    {
        $series = new AiSeries($this->owner, 'Deux amis', 'Deux amis se rencontrent.', 'Réaliste', '', $this->recipe('kling-25'));
        $this->em->persist($series);
        $this->em->flush();
        $this->client->request('GET', '/studio/series/'.$series->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[name^="series_recurrence"]');
        self::assertSelectorExists('form#add-episodes button');
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM series_recurrence'));
    }
}
