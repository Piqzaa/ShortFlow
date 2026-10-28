<?php

namespace App\Form;

use App\Service\MultiNetworkPublishing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class SeriesRecurrenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('accounts', SocialAccountsType::class, ['choices_by_network' => $options['account_choices']]);
        $builder->add('weekday', ChoiceType::class, ['label' => 'Jour de publication visé', 'choices' => ['Lundi' => 1, 'Mardi' => 2, 'Mercredi' => 3, 'Jeudi' => 4, 'Vendredi' => 5, 'Samedi' => 6, 'Dimanche' => 7], 'placeholder' => false])
            ->add('hour', TextType::class, ['label' => 'Heure visée', 'attr' => ['placeholder' => '18:00', 'maxlength' => 5, 'pattern' => '(?:[01][0-9]|2[0-3]):[0-5][0-9]'], 'constraints' => [new Assert\NotBlank(), new Assert\Regex('/^(?:[01]\d|2[0-3]):[0-5]\d$/D')]])
            ->add('timezone', ChoiceType::class, ['label' => 'Fuseau horaire', 'choices' => array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()), 'preferred_choices' => ['Europe/Paris', 'UTC', 'America/Toronto']])
            ->add('networks', ChoiceType::class, ['label' => 'Réseaux proposés à la validation', 'choices' => array_flip(MultiNetworkPublishing::LABELS), 'multiple' => true, 'expanded' => true, 'constraints' => [new Assert\Count(min: 1)]])
            ->add('limit', IntegerType::class, ['label' => 'Nombre de scénarios à préparer', 'constraints' => [new Assert\Range(min: 1, max: 12), new Assert\NotBlank()], 'attr' => ['min' => 1, 'max' => 12, 'data-recurrence-budget-target' => 'count']])
            ->add('tone', ChoiceType::class, ['label' => 'Ambiance des histoires', 'choices' => ['Automatique · fidèle au thème' => 'auto', 'Humour léger' => 'humour', 'Aventure' => 'aventure', 'Douce et contemplative' => 'calme']])
            ->add('continuity', ChoiceType::class, ['label' => 'Enchaînement des clips', 'choices' => ['Plusieurs scènes' => 'independent', 'Raccord depuis la dernière image · modèle compatible requis' => 'linked'], 'placeholder' => false])
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]])
            ->add('quote', HiddenType::class, ['constraints' => [new Assert\Regex('/^[a-f0-9]{64}$/D')]])
            ->add('consent', CheckboxType::class, ['label' => 'J’active la préparation automatique des scénarios jusqu’au nombre indiqué. Je relirai chaque épisode et confirmerai séparément son coût pour générer la vidéo.', 'constraints' => [new Assert\IsTrue()]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('account_choices', []);
    }
}
