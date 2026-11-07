<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class VideoSeriesScheduleRowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('picked', CheckboxType::class, ['required' => false, 'label' => 'Programmer cet épisode'])
            ->add('draft', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex('/^\d+$/D')]])
            ->add('revision', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex('/^\d+$/D')]])
            ->add('local', TextType::class, ['required' => false, 'label' => 'Date et heure', 'constraints' => [new Assert\Length(max: 16)], 'attr' => ['maxlength' => 16, 'placeholder' => 'AAAA-MM-JJTHH:MM']])
            ->add('zone', TimezoneType::class, ['label' => 'Fuseau', 'preferred_choices' => ['Europe/Paris', 'UTC', 'America/Toronto']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['label' => false, 'translation_domain' => false]);
    }
}
