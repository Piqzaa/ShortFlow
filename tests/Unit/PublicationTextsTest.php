<?php

namespace App\Tests\Unit;

use App\Service\LocalStoryWriter;
use App\Service\PublicationException;
use App\Service\PublicationTexts;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PublicationTextsTest extends TestCase
{
    public function testTextsAreSeparatedByNetworkAndHashtagsUseExistingApiTextFields(): void
    {
        $texts = new PublicationTexts();
        $profile = $texts->profile(['allowedHashtags' => '#Milo chats', 'excludedHashtags' => 'promotion']);
        $result = $texts->normalize(['youtube' => ['title' => 'Une découverte', 'description' => 'Milo ouvre la boîte.', 'hashtags' => ['Milo']], 'instagram' => ['caption' => 'Une petite pause avec Milo.', 'hashtags' => ['chats']], 'tiktok' => ['caption' => 'Que cache cette boîte ?', 'hashtags' => []]], ['youtube', 'instagram', 'tiktok'], $profile);
        self::assertSame('Une découverte', $result['youtube']['title']);
        self::assertSame("Milo ouvre la boîte.\n\n#Milo", $result['youtube']['description']);
        self::assertSame("Une petite pause avec Milo.\n\n#chats", $result['instagram']['caption']);
        self::assertSame('Que cache cette boîte ?', $result['tiktok']['caption']);
        self::assertArrayNotHasKey('privacy', $result['youtube']);
    }

    public function testExcludedOrUnlistedHashtagsAreRejectedWithoutSilentReplacement(): void
    {
        $texts = new PublicationTexts();
        $this->expectException(PublicationException::class);
        $texts->normalize(['instagram' => ['caption' => 'Milo se repose.', 'hashtags' => ['promotion']]], ['instagram'], $texts->profile(['excludedHashtags' => 'promotion']));
    }

    public function testOversizedTitleIsRejectedInsteadOfTruncated(): void
    {
        $texts = new PublicationTexts();
        $this->expectException(PublicationException::class);
        $texts->normalize(['youtube' => ['title' => str_repeat('é', 101), 'description' => 'Milo se repose.', 'hashtags' => []]], ['youtube'], []);
    }

    public function testModelCannotInjectPublicationSettingsIntoTheEditorialResult(): void
    {
        $texts = new PublicationTexts();
        $this->expectException(PublicationException::class);
        $texts->normalize(['tiktok' => ['caption' => 'Milo se repose.', 'hashtags' => [], 'privacy' => 'PUBLIC_TO_EVERYONE']], ['tiktok'], []);
    }

    public function testEditorialUsesTheConfiguredLocalModelAndStrictSchemaOnlyOnce(): void
    {
        $calls = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls): MockResponse {
            ++$calls;
            self::assertSame('http://localhost:11434/api/chat', $url);
            $body = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
            self::assertSame('configured-local-model', $body['model']);
            self::assertSame(['youtube'], array_keys($body['format']['properties']));
            self::assertStringContainsString('ne vois ni la vidéo', $body['messages'][0]['content']);

            return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode(['youtube' => ['title' => 'Milo découvre une boîte', 'description' => 'Une découverte toute simple.', 'hashtags' => ['Milo']]], JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR));
        });
        $result = (new LocalStoryWriter($http, true, 'http://localhost:11434', 'configured-local-model'))->publication(['title' => 'Milo'], [], ['youtube']);
        self::assertSame(1, $calls);
        self::assertStringContainsString('#Milo', $result['youtube']['description']);
    }
}
