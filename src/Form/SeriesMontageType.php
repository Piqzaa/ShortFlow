<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class SeriesMontageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('sourceVolume', IntegerType::class, ['label' => 'Volume des clips dans le montage (%)', 'help' => '0 coupe le son à l’export. Ce réglage ne réduit pas le coût de génération chez le fournisseur.', 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 100)], 'attr' => ['min' => 0, 'max' => 100, 'step' => 1]])
            ->add('intro', CheckboxType::class, ['label' => 'Afficher le titre de l’épisode pendant les 2,5 premières secondes', 'required' => false])
            ->add('fit', ChoiceType::class, ['label' => 'Ajustement des clips au montage', 'choices' => ['Image entière, avec bandes si nécessaire' => 'contain', 'Remplir le cadre, avec recadrage centré' => 'cover'], 'help' => 'Le format de sortie reste celui choisi pour l’épisode. Le contrôle du format livré par le fournisseur reste actif.'])
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]]);
    }
}
