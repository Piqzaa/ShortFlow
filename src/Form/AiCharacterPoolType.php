<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AiCharacterPoolType extends AbstractType
{
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['label' => 'Personnages disponibles pour les scènes', 'multiple' => true, 'required' => false, 'help' => 'Choisis jusqu’à sept fiches. Une seule version par personnage. Les références conservées ne changent pas avec la bibliothèque ; choisir la version actuelle les actualise. La présence se choisit ensuite dans chaque scène.', 'constraints' => [new Assert\Count(max: 7, groups: ['Default', 'autosave', 'connection'])], 'attr' => ['size' => 5]]);
    }
}
