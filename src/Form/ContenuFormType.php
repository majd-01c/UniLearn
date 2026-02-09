<?php

namespace App\Form;

use App\Entity\Contenu;
use App\Enum\ContenuType;
use App\Enum\FileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ContenuFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Content Title',
                'attr' => [
                    'placeholder' => 'Enter content title',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 2, 'max' => 255])
                ]
            ])
            ->add('type', EnumType::class, [
                'label' => 'Content Type',
                'class' => ContenuType::class,
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn($choice) => ucfirst($choice->value),
            ])
            ->add('fileUrl', TextType::class, [
                'label' => 'File URL',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter file URL (optional)',
                    'class' => 'form-control'
                ]
            ])
            ->add('fileType', EnumType::class, [
                'label' => 'File Type',
                'class' => FileType::class,
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn($choice) => strtoupper($choice->value),
                'placeholder' => 'Select file type (optional)'
            ])
            ->add('published', CheckboxType::class, [
                'label' => 'Published',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contenu::class,
        ]);
    }
}
