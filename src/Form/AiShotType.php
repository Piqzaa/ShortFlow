<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AiShotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('sceneDescription', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 300, groups: ['Default', 'connection', 'autosave'])]])
            ->add('startReference', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex('/^[a-f0-9]{48}\.jpg$/D')]])
            ->add('endReference', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex('/^[a-f0-9]{48}\.jpg$/D')]])
            ->add('referenceFit', HiddenType::class, ['required' => false, 'empty_data' => 'crop', 'constraints' => [new Assert\Choice(['crop', 'contain'])]])
            ->add('prompt', TextareaType::class, ['label' => 'Scène et action du plan', 'required' => false, 'constraints' => [new Assert\Length(max: 1500, groups: ['Default', 'connection', 'autosave'])], 'attr' => ['rows' => 4, 'maxlength' => 1500, 'placeholder' => 'Décris ce qui se passe ici, puis le mouvement de caméra…']])
            ->add('imagePrompt', TextareaType::class, ['label' => 'Mes consignes pour l’image de début', 'required' => false, 'constraints' => [new Assert\Length(max: 3000, groups: ['Default', 'connection', 'autosave'])], 'attr' => ['rows' => 6, 'maxlength' => 3000, 'placeholder' => 'Décris l’image souhaitée ou utilise « Proposer des consignes »…']])
            ->add('seconds', ChoiceType::class, ['label' => 'Durée', 'choices' => array_combine(array_map(static fn (int $s): string => $s.' s', $options['durations']), $options['durations'])]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('durations');
        $resolver->setAllowedTypes('durations', 'array');
    }
}
