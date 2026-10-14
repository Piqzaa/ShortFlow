<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AccountTokenType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['reset_password' => false]);
        $resolver->setAllowedTypes('reset_password', 'bool');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('token', PasswordType::class, ['label' => 'Code reçu par email', 'help' => 'Le lien de l’email le remplit automatiquement. Sinon, copie le code indiqué dans l’email.', 'always_empty' => false, 'constraints' => [new Assert\NotBlank(), new Assert\Regex(pattern: '/^[a-f0-9]{64}$/D', message: 'Le code doit contenir les 64 caractères reçus par email.')], 'attr' => ['autocomplete' => 'off', 'data-account-token-target' => 'input']]);
        if ($options['reset_password']) {
            $builder->add('password', RepeatedType::class, ['type' => PasswordType::class, 'invalid_message' => 'Les mots de passe doivent être identiques.', 'first_options' => ['label' => 'Nouveau mot de passe', 'help' => 'Au moins 12 caractères.'], 'second_options' => ['label' => 'Confirmer le mot de passe'], 'options' => ['attr' => ['autocomplete' => 'new-password', 'maxlength' => 128]], 'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 12, max: 128, minMessage: 'Utilise au moins 12 caractères.')]]);
        }
    }
}
