<?php

namespace App\Tests\Unit;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Service\AiCatalog;
use App\Service\AiPlanInputs;
use App\Service\AiReferenceStorage;
use App\Service\AiShotPrompts;
use App\Service\FalVideoApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class AiSubjectsTest extends TestCase
{
    private string $directory;
    private User $owner;
    private AiReferenceStorage $storage;
    private const FRONT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg';
    private const VIEW = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.jpg';
    private const OBJECT = 'cccccccccccccccccccccccccccccccccccccccccccccccc.jpg';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/vydflow-subject-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/42', 0750, true);
        foreach ([self::FRONT, self::VIEW, self::OBJECT] as $name) {
            copy(dirname(__DIR__).'/Fixtures/reference.jpg', $this->directory.'/42/'.$name);
        }
        mkdir($this->directory.'/43');
        copy(dirname(__DIR__).'/Fixtures/reference.jpg', $this->directory.'/43/'.str_repeat('d', 48).'.jpg');
        $this->owner = new User('synthetic-subject@example.test');
        (new \ReflectionProperty(User::class, 'id'))->setValue($this->owner, 42);
        $this->storage = new AiReferenceStorage($this->directory);
    }

    protected function tearDown(): void
    {
        foreach ([self::FRONT, self::VIEW, self::OBJECT] as $name) {
            unlink($this->directory.'/42/'.$name);
        }
        rmdir($this->directory.'/42');
        unlink($this->directory.'/43/'.str_repeat('d', 48).'.jpg');
        rmdir($this->directory.'/43');
        rmdir($this->directory);
    }

    public function testNamedElementsKeepTheImageOrderAndTheirOwnViews(): void
    {
        $inputs = new AiPlanInputs(new AiCatalog(), $this->storage);
        $rows = [
            ['reference' => self::OBJECT, 'name' => 'Balle', 'role' => 'object', 'presence' => 'visible', 'views' => [self::VIEW]],
            ['reference' => self::FRONT, 'name' => 'Milo', 'role' => 'character', 'presence' => 'enters', 'views' => [self::VIEW]],
        ];
        $details = $inputs->subjectDetails($this->owner, 'kling-3-image', [self::FRONT, self::OBJECT], $rows);
        self::assertSame(['Milo', 'Balle'], array_column($details, 'name'));
        self::assertSame('enters', $details[0]['presence']);
        $prompt = (new AiShotPrompts(new AiCatalog()))->build('kling-3-image', 5, 'Milo enters the room and approaches the ball.', '', 'independent', 2, $details);
        self::assertStringContainsString('@Element1 identifies the character named "Milo"', $prompt);
        self::assertStringContainsString('@Element2 identifies the object named "Balle"', $prompt);
        self::assertStringContainsString('it enters during the action', $prompt);
    }

    public function testMissingKlingViewsAreRefusedBeforeAnyHttpRequest(): void
    {
        $job = new AiGeneration($this->owner, 'Synthetic', 'Milo enters.', 'kling-3-image', 5, '9:16', 84);
        $job->attachReference(self::OBJECT);
        $job->attachSubjects([self::FRONT]);
        $http = new MockHttpClient(static function (): never { throw new \LogicException('No HTTP request allowed.'); });
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('vues supplémentaires');
        (new FalVideoApi($http, new AiCatalog(), $this->storage))->submit($job, 'synthetic-never-real');
    }

    public function testViduPrimarySentinelIsNormalizedAndDoesNotInventExtraImages(): void
    {
        $details = (new AiPlanInputs(new AiCatalog(), $this->storage))->subjectDetails($this->owner, 'vidu-q1-subject', [self::OBJECT], [['reference' => 'primary', 'name' => 'Milo', 'role' => 'character', 'presence' => 'unspecified', 'views' => []]], self::FRONT);
        self::assertSame([self::FRONT, self::OBJECT], array_column($details, 'reference'));
        self::assertSame('Milo', $details[0]['name']);
        self::assertSame([], $details[0]['views']);
        self::assertSame('Sujet 2', $details[1]['name']);
    }

    public function testAViewFromAnotherOwnerCannotBeNamedByItsFilename(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AiPlanInputs(new AiCatalog(), $this->storage))->subjectDetails($this->owner, 'kling-3-image', [self::FRONT], [['reference' => self::FRONT, 'name' => 'Milo', 'role' => 'character', 'presence' => 'visible', 'views' => [str_repeat('d', 48).'.jpg']]]);
    }

    public function testTheFrontalImageCannotBeSilentlyRepeatedAsAnAdditionalView(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AiPlanInputs(new AiCatalog(), $this->storage))->subjectDetails($this->owner, 'kling-3-image', [self::FRONT], [['reference' => self::FRONT, 'views' => [self::FRONT]]]);
    }

    public function testViduCannotReceiveKlingStyleGroupedViews(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AiPlanInputs(new AiCatalog(), $this->storage))->subjectDetails($this->owner, 'vidu-q1-subject', [], [['reference' => 'primary', 'views' => [self::VIEW]]], self::FRONT);
    }

    public function testViduCountsThePrimaryImageInItsSevenImageLimit(): void
    {
        $names = [];
        foreach (['e', 'f', '1', '2', '3', '4', '5'] as $character) {
            $name = str_repeat($character, 48).'.jpg';
            copy(dirname(__DIR__).'/Fixtures/reference.jpg', $this->directory.'/42/'.$name);
            $names[] = $name;
        }
        try {
            $inputs = new AiPlanInputs(new AiCatalog(), $this->storage);
            self::assertCount(7, $inputs->subjectDetails($this->owner, 'vidu-q1-subject', array_slice($names, 0, 6), [], self::FRONT));
            $this->expectException(\RuntimeException::class);
            $inputs->subjectDetails($this->owner, 'vidu-q1-subject', $names, [], self::FRONT);
        } finally {
            foreach ($names as $name) {
                unlink($this->directory.'/42/'.$name);
            }
        }
    }

    public function testKlingLengthLimitIsAppliedToLegacyPromptsToo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('2500');
        (new AiCatalog())->validatePrompt('kling-3-image', str_repeat('a', 3000));
    }

    public function testSevenNamedViduReferencesLeaveRoomForTheWriterIdentityAndScene(): void
    {
        $details = array_fill(0, 7, ['name' => str_repeat('M', 60), 'role' => 'character', 'presence' => 'unspecified']);
        $prompts = new AiShotPrompts(new AiCatalog());
        $context = $prompts->writingContext('vidu-q1-subject', $details);
        self::assertGreaterThanOrEqual(200, $context['sceneCharacters']);
        self::assertLessThan(650, $context['sceneCharacters']);
        $scene = str_repeat('I', 300).'. '.str_repeat('s', $context['sceneCharacters']);
        $effective = $prompts->build('vidu-q1-subject', 5, $scene, str_repeat('context ', 300), 'independent', 7, $details);
        self::assertLessThanOrEqual(1500, mb_strlen($effective));
        self::assertStringContainsString($scene, $effective);
        self::assertStringContainsString('Reference7=', $effective);
    }
}
