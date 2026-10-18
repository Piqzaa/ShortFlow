<?php

namespace App\Form;

use App\Service\MultiNetworkPublishing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class PublicationComposerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revision', HiddenType::class, ['data' => $options['revision'], 'constraints' => [new Assert\EqualTo($options['revision'], message: 'La préparation a changé dans un autre onglet. Recharge-la avant de continuer.')]])
            ->add('networks', ChoiceType::class, ['label' => 'Où publier ?', 'choices' => array_flip(MultiNetworkPublishing::LABELS), 'expanded' => true, 'multiple' => true, 'constraints' => $options['partial'] ? [] : [new Assert\Count(min: 1, minMessage: 'Choisis au moins un réseau.')]])
            ->add('settings', MultiNetworkPrepareType::class, ['label' => false, 'videos' => $options['videos'], 'networks' => $options['networks'], 'creator' => $options['creator'], 'draft_key' => $options['revision'], 'draft_title' => $options['draft_title'], 'partial' => $options['partial']])
            ->add('mode', ChoiceType::class, ['label' => 'Quand ?', 'choices' => ['Maintenant' => 'immediate', 'Choisir une date' => 'scheduled'], 'expanded' => true])
            ->add('local', TextType::class, ['label' => 'Date et heure locales', 'required' => false, 'constraints' => [new Assert\Length(max: 16)], 'attr' => ['placeholder' => 'AAAA-MM-JJTHH:MM']])
            ->add('timezone', TimezoneType::class, ['label' => 'Fuseau horaire']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['videos', 'networks', 'creator', 'revision'])->setDefaults(['partial' => false, 'draft_title' => '']);
        $resolver->setAllowedTypes('videos', 'array')->setAllowedTypes('networks', 'array')->setAllowedTypes('creator', 'array')->setAllowedTypes('revision', 'string');
    }
}
