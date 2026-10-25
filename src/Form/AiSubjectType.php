<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class AiSubjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('reference', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex('/^(?:[a-f0-9]{48}\.jpg|primary)$/D')]])
            ->add('name', TextType::class, ['label' => 'Nom du personnage ou de l’objet', 'required' => false, 'constraints' => [new Assert\Length(max: 60)], 'attr' => ['maxlength' => 60]])
            ->add('role', ChoiceType::class, ['label' => 'Rôle de cette référence', 'choices' => ['Personnage' => 'character', 'Objet' => 'object'], 'empty_data' => 'character', 'placeholder' => false])
            ->add('presence', ChoiceType::class, ['label' => 'Présence dans le premier cadre', 'choices' => ['À préciser dans les scènes' => 'unspecified', 'Déjà présent dans le cadre' => 'visible', 'Entre pendant l’action' => 'enters'], 'empty_data' => 'unspecified', 'placeholder' => false])
            ->add('views', CollectionType::class, ['entry_type' => HiddenType::class, 'entry_options' => ['required' => false, 'constraints' => [new Assert\Regex('/^[a-f0-9]{48}\.jpg$/D')]], 'allow_add' => true, 'allow_delete' => true, 'required' => false, 'prototype_name' => '__view__', 'constraints' => [new Assert\Count(max: 3)]]);
    }
}
