<?php

namespace App\Tests\Unit;

use App\Form\AiSubjectType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class AiSubjectTypeTest extends TestCase
{
    public function testPrimaryProfileAndNestedViewPrototypeRemainDistinct(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->getFormFactory();
        $form = $factory->create(AiSubjectType::class);
        self::assertSame('__view__', $form->get('views')->getConfig()->getOption('prototype_name'));
        $form->submit(['reference' => 'primary', 'name' => 'Milo', 'role' => 'character', 'presence' => 'enters', 'views' => [str_repeat('b', 48).'.jpg']]);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('primary', $form->getData()['reference']);
        self::assertSame('enters', $form->getData()['presence']);
    }

    public function testInvalidRoleAndTooManyViewsAreFormErrors(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->getFormFactory();
        $form = $factory->create(AiSubjectType::class);
        $form->submit(['reference' => str_repeat('a', 48).'.jpg', 'name' => 'Milo', 'role' => 'character', 'presence' => 'visible', 'views' => array_fill(0, 4, str_repeat('b', 48).'.jpg')]);
        self::assertFalse($form->isValid());
        $invalidRole = $factory->create(AiSubjectType::class);
        $invalidRole->submit(['reference' => str_repeat('a', 48).'.jpg', 'name' => 'Milo', 'role' => 'administrator', 'presence' => 'visible', 'views' => []]);
        self::assertFalse($invalidRole->isValid());
    }
}
