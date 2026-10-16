<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class InstagramPublishType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $rows = $builder->create('videos', FormType::class, ['label' => false]);
        foreach ($options['videos'] as $video) {
            $row = $rows->create((string) $video->getId(), FormType::class, ['label' => false]);
            $row->add('caption', TextareaType::class, ['label' => 'Légende Instagram', 'required' => false, 'attr' => ['rows' => 5, 'maxlength' => 2200], 'constraints' => [new Assert\Length(max: 2200), new Assert\Regex(pattern: '/^(?:(?!#)[\s\S]*#){0,30}(?!#)[\s\S]*$/u', message: 'Utilise au maximum 30 hashtags.')]])
                ->add('shareToFeed', CheckboxType::class, ['label' => 'Partager aussi dans le fil', 'required' => false, 'row_attr' => ['class' => 'publication-checkbox']])
                ->add('synthetic', CheckboxType::class, ['label' => 'Indiquer que le contenu a été généré par IA', 'required' => false, 'row_attr' => ['class' => 'publication-checkbox']]);
            $rows->add($row);
        }
        $builder->add('draft_key', HiddenType::class, ['mapped' => false, 'data' => $options['draft_key'], 'constraints' => [new Assert\EqualTo($options['draft_key'])]])->add($rows)->add('confirm', CheckboxType::class, ['label' => 'Je confirme le compte, les vidéos et les légendes, et je dispose des droits nécessaires pour les publier.', 'row_attr' => ['class' => 'publication-checkbox'], 'constraints' => [new Assert\IsTrue()]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['videos', 'draft_key'])->setAllowedTypes('videos', 'array')->setAllowedTypes('draft_key', 'string');
    }
}
