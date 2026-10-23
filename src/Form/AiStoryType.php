<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AiStoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, ['label' => 'Nom de la vidéo', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 120)], 'attr' => ['maxlength' => 120]])
            ->add('shots', CollectionType::class, ['entry_type' => AiShotType::class, 'entry_options' => ['durations' => $options['durations']], 'label' => false, 'allow_add' => true, 'allow_delete' => true, 'constraints' => [new Assert\Count(min: 1, max: 8)]])
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('durations');
        $resolver->setAllowedTypes('durations', 'array');
    }
}
