<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AiStoryStartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('duration', IntegerType::class, ['label' => 'Durée totale souhaitée (secondes)', 'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 1, max: 60)], 'attr' => ['min' => 1, 'max' => 60], 'help' => 'Choisis ton total. Les propositions utilisent les durées acceptées par le modèle.'])
            ->add('continuity', ChoiceType::class, ['label' => 'Enchaînement des plans', 'choices' => ['Scènes distinctes, références communes' => 'independent', 'Action continue, raccord depuis le plan précédent' => 'linked'], 'help' => 'Le raccord demande un modèle image vers vidéo avec une image de départ et sans image de fin obligatoire. La cohérence parfaite n’est pas garantie.'])
            ->add('mute', CheckboxType::class, ['label' => 'Couper le son des plans au montage', 'required' => false, 'help' => 'Évite les ruptures entre les sons générés. Tu pourras ajouter une musique ou une narration dans le montage.'])
            ->add('layout', ChoiceType::class, ['label' => 'Découpage proposé', 'choices' => $options['layouts'], 'required' => false, 'placeholder' => 'Choisir un découpage'])
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('layouts', []);
        $resolver->setAllowedTypes('layouts', 'array');
    }
}
