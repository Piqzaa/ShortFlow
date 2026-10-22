<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class AiEpisodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre de l’épisode', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 120)], 'attr' => ['maxlength' => 120]])
            ->add('prompt', TextareaType::class, ['label' => 'Prompt de l’épisode', 'help' => 'Complète le contexte commun avec une scène, une action et un mouvement de caméra. Ce texte sera envoyé tel quel au modèle.', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 1500)], 'attr' => ['rows' => 6, 'maxlength' => 1500]])
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]])
            ->add('seriesRevision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]]);
    }
}
