<?php

namespace App\Form;

use App\Service\AiCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints as Assert;

final class AiIdeaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach (AiCatalog::MODELS as $id => $model) {
            $choices[$model['name'].' · '.AiCatalog::PROVIDERS[$model['provider']]] = $id;
        }
        $builder
            ->add('title', TextType::class, ['label' => 'Nom de ta création', 'required' => false, 'help' => 'Facultatif : un nom sera proposé à partir de ton idée.', 'constraints' => [new Assert\Length(max: 120)], 'attr' => ['maxlength' => 120]])
            ->add('prompt', TextareaType::class, ['label' => 'Ton idée', 'help' => 'Décris le sujet, le mouvement et l’ambiance. Un prompt en anglais est conseillé pour ces modèles.', 'constraints' => [new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 1500)], 'attr' => ['rows' => 6, 'maxlength' => 1500, 'placeholder' => 'Une caméra suit un cycliste au lever du soleil…']])
            ->add('model', ChoiceType::class, ['label' => 'Modèle et fournisseur', 'placeholder' => 'Choisir un modèle', 'choices' => $choices, 'constraints' => [new Assert\NotBlank()]])
            ->add('accessMode', ChoiceType::class, ['label' => 'Connexion à utiliser', 'choices' => ['Ma clé API' => 'api', 'Mon compte Fal connecté par navigateur' => 'mcp'], 'help' => 'La connexion navigateur concerne Fal. Elle utilise les crédits du compte MCP actif chez Fal.', 'constraints' => [new Assert\NotBlank()]])
            ->add('seconds', ChoiceType::class, ['label' => 'Durée du plan', 'placeholder' => 'Choisir une durée', 'choices' => array_combine(array_map(static fn (int $s): string => $s.' secondes', (new AiCatalog())->durations()), (new AiCatalog())->durations()), 'constraints' => [new Assert\NotBlank()]])
            ->add('ratio', ChoiceType::class, ['label' => 'Format', 'placeholder' => 'Choisir un format', 'choices' => ['Vertical · 9:16' => '9:16', 'Paysage · 16:9' => '16:9', 'Carré · 1:1' => '1:1'], 'constraints' => [new Assert\NotBlank()]]);
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            $form = $event->getForm();
            if (!isset(AiCatalog::MODELS[$data['model'] ?? ''])) {
                return;
            }
            $model = AiCatalog::MODELS[$data['model']];
            if ('mcp' === ($data['accessMode'] ?? 'api') && 'fal' !== $model['provider']) {
                $form->get('accessMode')->addError(new FormError('Ce fournisseur utilise une clé API dans VydFlow.'));
            }
            try {
                (new AiCatalog())->validatePrompt($data['model'], $data['prompt'] ?? '');
            } catch (\InvalidArgumentException $error) {
                $form->get('prompt')->addError(new FormError($error->getMessage()));
            }
            if (!in_array($data['seconds'] ?? null, $model['seconds'], true)) {
                $form->get('seconds')->addError(new FormError('Cette durée n’est pas disponible pour ce modèle.'));
            }
            if (!in_array($data['ratio'] ?? null, $model['ratios'], true)) {
                $form->get('ratio')->addError(new FormError('Ce format n’est pas disponible pour ce modèle et ce fournisseur.'));
            }
        });
    }
}
