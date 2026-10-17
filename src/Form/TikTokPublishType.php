<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class TikTokPublishType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'tiktok_publish';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $names = ['PUBLIC_TO_EVERYONE' => 'Tout le monde', 'MUTUAL_FOLLOW_FRIENDS' => 'Amis', 'FOLLOWER_OF_CREATOR' => 'Abonnés', 'SELF_ONLY' => 'Moi uniquement'];
        $choices = [];
        foreach ($options['creator']['privacy_level_options'] ?? [] as $privacy) {
            $choices[$names[$privacy]] = $privacy;
        }
        $rows = $builder->create('videos', FormType::class, ['label' => false]);
        foreach ($options['videos'] as $video) {
            $row = $rows->create((string) $video->getId(), FormType::class, ['label' => false]);
            $row->add('caption', TextareaType::class, ['label' => 'Légende TikTok', 'required' => false, 'attr' => ['rows' => 4, 'maxlength' => 2200], 'constraints' => [new Assert\Length(max: 2200)]])
                ->add('privacy', ChoiceType::class, ['label' => 'Qui peut voir cette vidéo ?', 'placeholder' => 'Choisir la visibilité', 'choices' => $choices, 'required' => !$options['allow_missing_privacy'], 'constraints' => $options['allow_missing_privacy'] ? [] : [new Assert\NotBlank()]]);
            foreach (['comment' => 'Autoriser les commentaires', 'duet' => 'Autoriser les Duos', 'stitch' => 'Autoriser les Collages'] as $flag => $label) {
                $row->add($flag, CheckboxType::class, ['label' => $label, 'required' => false, 'disabled' => $options['creator'][$flag.'_disabled'] ?? true, 'row_attr' => ['class' => 'publication-checkbox']]);
            }
            foreach (['disclosure' => 'Cette vidéo présente un contenu commercial', 'ownBrand' => 'Je présente ma propre marque (contenu promotionnel)', 'partnerBrand' => 'Je présente une marque partenaire (partenariat rémunéré)', 'synthetic' => 'Indiquer que le contenu a été généré par IA'] as $flag => $label) {
                $row->add($flag, CheckboxType::class, ['label' => $label, 'required' => false, 'row_attr' => ['class' => 'publication-checkbox']]);
            }
            $rows->add($row);
        }
        $builder->add('draft_key', HiddenType::class, ['mapped' => false, 'data' => $options['draft_key'], 'constraints' => [new Assert\EqualTo($options['draft_key'])]])->add($rows)
            ->add('confirm', CheckboxType::class, ['label' => 'Je confirme le compte, les vidéos et les réglages, et j’accepte la confirmation d’utilisation de la musique ainsi que la politique relative aux contenus de marque pour tout partenariat.', 'row_attr' => ['class' => 'publication-checkbox'], 'constraints' => [new Assert\IsTrue()]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['videos', 'draft_key', 'creator'])->setAllowedTypes('videos', 'array')->setAllowedTypes('draft_key', 'string')->setAllowedTypes('creator', 'array');
        $resolver->setDefault('allow_missing_privacy', false)->setAllowedTypes('allow_missing_privacy', 'bool');
    }
}
