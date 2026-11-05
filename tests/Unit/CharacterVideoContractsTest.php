<?php

namespace App\Tests\Unit;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Service\AiCatalog;
use App\Service\AiReferenceStorage;
use App\Service\AiShotPrompts;
use App\Service\CharacterVideoModels;
use App\Service\FalVideoApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class CharacterVideoContractsTest extends TestCase
{
    public static function configurations(): iterable
    {
        foreach (CharacterVideoModels::MODELS as $id => $model) {
            foreach (array_unique([$model['seconds'][0], $model['seconds'][array_key_last($model['seconds'])]]) as $seconds) {
                yield $id.'-'.$seconds => [$id, $seconds];
            }
        }
    }

    #[DataProvider('configurations')]
    public function testPhotographicReferencesMatchTheRecordedOfficialContract(string $id, int $seconds): void
    {
        $catalog = new AiCatalog();
        $model = $catalog->model($id);
        $directory = sys_get_temp_dir().'/vydflow-reference-contract-'.bin2hex(random_bytes(8));
        mkdir($directory.'/42', 0750, true);
        $names = [str_repeat('a', 48).'.jpg', str_repeat('b', 48).'.jpg'];
        foreach ($names as $name) {
            copy(dirname(__DIR__).'/Fixtures/reference.jpg', $directory.'/42/'.$name);
        }
        try {
            $owner = new User('synthetic-reference@example.test');
            (new \ReflectionProperty(User::class, 'id'))->setValue($owner, 42);
            $count = min(2, $model['subjectLimit']);
            $details = array_map(static fn (int $index): array => ['name' => $index ? 'Nox' : 'Milo', 'role' => 'character', 'presence' => 'visible'], range(0, $count - 1));
            $prompt = (new AiShotPrompts($catalog))->build($id, $seconds, 'Milo waves slowly.', '', 'independent', $count, $details);
            $job = new AiGeneration($owner, 'Contract', $prompt, $id, $seconds, $model['ratios'][0], $catalog->estimate($id, $seconds, $count));
            $job->attachReference($names[0]);
            $job->attachSubjects(2 === $count ? [$names[1]] : []);
            $api = new FalVideoApi(new MockHttpClient(static fn () => throw new \LogicException('No real HTTP.')), $catalog, new AiReferenceStorage($directory));
            $input = $api->input($job);
            $contracts = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/character-video-contracts.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSchema($input, $contracts[$model['endpoint']]);
            self::assertArrayNotHasKey('start_image_url', $input);
            self::assertArrayNotHasKey('video_urls', $input);
            self::assertArrayNotHasKey('reference_video_urls', $input);
            self::assertGreaterThan(0, $job->getEstimatedCents());
            self::assertSame('subject', $catalog->referenceMode($id));
        } finally {
            foreach ($names as $name) {
                unlink($directory.'/42/'.$name);
            }
            rmdir($directory.'/42');
            rmdir($directory);
        }
    }

    private function assertSchema(mixed $value, array $schema): void
    {
        if (isset($schema['anyOf'])) {
            foreach ($schema['anyOf'] as $branch) {
                if (($branch['type'] ?? null) === $this->typeOf($value) || ('number' === ($branch['type'] ?? null) && is_int($value))) {
                    $this->assertSchema($value, $branch);

                    return;
                }
            }
            self::fail('No matching schema union for '.$this->typeOf($value));
        }
        if (isset($schema['enum'])) {
            self::assertContains($value, $schema['enum']);
        }
        if (isset($schema['type'])) {
            self::assertTrue($schema['type'] === $this->typeOf($value) || ('number' === $schema['type'] && is_int($value)));
        }
        if ('object' === ($schema['type'] ?? null)) {
            foreach ($schema['required'] ?? [] as $name) {
                self::assertArrayHasKey($name, $value);
            }
            foreach ($value as $name => $child) {
                self::assertArrayHasKey($name, $schema['properties'], 'Unexpected provider input '.$name);
                $this->assertSchema($child, $schema['properties'][$name]);
            }
        } elseif (is_array($value)) {
            self::assertGreaterThanOrEqual($schema['minItems'] ?? 0, count($value));
            self::assertLessThanOrEqual($schema['maxItems'] ?? PHP_INT_MAX, count($value));
            foreach ($value as $child) {
                $this->assertSchema($child, $schema['items'] ?? []);
            }
        } elseif (is_string($value)) {
            self::assertLessThanOrEqual($schema['maxLength'] ?? PHP_INT_MAX, mb_strlen($value));
        } elseif (is_numeric($value)) {
            self::assertGreaterThanOrEqual($schema['minimum'] ?? -PHP_INT_MAX, $value);
            self::assertLessThanOrEqual($schema['maximum'] ?? PHP_INT_MAX, $value);
        }
    }

    private function typeOf(mixed $value): string
    {
        return match (true) {
            is_array($value) => array_is_list($value) ? 'array' : 'object',
            is_string($value) => 'string',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            default => 'null',
        };
    }

    public function testReferenceFeesAndFixedMegapixelQuotesAreNotLost(): void
    {
        $catalog = new AiCatalog();
        self::assertSame(41, $catalog->estimate('grok-15-subject-480p', 5, 1));
        self::assertSame(47, $catalog->estimate('grok-15-subject-480p', 5, 7));
        self::assertSame(27, $catalog->estimate('ltx-23-ingredient-subject', 5, 1));
        self::assertSame(56, $catalog->estimate('kling-o3-subject', 5, 2));
        self::assertSame(111, $catalog->estimate('seedance-25-us-subject-480p', 5, 2));
        self::assertSame(237, $catalog->estimate('seedance-25-us-subject-720p', 5, 2));
    }
}
