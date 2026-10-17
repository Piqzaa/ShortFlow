<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class YouTubePublishType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $rows = $builder->create('videos', FormType::class, ['label' => false]);
        foreach ($options['videos'] as $video) {
            $rows->add((string) $video->getId(), YouTubeVideoType::class, ['label' => false, 'partial' => $options['partial']]);
        }
        $builder->add('draft_key', HiddenType::class, ['mapped' => false, 'data' => $options['draft_key'], 'constraints' => [new Assert\EqualTo($options['draft_key'], message: 'Cette préparation a changé dans un autre onglet. Recharge la page.')]])->add($rows)->add('confirm', CheckboxType::class, ['row_attr' => ['class' => 'publication-checkbox'], 'label' => 'Je confirme les vidéos, la chaîne et les options ci-dessus, et je dispose des droits nécessaires pour les publier.', 'constraints' => [new Assert\IsTrue()]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['videos', 'draft_key'])->setAllowedTypes('videos', 'array')->setAllowedTypes('draft_key', 'string');
        $resolver->setDefault('partial', false)->setAllowedTypes('partial', 'bool');
    }
}
