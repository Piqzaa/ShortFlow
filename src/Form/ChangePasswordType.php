<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, ['label' => 'Mot de passe actuel', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 128)], 'attr' => ['autocomplete' => 'current-password', 'maxlength' => 128]])
            ->add('newPassword', RepeatedType::class, ['type' => PasswordType::class, 'invalid_message' => 'Les mots de passe doivent être identiques.', 'first_options' => ['label' => 'Nouveau mot de passe', 'help' => 'Au moins 12 caractères.'], 'second_options' => ['label' => 'Confirmer le nouveau mot de passe'], 'options' => ['attr' => ['autocomplete' => 'new-password', 'maxlength' => 128]], 'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 12, max: 128, minMessage: 'Utilise au moins 12 caractères.')]]);
    }
}
