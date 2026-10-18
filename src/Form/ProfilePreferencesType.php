<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProfilePreferencesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('timezone', TimezoneType::class, ['label' => 'Fuseau horaire par défaut', 'help' => 'Utilisé pour les nouvelles préparations et le calendrier. Les publications déjà programmées gardent leur créneau.']);
    }
}
