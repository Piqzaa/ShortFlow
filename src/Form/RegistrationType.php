<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, ['label' => 'Adresse email', 'constraints' => [new Assert\NotBlank(), new Assert\Email(message: 'Indique une adresse email valide.'), new Assert\Length(max: 254)], 'attr' => ['autocomplete' => 'email', 'maxlength' => 254]])
            ->add('password', RepeatedType::class, ['type' => PasswordType::class, 'invalid_message' => 'Les mots de passe doivent être identiques.', 'first_options' => ['label' => 'Mot de passe', 'help' => 'Au moins 12 caractères. Une phrase longue est un bon choix.'], 'second_options' => ['label' => 'Confirmer le mot de passe'], 'options' => ['attr' => ['autocomplete' => 'new-password', 'maxlength' => 128]], 'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 12, max: 128, minMessage: 'Utilise au moins 12 caractères.')]]);
    }
}
