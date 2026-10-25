<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class SeriesEditorialType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('language', ChoiceType::class, ['label' => 'Langue des publications', 'choices' => ['Français' => 'fr', 'English' => 'en']]);
        foreach (['tone' => ['Ton des textes', 200], 'audience' => ['Public visé', 200], 'keywords' => ['Mots-clés utiles', 300], 'brand' => ['Formulation de marque facultative', 200], 'cta' => ['Appel à l’action facultatif', 200], 'allowedHashtags' => ['Hashtags autorisés (facultatif)', 300], 'excludedHashtags' => ['Hashtags exclus (facultatif)', 300]] as $name => [$label, $limit]) {
            $builder->add($name, TextareaType::class, ['label' => $label, 'required' => false, 'constraints' => [new Assert\Length(max: $limit)], 'attr' => ['rows' => 2, 'maxlength' => $limit]]);
        }
        $builder->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]]);
    }
}
