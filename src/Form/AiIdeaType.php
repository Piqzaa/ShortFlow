<?php

namespace App\Form;

use App\Service\AiCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class AiIdeaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach (AiCatalog::MODELS as $id => $model) {
            $choices[$model['name'].' · Fal'] = $id;
        }
        $builder
            ->add('title', TextType::class, ['label' => 'Nom de ta création', 'required' => false, 'help' => 'Facultatif : un nom sera proposé à partir de ton idée.', 'constraints' => [new Assert\Length(max: 120)], 'attr' => ['maxlength' => 120]])
            ->add('prompt', TextareaType::class, ['label' => 'Ton idée', 'help' => 'Décris le sujet, le mouvement et l’ambiance. Un prompt en anglais est conseillé pour ces modèles.', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 1500)], 'attr' => ['rows' => 6, 'maxlength' => 1500, 'placeholder' => 'Une caméra suit un cycliste au lever du soleil…']])
            ->add('model', ChoiceType::class, ['label' => 'Modèle et fournisseur', 'placeholder' => 'Choisir un modèle', 'choices' => $choices, 'constraints' => [new Assert\NotBlank()]])
            ->add('seconds', ChoiceType::class, ['label' => 'Durée', 'choices' => ['5 secondes' => 5, '10 secondes' => 10], 'constraints' => [new Assert\NotBlank()]])
            ->add('ratio', ChoiceType::class, ['label' => 'Format', 'choices' => ['Vertical · 9:16' => '9:16', 'Paysage · 16:9' => '16:9', 'Carré · 1:1' => '1:1'], 'constraints' => [new Assert\NotBlank()]]);
    }
}
