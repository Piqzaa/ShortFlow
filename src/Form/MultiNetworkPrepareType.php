<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class MultiNetworkPrepareType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $types = ['youtube' => YouTubePublishType::class, 'instagram' => InstagramPublishType::class, 'tiktok' => TikTokPublishType::class];
        $builder->add('draft_key', HiddenType::class, ['mapped' => false, 'data' => $options['draft_key'], 'constraints' => [new Assert\EqualTo($options['draft_key'])]]);
        foreach ($options['networks'] as $network) {
            $childOptions = ['videos' => $options['videos'], 'draft_key' => $options['draft_key'], 'csrf_protection' => false, 'label' => false];
            if ('tiktok' === $network) {
                $childOptions['creator'] = $options['creator'];
                // An offline preview may omit privacy; enqueue still requires live capabilities.
                $childOptions['allow_missing_privacy'] = [] === $options['creator'];
            }
            $child = $builder->create($network, $types[$network], $childOptions);
            $child->remove('draft_key')->remove('confirm');
            $builder->add($child);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['videos', 'networks', 'draft_key', 'creator']);
        $resolver->setAllowedTypes('videos', 'array')->setAllowedTypes('networks', 'array')->setAllowedTypes('draft_key', 'string')->setAllowedTypes('creator', 'array');
    }
}
