<?php

namespace App\Tests\Unit;

use App\Form\AiShotType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class AiShotTypeTest extends TestCase
{
    /** @param array<string,mixed> $data */
    private function form(array $data, string $context = ''): FormInterface
    {
        return Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->getFormFactory()->createNamed('shot', AiShotType::class, $data, ['durations' => [5, 10], 'visual_context' => $context]);
    }

    public function testExistingContextIsVisibleOnceWithoutMutatingStoredData(): void
    {
        $data = ['prompt' => 'The cat walks across the room.', 'seconds' => 5, 'translatedIdentity' => 'A white cat.', 'translationSource' => str_repeat('a', 64)];
        $form = $this->form($data);
        self::assertSame("A white cat.\nThe cat walks across the room.", $form->get('prompt')->getData());
        self::assertSame('1', $form->get('promptOnly')->getData());
        self::assertSame('The cat walks across the room.', $data['prompt']);
        $data['prompt'] = 'A white cat. The cat walks across the room.';
        self::assertSame($data['prompt'], $this->form($data)->get('prompt')->getData());
    }

    public function testEditingTheFullPromptDoesNotRestoreRemovedIdentityOnReload(): void
    {
        $form = $this->form(['prompt' => 'A blue cat walks across the room.', 'seconds' => 5, 'translatedIdentity' => 'A white cat.', 'promptOnly' => '1']);
        self::assertSame('A blue cat walks across the room.', $form->get('prompt')->getData());
        $manual = $this->form(['prompt' => 'A blue cat walks across the room.', 'seconds' => 5, 'translatedIdentity' => 'A white cat.', 'promptOnly' => 'manual']);
        self::assertSame('manual', $manual->get('promptOnly')->getData());
        self::assertSame('A blue cat walks across the room.', $manual->get('prompt')->getData());
    }

    public function testLegacyContextIsPreservedButANewBlankPlanStaysBlank(): void
    {
        self::assertSame("A red robot.\nA static camera follows it.", $this->form(['prompt' => 'A static camera follows it.', 'seconds' => 5], 'A red robot.')->get('prompt')->getData());
        self::assertSame('', $this->form(['prompt' => '', 'seconds' => 5], 'A red robot.')->get('prompt')->getData());
    }

    public function testMissingMarkerOnAnOlderSubmittedFormKeepsLegacySemantics(): void
    {
        $form = $this->form(['prompt' => 'A white cat walks.', 'seconds' => 5]);
        $form->submit(['prompt' => 'A white cat walks.', 'seconds' => '5']);
        self::assertTrue($form->isValid());
        self::assertNull($form->getData()['promptOnly']);
    }

    public function testOversizedMergedTextIsNeverTruncatedAndCannotBeSaved(): void
    {
        $form = $this->form(['prompt' => str_repeat('A', 1490), 'seconds' => 5], 'A white cat in a large room.');
        $prompt = $form->get('prompt')->getData();
        self::assertGreaterThan(1500, mb_strlen($prompt));
        self::assertStringEndsWith(str_repeat('A', 1490), $prompt);
        $form->submit(['prompt' => $prompt, 'seconds' => '5', 'promptOnly' => '1']);
        self::assertFalse($form->isValid());
    }
}
