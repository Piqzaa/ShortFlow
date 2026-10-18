<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('displayName', TextType::class, ['label' => 'Nom affiché', 'required' => false, 'constraints' => [new Assert\Length(max: 80)], 'attr' => ['maxlength' => 80, 'autocomplete' => 'nickname'], 'help' => 'Facultatif. Ce nom apparaît uniquement dans ton espace VydFlow.'])
            ->add('avatar', FileType::class, ['label' => 'Photo de profil', 'required' => false, 'constraints' => [new Assert\Image(maxSize: '2M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], maxWidth: 4096, maxHeight: 4096)], 'attr' => ['accept' => 'image/jpeg,image/png,image/webp'], 'help' => 'JPG, PNG ou WebP · 2 Mo maximum. Une copie carrée est créée ; ton image reste privée.'])
            ->add('removeAvatar', CheckboxType::class, ['label' => 'Retirer ma photo actuelle', 'required' => false, 'row_attr' => ['class' => 'publication-checkbox']]);
    }
}
