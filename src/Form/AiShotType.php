<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AiShotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($options['character_choices'] as $label => $token) {
            $id = explode(':', $token)[0];
            if (!in_array($id, $choices, true)) {
                $choices[$label] = $id;
            }
        }
        $builder->add('characterIds', ChoiceType::class, ['label' => 'Personnages présents dans cette scène', 'choices' => $choices, 'multiple' => true, 'required' => false, 'help' => 'Uniquement parmi les fiches choisies pour cette préparation. Aucune sélection signifie aucun personnage de la bibliothèque dans ce plan.', 'attr' => ['size' => 3]])
            ->add('characterIdsManaged', HiddenType::class, ['data' => '1', 'required' => false, 'empty_data' => '0'])
            ->add('sceneDescription', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 300, groups: ['Default', 'connection', 'autosave'])]])
            ->add('translatedIdentity', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 300, groups: ['Default', 'connection', 'autosave'])]])
            ->add('translationSource', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex('/^[a-f0-9]{64}$/D', groups: ['Default', 'connection', 'autosave'])]])
            ->add('promptOnly', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Choice(['', '1', 'manual'], groups: ['Default', 'connection', 'autosave'])]])
            ->add('startReference', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex('/^[a-f0-9]{48}\.jpg$/D')]])
            ->add('endReference', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex('/^[a-f0-9]{48}\.jpg$/D')]])
            ->add('referenceFit', HiddenType::class, ['required' => false, 'empty_data' => 'crop', 'constraints' => [new Assert\Choice(['crop', 'contain'])]])
            ->add('prompt', TextareaType::class, ['label' => 'Prompt vidéo — consignes envoyées au modèle', 'required' => false, 'constraints' => [new Assert\Length(max: 1500, groups: ['Default', 'connection', 'autosave'])], 'attr' => ['rows' => 4, 'maxlength' => 1500, 'placeholder' => 'Décris en anglais l’action et le mouvement de caméra…']])
            ->add('imagePrompt', TextareaType::class, ['label' => 'Mes consignes pour l’image de début', 'required' => false, 'constraints' => [new Assert\Length(max: 3000, groups: ['Default', 'connection', 'autosave'])], 'attr' => ['rows' => 6, 'maxlength' => 3000, 'placeholder' => 'Décris l’image souhaitée ou utilise « Proposer des consignes »…']])
            ->add('seconds', ChoiceType::class, ['label' => 'Durée', 'choices' => array_combine(array_map(static fn (int $s): string => $s.' s', $options['durations']), $options['durations'])]);
        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if ('1' !== (string) ($data['characterIdsManaged'] ?? '0')) {
                unset($data['characterIds']);
                $event->setData($data);
            }
        });
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($options): void {
            $data = $event->getData() ?? [];
            if (!in_array($data['promptOnly'] ?? '', ['1', 'manual'], true)) {
                $identity = trim($data['translatedIdentity'] ?? $options['visual_context']);
                $prompt = $data['prompt'] ?? '';
                if ('' !== trim($prompt) && '' !== $identity && !str_contains($prompt, rtrim($identity, '. '))) {
                    $data['prompt'] = $identity."\n".$prompt;
                }
            }
            $data['promptOnly'] = 'manual' === ($data['promptOnly'] ?? '') ? 'manual' : '1';
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('durations');
        $resolver->setAllowedTypes('durations', 'array');
        $resolver->setDefault('character_choices', []);
        $resolver->setAllowedTypes('character_choices', 'array');
        $resolver->setDefault('visual_context', '');
        $resolver->setAllowedTypes('visual_context', 'string');
    }
}
