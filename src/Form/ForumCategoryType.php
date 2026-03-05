<?php

namespace App\Form;

use App\Entity\ForumCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
            ->add('icon', ChoiceType::class, [
                'label' => 'Icon',
                'required' => false,
                'placeholder' => '-- Select an icon --',
                'attr' => [
                    'class' => 'form-select'
                ],
                'choices' => [
                    'Chat & Communication' => [
                        'Chat Dots' => 'bi-chat-dots',
                        'Chat Left Text' => 'bi-chat-left-text',
                        'Chat Square' => 'bi-chat-square',
                        'Megaphone' => 'bi-megaphone',
                        'Envelope' => 'bi-envelope',
                        'Bell' => 'bi-bell',
                        'Broadcast' => 'bi-broadcast',
                    ],
                    'Education' => [
                        'Book' => 'bi-book',
                        'Mortarboard' => 'bi-mortarboard',
                        'Journal' => 'bi-journal-text',
                        'Pencil Square' => 'bi-pencil-square',
                        'Clipboard' => 'bi-clipboard-check',
                        'Award' => 'bi-award',
                        'Lightbulb' => 'bi-lightbulb',
                    ],
                    'Technology' => [
                        'Code Slash' => 'bi-code-slash',
                        'Terminal' => 'bi-terminal',
                        'Laptop' => 'bi-laptop',
                        'CPU' => 'bi-cpu',
                        'Database' => 'bi-database',
                        'Globe' => 'bi-globe',
                        'Bug' => 'bi-bug',
                    ],
                    'General' => [
                        'Question Circle' => 'bi-question-circle',
                        'Info Circle' => 'bi-info-circle',
                        'Star' => 'bi-star',
                        'Heart' => 'bi-heart',
                        'People' => 'bi-people',
                        'Flag' => 'bi-flag',
                        'Folder' => 'bi-folder',
                        'Gear' => 'bi-gear',
                        'Shield' => 'bi-shield-check',
                        'Trophy' => 'bi-trophy',
                        'Calendar' => 'bi-calendar-event',
                        'Lightning' => 'bi-lightning',
                    ],
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
