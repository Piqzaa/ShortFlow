<?php

namespace App\Form;

use App\Service\AiCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
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

final class AiSeriesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $models = [];
        foreach (AiCatalog::MODELS as $id => $model) {
            $resolution = str_contains(strtolower($model['name']), strtolower($model['quality'])) ? '' : ' · '.('4k' === $model['quality'] ? '4K' : $model['quality']);
            $models[AiCatalog::PROVIDERS[$model['provider']]][$model['name'].$resolution] = $id;
        }
        $catalog = new AiCatalog();
        if ($options['creating']) {
            $builder->add('episodeCount', IntegerType::class, ['label' => 'Combien d’épisodes veux-tu préparer ?', 'help' => 'De 0 à 12 pour commencer. Tu pourras en ajouter ensuite.', 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 12)], 'attr' => ['min' => 0, 'max' => 12]]);
        }
        $builder
            ->add('name', TextType::class, ['label' => 'Nom de la série', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 120)], 'attr' => ['maxlength' => 120]])
            ->add('theme', TextareaType::class, ['label' => 'Thème et intention', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 700)], 'attr' => ['rows' => 3, 'maxlength' => 700]])
            ->add('style', TextareaType::class, ['label' => 'Style visuel', 'required' => false, 'constraints' => [new Assert\Length(max: 300)], 'attr' => ['rows' => 2, 'maxlength' => 300]])
            ->add('characters', TextareaType::class, ['label' => 'Description textuelle des personnages', 'required' => false, 'help' => 'Ce texte guide les prompts. Il ne remplace pas une image et ne garantit pas une apparence identique entre les clips.', 'constraints' => [new Assert\Length(max: 300)], 'attr' => ['rows' => 2, 'maxlength' => 300]])
            ->add('source', ChoiceType::class, ['mapped' => false, 'label' => 'Point de départ des vidéos', 'choices' => ['Décrire avec du texte' => 'text', 'Animer une image de début' => 'image', 'Garder un personnage de référence' => 'subject'], 'data' => (AiCatalog::MODELS[$builder->getData()['model'] ?? '']['subjectReference'] ?? false) ? 'subject' : ((AiCatalog::MODELS[$builder->getData()['model'] ?? '']['image'] ?? false) ? 'image' : 'text'), 'help' => 'Le choix du modèle ci-dessous détermine les références réellement envoyées.', 'attr' => ['data-series-settings-target' => 'source', 'data-action' => 'change->series-settings#changeSource']])
            ->add('model', ChoiceType::class, ['label' => 'Modèle et fournisseur', 'placeholder' => 'Choisir un modèle compatible', 'choices' => $models, 'attr' => ['data-series-settings-target' => 'model', 'data-action' => 'series-settings#change']])
            ->add('totalSeconds', IntegerType::class, ['label' => 'Durée finale des prochains épisodes (secondes)', 'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 1, max: 60)], 'attr' => ['min' => 1, 'max' => 60]])
            ->add('seconds', ChoiceType::class, ['label' => 'Durée d’un clip par défaut', 'choices' => array_combine(array_map(static fn (int $s): string => $s.' secondes', $catalog->durations()), $catalog->durations()), 'attr' => ['data-series-settings-target' => 'seconds']])
            ->add('ratio', ChoiceType::class, ['label' => 'Format', 'choices' => ['Vertical · 9:16' => '9:16', 'Paysage · 16:9' => '16:9', 'Carré · 1:1' => '1:1', 'Format de l’image' => 'auto'], 'attr' => ['data-series-settings-target' => 'ratio']])
            ->add('accessMode', ChoiceType::class, ['label' => 'Accès BYOK existant', 'choices' => ['Ma clé API' => 'api', 'Mon compte Fal connecté par navigateur' => 'mcp'], 'help' => 'La série utilise ta connexion dans VydFlow. Aucune clé à recopier.'])
            ->add('imageFit', ChoiceType::class, ['label' => 'Cadrage des images', 'choices' => ['Recadrage centré' => 'crop', 'Image entière avec bandes' => 'contain']])
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^\d+$/D')]]);
        foreach (['image' => 'Image de départ commune', 'endImage' => 'Image de fin commune (facultative selon le modèle)'] as $field => $label) {
            $builder->add($field, FileType::class, ['label' => $label, 'required' => false, 'constraints' => [new Assert\File(maxSize: '10M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])], 'attr' => ['accept' => 'image/jpeg,image/png,image/webp']]);
        }
        $builder->add('removeImage', CheckboxType::class, ['label' => 'Retirer l’image enregistrée à la sauvegarde', 'required' => false]);
        $builder->add('removeEndImage', CheckboxType::class, ['label' => 'Retirer l’image de fin enregistrée à la sauvegarde', 'required' => false]);
        $builder->add('subjectImages', FileType::class, ['label' => 'Autres références de personnages', 'multiple' => true, 'required' => false, 'help' => 'Vidu référence : jusqu’à sept images au total. Kling 3 image : jusqu’à deux personnages supplémentaires, avec un premier cadre propre à chaque plan. Aucun ajout n’est généré par IA.', 'constraints' => [new Assert\Count(max: 7), new Assert\All([new Assert\File(maxSize: '10M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])])], 'attr' => ['accept' => 'image/jpeg,image/png,image/webp']]);
        $choices = [];
        foreach ($options['subjects'] as $index => $name) {
            $choices['Retirer la référence supplémentaire '.($index + 1)] = $name;
        }
        $builder->add('removeSubjects', ChoiceType::class, ['label' => 'Références supplémentaires enregistrées', 'choices' => $choices, 'multiple' => true, 'expanded' => true, 'required' => false]);
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($catalog): void {
            $data = $event->getData();
            if (!isset(AiCatalog::MODELS[$data['model'] ?? ''])) {
                return;
            }
            try {
                $catalog->validate($data['model'], $data['seconds'] ?? 0, $data['ratio'] ?? '');
                if ('mcp' === ($data['accessMode'] ?? '') && 'fal' !== $catalog->model($data['model'])['provider']) {
                    throw new \InvalidArgumentException('La connexion navigateur est réservée à Fal. Choisis la clé API pour ce fournisseur.');
                }
            } catch (\InvalidArgumentException $error) {
                $event->getForm()->addError(new FormError($error->getMessage()));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('creating', false);
        $resolver->setAllowedTypes('creating', 'bool');
        $resolver->setDefault('subjects', []);
        $resolver->setAllowedTypes('subjects', 'array');
    }
}
