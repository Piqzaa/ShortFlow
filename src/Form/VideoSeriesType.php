<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class VideoSeriesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'Nom de la série', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 120)], 'attr' => ['maxlength' => 120]])
            ->add('videos', ChoiceType::class, ['label' => 'Choisis tes vidéos', 'choices' => $options['videos'], 'multiple' => true, 'expanded' => true, 'constraints' => [new Assert\Count(min: 1, max: 100, minMessage: 'Choisis au moins une vidéo.')]])
            ->add('order', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 1100)]])
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('videos', []);
        $resolver->setAllowedTypes('videos', 'array');
    }
}
