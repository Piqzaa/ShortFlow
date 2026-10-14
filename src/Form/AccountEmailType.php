<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class AccountEmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, ['label' => 'Adresse email', 'constraints' => [new Assert\NotBlank(), new Assert\Email(message: 'Indique une adresse email valide.'), new Assert\Length(max: 254)], 'attr' => ['autocomplete' => 'email', 'maxlength' => 254]]);
    }
}
