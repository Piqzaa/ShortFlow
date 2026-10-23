<?php

namespace App\Form;

use App\Service\AiCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AiIdeaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        $favoriteVariants = [];
        foreach ((new AiCatalog())->groups() as $groupId => $group) {
            if (in_array($groupId, $options['favorite_models'], true)) {
                $favoriteVariants = array_merge($favoriteVariants, $group['variants']);
            }
        }
        foreach (AiCatalog::MODELS as $id => $model) {
            $resolution = str_contains(strtolower($model['name']), strtolower($model['quality'])) ? '' : ' · '.('4k' === $model['quality'] ? '4K' : $model['quality']);
            $disconnected = isset($options['connected_providers'][$model['provider']]) && !$options['connected_providers'][$model['provider']];
            $choices[(in_array($id, $favoriteVariants, true) ? '★ ' : '').$model['name'].$resolution.' · '.AiCatalog::PROVIDERS[$model['provider']].($disconnected ? ' · à connecter' : '')] = $id;
        }
        $builder->add('target', IntegerType::class, ['label' => 'Durée finale souhaitée (secondes)', 'required' => false, 'constraints' => [new Assert\Range(min: 1, max: 60)], 'attr' => ['min' => 1, 'max' => 60]])
            ->add('continuity', ChoiceType::class, ['label' => 'Comment enchaîner les clips ?', 'required' => true, 'placeholder' => false, 'empty_data' => 'independent', 'choices' => ['Plusieurs scènes' => 'independent', 'Prolonger l’action' => 'linked']])
            ->add('mute', CheckboxType::class, ['label' => 'Couper le son des plans au montage', 'required' => false])
            ->add('revision', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex('/^\d+$/D')]])
            ->add('shots', CollectionType::class, ['entry_type' => AiShotType::class, 'entry_options' => ['durations' => (new AiCatalog())->durations()], 'allow_add' => true, 'allow_delete' => true, 'label' => false, 'required' => false, 'constraints' => [new Assert\Count(max: 8)]]);
        $builder
            ->add('source', ChoiceType::class, ['label' => 'Point de départ', 'choices' => ['Décrire une vidéo' => 'text', 'Animer une image' => 'image'], 'placeholder' => false, 'required' => false, 'empty_data' => 'text'])
            ->add('reference', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex(pattern: '/^[a-f0-9]{48}\.jpg$/D', groups: ['Default', 'connection', 'autosave'])]])
            ->add('endReference', HiddenType::class, ['required' => false, 'constraints' => [new Assert\Regex(pattern: '/^[a-f0-9]{48}\.jpg$/D', groups: ['Default', 'connection', 'autosave'])]])
            ->add('useEnd', CheckboxType::class, ['label' => 'Ajouter une image de fin', 'required' => false])
            ->add('imageFit', ChoiceType::class, ['label' => 'Ajustement des images', 'choices' => ['Recadrage centré' => 'crop', 'Image entière (bandes)' => 'contain'], 'placeholder' => false, 'required' => false, 'empty_data' => 'crop'])
            ->add('title', TextType::class, ['label' => 'Nom de ta création', 'required' => false, 'help' => 'Facultatif : un nom sera proposé à partir de ton idée.', 'constraints' => [new Assert\Length(max: 120, groups: ['Default', 'connection', 'autosave'])], 'attr' => ['maxlength' => 120]])
            ->add('prompt', TextareaType::class, ['label' => 'Ton idée', 'help' => 'Quelques mots suffisent pour proposer un scénario. Tu peux aussi écrire tes scènes toi-même.', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 1500, groups: ['Default', 'connection', 'autosave'])], 'attr' => ['rows' => 4, 'maxlength' => 1500, 'placeholder' => 'Milo, mon chat blanc joueur, découvre un aspirateur robot…']])
            ->add('model', ChoiceType::class, ['label' => 'Modèle, résolution et fournisseur', 'placeholder' => 'Choisir un modèle', 'choices' => $choices, 'choice_attr' => static fn (string $id): array => isset($options['connected_providers'][AiCatalog::MODELS[$id]['provider']]) && !$options['connected_providers'][AiCatalog::MODELS[$id]['provider']] ? ['class' => 'catalog-option-disconnected'] : [], 'constraints' => [new Assert\NotBlank(groups: ['Default', 'connection'])]])
            ->add('accessMode', ChoiceType::class, ['label' => 'Connexion à utiliser', 'choices' => ['Ma clé API' => 'api', 'Mon compte Fal connecté par navigateur' => 'mcp'], 'help' => 'Un seul accès suffit. Pour Fal, la connexion navigateur évite de copier une clé ; vérifie le compte et ses crédits chez Fal.', 'constraints' => [new Assert\NotBlank()]])
            ->add('seconds', ChoiceType::class, ['label' => 'Durée du plan', 'placeholder' => 'Choisir une durée', 'choices' => array_combine(array_map(static fn (int $s): string => $s.' secondes', (new AiCatalog())->durations()), (new AiCatalog())->durations()), 'constraints' => [new Assert\NotBlank()]])
            ->add('ratio', ChoiceType::class, ['label' => 'Format', 'placeholder' => 'Choisir un format', 'choices' => ['Vertical · 9:16' => '9:16', 'Paysage · 16:9' => '16:9', 'Carré · 1:1' => '1:1', 'Celui de l’image' => 'auto'], 'constraints' => [new Assert\NotBlank()]]);
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            $form = $event->getForm();
            if (!isset(AiCatalog::MODELS[$data['model'] ?? ''])) {
                return;
            }
            if (in_array('autosave', $form->getConfig()->getOption('validation_groups') ?? [], true)) {
                return;
            }
            $model = AiCatalog::MODELS[$data['model']];
            if (($model['image'] ?? false) !== ('image' === ($data['source'] ?? 'text'))) {
                $form->get('model')->addError(new FormError('Choisis un modèle compatible avec ton point de départ.'));
            }
            if (($model['image'] ?? false) && empty($data['reference'])) {
                $form->get('reference')->addError(new FormError('Importe une image de départ avant de continuer.'));
            }
            if (($model['image'] ?? false) && ($data['useEnd'] ?? false)) {
                if (!isset($model['endImageField'])) {
                    $form->get('model')->addError(new FormError('Ce modèle n’accepte pas d’image de fin. Choisis un modèle Début + fin ou retire cette option.'));
                }
                if (empty($data['endReference'])) {
                    $form->get('endReference')->addError(new FormError('Importe ton image de fin avant de continuer.'));
                }
            }
            if (($model['requiresEnd'] ?? false) && !($data['useEnd'] ?? false)) {
                $form->get('model')->addError(new FormError('Ce parcours nécessite une image de début et une image de fin.'));
            }
            if ('mcp' === ($data['accessMode'] ?? 'api') && 'fal' !== $model['provider']) {
                $form->get('accessMode')->addError(new FormError('Ce fournisseur utilise une clé API dans VydFlow.'));
            }
            if (in_array('connection', $form->getConfig()->getOption('validation_groups') ?? [], true)) {
                return;
            }
            try {
                (new AiCatalog())->validatePrompt($data['model'], $data['prompt'] ?? '');
            } catch (\InvalidArgumentException $error) {
                $form->get('prompt')->addError(new FormError($error->getMessage()));
            }
            if (!$form->getConfig()->getOption('composed') && !in_array($data['seconds'] ?? null, $model['seconds'], true)) {
                $form->get('seconds')->addError(new FormError('Cette durée n’est pas disponible pour ce modèle.'));
            }
            if (!in_array($data['ratio'] ?? null, $model['ratios'], true)) {
                $form->get('ratio')->addError(new FormError('Ce format n’est pas disponible pour ce modèle et ce fournisseur.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('composed', false);
        $resolver->setAllowedTypes('composed', 'bool');
        $resolver->setDefault('connected_providers', []);
        $resolver->setAllowedTypes('connected_providers', 'array');
        $resolver->setDefault('favorite_models', []);
        $resolver->setAllowedTypes('favorite_models', 'array');
    }
}
