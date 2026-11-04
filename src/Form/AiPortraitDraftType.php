<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class AiPortraitDraftType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'Nom du personnage', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 60)], 'attr' => ['maxlength' => 60]])
            ->add('idea', TextareaType::class, ['label' => 'Ton idée', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 1000)], 'attr' => ['maxlength' => 1000, 'rows' => 3]])
            ->add('appearance', TextareaType::class, ['label' => 'Apparence et tenue', 'required' => false, 'constraints' => [new Assert\Length(max: 2000)], 'attr' => ['maxlength' => 2000, 'rows' => 4]])
            ->add('style', TextType::class, ['label' => 'Style visuel', 'required' => false, 'constraints' => [new Assert\Length(max: 300)], 'attr' => ['maxlength' => 300]])
            ->add('customPrompt', TextareaType::class, ['label' => 'Mon prompt complet (facultatif)', 'required' => false, 'help' => 'Vide : le prompt est construit avec les champs ci-dessus. Renseigné : seul ce texte sera prévu pour le modèle. Relis-le si tu changes les autres champs.', 'constraints' => [new Assert\Length(max: 4000)], 'attr' => ['maxlength' => 4000, 'rows' => 5]])
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]]);
    }
}
