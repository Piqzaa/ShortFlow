<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class PublicationPlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revision', HiddenType::class, ['data' => (string) $options['revision'], 'constraints' => [new Assert\EqualTo((string) $options['revision'])]]);
        if ($options['scheduled_only']) {
            $builder->add('mode', HiddenType::class, ['data' => 'scheduled', 'constraints' => [new Assert\EqualTo('scheduled')]]);
        } else {
            $builder->add('mode', ChoiceType::class, ['label' => 'Quand publier ?', 'choices' => ['Maintenant' => 'immediate', 'À une date choisie' => 'scheduled']]);
        }
        $builder->add('local', TextType::class, ['required' => false, 'label' => 'Date et heure locales', 'constraints' => [new Assert\Length(max: 16)]])
            ->add('timezone', TimezoneType::class, ['label' => 'Fuseau horaire']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('revision')->setAllowedTypes('revision', 'int');
        $resolver->setDefault('scheduled_only', false)->setAllowedTypes('scheduled_only', 'bool');
    }
}
