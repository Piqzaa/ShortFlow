<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class VideoUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, ['label' => 'Ta vidéo', 'constraints' => [new Assert\NotBlank(message: 'Choisis une vidéo.'), new Assert\File(maxSize: '100Mi', extensions: ['mp4', 'webm'], mimeTypes: ['video/mp4', 'video/webm'], maxSizeMessage: 'La vidéo dépasse la limite de 100 Mo.', extensionsMessage: 'Choisis un véritable fichier MP4 ou WebM.', mimeTypesMessage: 'Ce fichier n’est pas une vidéo MP4 ou WebM.')], 'attr' => ['accept' => '.mp4,.webm,video/mp4,video/webm', 'data-upload-target' => 'file', 'data-action' => 'change->upload#selected']])
            ->add('title', TextType::class, ['label' => 'Nom de la vidéo', 'required' => false, 'help' => 'Facultatif. Tu pourras le modifier ensuite.', 'constraints' => [new Assert\Length(max: 120, maxMessage: 'Le nom est limité à 120 caractères.')], 'attr' => ['maxlength' => 120, 'placeholder' => 'Mon prochain mouvement']]);
    }
}
