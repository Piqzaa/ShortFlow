<?php

namespace App\Form;

use App\Service\MultiNetworkPublishing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SocialAccountsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($options['networks'] as $network) {
            $builder->add($network, ChoiceType::class, ['label' => 'Compte '.$options['labels'][$network], 'choices' => $options['choices_by_network'][$network] ?? [], 'placeholder' => 'Choisir un compte connecté', 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['networks' => array_keys(MultiNetworkPublishing::LABELS), 'choices_by_network' => [], 'labels' => MultiNetworkPublishing::LABELS, 'label' => false]);
        $resolver->setAllowedTypes('networks', 'array')->setAllowedTypes('choices_by_network', 'array');
    }
}
