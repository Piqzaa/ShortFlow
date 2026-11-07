<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class VideoSeriesScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revision', HiddenType::class, ['data' => $options['revision'], 'constraints' => [new Assert\EqualTo($options['revision'], message: 'La série a changé. Recharge ses épisodes avant de confirmer.')]])
            ->add('selection', FormType::class, ['label' => false]);
        foreach ($options['episodes'] as $id => $defaults) {
            $builder->get('selection')->add((string) $id, VideoSeriesScheduleRowType::class, ['label' => false]);
        }
        $builder->add('snapshot', HiddenType::class)
            ->add('confirm', CheckboxType::class, ['required' => false, 'label' => 'Je confirme les créneaux, les vidéos, les comptes et les textes de chaque épisode et je dispose des droits nécessaires. Pour TikTok, j’accepte la confirmation d’utilisation de la musique et la politique relative aux contenus de marque pour les partenariats.', 'row_attr' => ['class' => 'publication-checkbox']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['revision', 'episodes']);
        $resolver->setAllowedTypes('revision', 'string')->setAllowedTypes('episodes', 'array');
    }
}
