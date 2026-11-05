<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/** Preserve reference numbering when expanded checkboxes replace an older select. */
final class AiCharacterChoicesType extends AbstractType
{
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $order = [];
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use (&$order): void {
            $order = is_array($event->getData()) ? array_values(array_filter($event->getData(), 'is_string')) : [];
        }, 100);
        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) use (&$order): void {
            $data = $event->getData();
            if (is_array($data)) {
                $positions = array_flip($order);
                usort($data, static fn (string $a, string $b): int => ($positions[$a] ?? PHP_INT_MAX) <=> ($positions[$b] ?? PHP_INT_MAX));
                $event->setData($data);
            }
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $positions = array_flip($form->getData() ?? []);
        uasort($view->children, static fn (FormView $a, FormView $b): int => ($positions[$a->vars['value']] ?? PHP_INT_MAX) <=> ($positions[$b->vars['value']] ?? PHP_INT_MAX));
    }
}
