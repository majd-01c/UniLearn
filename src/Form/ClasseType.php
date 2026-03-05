<?php

namespace App\Form;

use App\Entity\Classe;
use App\Entity\Program;
use App\Enum\ClasseStatus;
use App\Enum\Level;
use App\Enum\Specialty;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ClasseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Class Name',
                'attr' => [
                    'placeholder' => 'e.g., Computer Science L1 - Class A',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 2, 'max' => 255])
                ]
            ])
            ->add('program', EntityType::class, [
                'label' => 'Program',
                'class' => Program::class,
                'choice_label' => 'name',
                'placeholder' => '-- Select a program --',
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new Assert\NotNull()
                ]
            ])
            ->add('level', EnumType::class, [
                'label' => 'Level',
                'class' => Level::class,
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn(Level $level) => $level->label(),
                'placeholder' => '-- Select level --',
                'constraints' => [
                    new Assert\NotNull()
                ]
            ])
            ->add('specialty', EnumType::class, [
                'label' => 'Specialty',
                'class' => Specialty::class,
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn(Specialty $specialty) => $specialty->label(),
                'placeholder' => '-- Select specialty --',
                'constraints' => [
                    new Assert\NotNull()
                ]
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'Maximum Capacity',
                'attr' => [
                    'placeholder' => 'Max students',
                    'class' => 'form-control',
                    'min' => 1
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Positive(),
                    new Assert\Range(['min' => 1, 'max' => 500])
                ]
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Start Date',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank()
                ]
            ])
            ->add('endDate', DateType::class, [
                'label' => 'End Date',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank()
                ]
            ])
            ->add('imageUrl', UrlType::class, [
                'label' => 'Image URL (optional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://example.com/image.jpg',
                    'class' => 'form-control'
                ]
            ])
            ->add('status', EnumType::class, [
                'label' => 'Status',
                'class' => ClasseStatus::class,
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn(ClasseStatus $status) => $status->label(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Classe::class,
        ]);
    }
}
