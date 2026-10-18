<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class YouTubeVideoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, ['label' => 'Titre YouTube', 'required' => !$options['partial'], 'attr' => ['maxlength' => 100], 'constraints' => array_merge($options['partial'] ? [] : [new Assert\NotBlank(message: 'Renseigne ce champ avant de continuer.')], [new Assert\Length(max: 100), new Assert\Regex(pattern: '/^[^<>]+$/u', message: 'Le titre ne peut pas contenir < ou >.')])])
            ->add('description', TextareaType::class, ['label' => 'Légende / description', 'required' => false, 'attr' => ['rows' => 3], 'constraints' => [new Assert\Regex(pattern: '/^[^<>]*$/u', message: 'La description ne peut pas contenir < ou >.'), new Assert\Callback(static function (?string $value, ExecutionContextInterface $context): void { if (strlen($value ?? '') > 5000) { $context->buildViolation('La description dépasse la limite YouTube de 5 000 octets.')->addViolation(); } })]])
            ->add('privacy', ChoiceType::class, ['label' => 'Visibilité', 'required' => !$options['partial'], 'placeholder' => 'Choisir la visibilité', 'choices' => ['Privée' => 'private', 'Non répertoriée' => 'unlisted', 'Publique' => 'public'], 'constraints' => $options['partial'] ? [] : [new Assert\NotBlank(message: 'Renseigne ce champ avant de continuer.')]])
            ->add('audience', ChoiceType::class, ['label' => 'Cette vidéo est-elle conçue pour les enfants ?', 'required' => !$options['partial'], 'placeholder' => 'Choisir l’audience', 'choices' => ['Non' => 'no', 'Oui' => 'yes'], 'constraints' => $options['partial'] ? [] : [new Assert\NotBlank(message: 'Renseigne ce champ avant de continuer.')]])
            ->add('synthetic', CheckboxType::class, ['row_attr' => ['class' => 'publication-checkbox'], 'label' => 'Contient un contenu réaliste modifié ou généré artificiellement', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('partial', false)->setAllowedTypes('partial', 'bool');
    }
}
