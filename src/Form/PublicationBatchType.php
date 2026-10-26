<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class PublicationBatchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revision', HiddenType::class, ['data' => $options['revision'], 'constraints' => [new Assert\EqualTo($options['revision'], message: 'La sélection a changé. Recharge-la avant de continuer.')]]);
        $plans = $builder->create('plans', FormType::class, ['label' => false]);
        foreach ($options['video_ids'] as $id) {
            $plans->add((string) $id, PublicationBatchRowType::class, ['label' => false, 'account_choices' => $options['account_choices']]);
        }
        $builder->add($plans);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['revision', 'video_ids']);
        $resolver->setDefault('account_choices', []);
        $resolver->setAllowedTypes('revision', 'string')->setAllowedTypes('video_ids', 'array');
    }
}
