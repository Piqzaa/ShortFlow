<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class AiCharacterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'Nom', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 60)], 'attr' => ['maxlength' => 60]])
            ->add('description', TextareaType::class, ['label' => 'Description de son apparence', 'required' => false, 'constraints' => [new Assert\Length(max: 2000)], 'attr' => ['maxlength' => 2000, 'rows' => 4]])
            ->add('portrait', FileType::class, ['label' => 'Portrait', 'required' => false, 'help' => 'JPG, PNG ou WebP · 10 Mo maximum · 300 à 4 096 pixels par côté. À la modification, laisse vide pour conserver le portrait.', 'attr' => ['accept' => 'image/jpeg,image/png,image/webp']])
            ->add('views', FileType::class, ['label' => 'Vues supplémentaires', 'multiple' => true, 'required' => false, 'constraints' => [new Assert\Count(max: 3)], 'help' => 'Facultatif : jusqu’à trois images. Un nouvel envoi remplace les vues de la fiche ; les anciennes images utilisées restent conservées.', 'attr' => ['accept' => 'image/jpeg,image/png,image/webp']])
            ->add('clearViews', CheckboxType::class, ['label' => 'Retirer les vues supplémentaires de cette fiche', 'required' => false])
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]]);
    }
}
