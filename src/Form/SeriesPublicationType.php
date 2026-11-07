<?php

namespace App\Form;

use App\Service\MultiNetworkPublishing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SeriesPublicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('networks', ChoiceType::class, ['label' => 'Où publier tes épisodes ?', 'choices' => array_flip(MultiNetworkPublishing::LABELS), 'expanded' => true, 'multiple' => true, 'required' => false])
            ->add('accounts', SocialAccountsType::class, ['choices_by_network' => $options['account_choices']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['account_choices' => [], 'label' => false]);
        $resolver->setAllowedTypes('account_choices', 'array');
    }
}
