<?php

namespace App\Form;

use App\Entity\ForumCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ForumCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Category Name',
                'attr' => [
                    'placeholder' => 'e.g. General Discussion',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'You must enter a category name.']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'The category name must be at least {{ limit }} characters.',
                        'maxMessage' => 'The category name cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Describe what this category is about...',
                    'class' => 'form-control',
                    'rows' => 3
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 500,
                        'maxMessage' => 'The description cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icon (Bootstrap Icon class)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'e.g. bi-chat-dots',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 100,
                        'maxMessage' => 'The icon class cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Display Order',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0
                ],
                'constraints' => [
                    new Assert\NotNull(['message' => 'You must enter a display order.']),
                    new Assert\PositiveOrZero(['message' => 'The display order must be 0 or a positive number.']),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumCategory::class,
        ]);
    }
}
