<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class SeriesCalendarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('start', DateType::class, ['label' => 'Date de début', 'widget' => 'single_text', 'input' => 'string', 'constraints' => [new Assert\NotBlank()]])
            ->add('end', DateType::class, ['label' => 'Date de fin (facultatif)', 'widget' => 'single_text', 'input' => 'string', 'required' => false])
            ->add('weekdays', ChoiceType::class, ['label' => 'Jours de publication prévus', 'choices' => ['Lundi' => 1, 'Mardi' => 2, 'Mercredi' => 3, 'Jeudi' => 4, 'Vendredi' => 5, 'Samedi' => 6, 'Dimanche' => 7], 'expanded' => true, 'multiple' => true, 'constraints' => [new Assert\Count(min: 1)]])
            ->add('hour', TextType::class, ['label' => 'Heure de publication prévue', 'attr' => ['placeholder' => '18:00', 'maxlength' => 5, 'inputmode' => 'numeric'], 'constraints' => [new Assert\NotBlank(), new Assert\Regex('/^(?:[01]\d|2[0-3]):[0-5]\d$/D')]])
            ->add('timezone', ChoiceType::class, ['label' => 'Fuseau horaire', 'choices' => array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()), 'preferred_choices' => ['Europe/Paris', 'UTC', 'America/Toronto']])
            ->add('count', IntegerType::class, ['label' => 'Nombre d’épisodes à prévoir', 'attr' => ['min' => 1, 'max' => 90], 'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 1, max: 90)]])
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]])
            ->add('snapshot', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex('/^[a-f0-9]{64}$/D')]]);
    }
}
