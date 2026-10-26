<?php

namespace App\Form;

use App\Service\MultiNetworkPublishing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class PublicationBatchRowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('accounts', SocialAccountsType::class, ['choices_by_network' => $options['account_choices']]);
        $builder->add('networks', ChoiceType::class, ['label' => 'Réseaux de cette vidéo', 'choices' => array_flip(MultiNetworkPublishing::LABELS), 'expanded' => true, 'multiple' => true, 'constraints' => [new Assert\Count(min: 1, minMessage: 'Choisis au moins un réseau pour cette vidéo.')]])
            ->add('mode', ChoiceType::class, ['label' => 'Quand publier cette vidéo ?', 'choices' => ['Maintenant' => 'immediate', 'Choisir une date' => 'scheduled'], 'expanded' => true])
            ->add('local', TextType::class, ['label' => 'Date et heure locales', 'required' => false, 'constraints' => [new Assert\Length(max: 16)], 'attr' => ['placeholder' => 'AAAA-MM-JJTHH:MM', 'data-local-datetime' => 'true']])
            ->add('timezone', TimezoneType::class, ['label' => 'Fuseau horaire']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('account_choices', []);
    }
}
