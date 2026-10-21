<?php

namespace App\Form;

use App\Entity\VideoEdit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class VideoEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, ['label' => 'Nom du montage', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)], 'attr' => ['maxlength' => 120]])
            ->add('format', ChoiceType::class, ['label' => 'Format de la vidéo finale', 'choices' => ['Vertical · 9:16' => '9:16', 'Paysage · 16:9' => '16:9', 'Carré · 1:1' => '1:1'], 'constraints' => [new Assert\Choice(choices: array_keys(VideoEdit::FORMATS))]])
            ->add('fit', ChoiceType::class, ['label' => 'Cadrage', 'choices' => ['Image entière · avec bandes' => 'contain', 'Remplir · recadrage centré' => 'cover'], 'data' => $options['data']['fit'] ?? 'contain'])
            ->add('captionStyle', ChoiceType::class, ['label' => 'Style des sous-titres', 'choices' => ['Sans sous-titres' => 'off', 'Sobre · blanc' => 'clean', 'Contrasté · jaune' => 'social'], 'data' => $options['data']['captionStyle'] ?? 'off'])
            ->add('captionX', NumberType::class, ['label' => 'Position horizontale · 0,05 à 0,95', 'html5' => true, 'data' => $options['data']['captionX'] ?? 0.5, 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0.05, max: 0.95)], 'attr' => ['min' => 0.05, 'max' => 0.95, 'step' => 0.01]])
            ->add('captionY', NumberType::class, ['label' => 'Position verticale · 0,05 à 0,95', 'html5' => true, 'data' => $options['data']['captionY'] ?? 0.85, 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0.05, max: 0.95)], 'attr' => ['min' => 0.05, 'max' => 0.95, 'step' => 0.01]])
            ->add('captionSize', NumberType::class, ['label' => 'Taille · % de la hauteur', 'html5' => true, 'data' => $options['data']['captionSize'] ?? 4, 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 2, max: 10)], 'attr' => ['min' => 2, 'max' => 10, 'step' => 0.5]])
            ->add('language', ChoiceType::class, ['label' => 'Langue parlée', 'choices' => ['Français' => 'fr', 'Anglais' => 'en', 'Détection automatique' => 'auto'], 'data' => $options['data']['language'] ?? 'fr'])
            ->add('intro', CheckboxType::class, ['label' => 'Afficher le titre pendant les 2,5 premières secondes', 'required' => false])
            ->add('sourceVolume', NumberType::class, ['html5' => true, 'label' => 'Son des clips · %', 'data' => $options['data']['sourceVolume'] ?? 100, 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 100)], 'attr' => ['min' => 0, 'max' => 100]])
            ->add('musicVolume', NumberType::class, ['html5' => true, 'label' => 'Musique · %', 'data' => $options['data']['musicVolume'] ?? 20, 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 100)], 'attr' => ['min' => 0, 'max' => 100]])
            ->add('musicOffset', NumberType::class, ['html5' => true, 'label' => 'Début dans la piste · secondes', 'data' => $options['data']['musicOffset'] ?? 0, 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 599)], 'attr' => ['min' => 0, 'max' => 599, 'step' => 0.1]])
            ->add('musicLoop', CheckboxType::class, ['label' => 'Répéter la musique jusqu’à la fin', 'required' => false, 'data' => $options['data']['musicLoop'] ?? true]);
    }
}
