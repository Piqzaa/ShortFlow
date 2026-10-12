<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class WaitlistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, ['label' => 'Ton adresse email', 'constraints' => [new Assert\NotBlank(message: 'Indique ton adresse email.'), new Assert\Email(message: 'Cette adresse email semble incorrecte.'), new Assert\Length(max: 254)], 'attr' => ['placeholder' => 'toi@exemple.fr', 'autocomplete' => 'email', 'maxlength' => 254]])
            ->add('website', TextType::class, ['required' => false, 'label' => 'Laisser ce champ vide', 'attr' => ['tabindex' => '-1', 'autocomplete' => 'off']]);
    }
}
