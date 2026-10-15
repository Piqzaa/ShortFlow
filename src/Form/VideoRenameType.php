<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class VideoRenameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, ['label' => 'Nom de la vidéo', 'constraints' => [new Assert\NotBlank(message: 'Indique un nom pour cette vidéo.'), new Assert\Length(max: 120, maxMessage: 'Le nom est limité à 120 caractères.')], 'attr' => ['maxlength' => 120]]);
    }
}
