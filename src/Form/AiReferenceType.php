<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class AiReferenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('image', FileType::class, ['label' => $options['label'] ?? 'Image de départ', 'constraints' => [new Assert\NotNull(), new Assert\File(maxSize: '10M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])], 'attr' => ['accept' => 'image/jpeg,image/png,image/webp']]);
    }
}
