<?php

namespace App\Form;

use App\Entity\Evaluation\Reclamation;
use App\Entity\Course;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Subject',
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. Issue with exam grade', 'minlength' => 5, 'maxlength' => 255],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter a subject for your complaint.']),
                    new Assert\Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'Subject must be at least {{ limit }} characters.',
                        'maxMessage' => 'Subject cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Detailed Description',
                'attr' => ['class' => 'form-control', 'rows' => 6, 'placeholder' => 'Describe your complaint in detail...', 'minlength' => 20, 'maxlength' => 3000],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please provide a detailed description of your complaint.']),
                    new Assert\Length([
                        'min' => 20,
                        'max' => 3000,
                        'minMessage' => 'Description must be at least {{ limit }} characters.',
                        'maxMessage' => 'Description cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('relatedCourse', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'title',
                'label' => 'Related Course (optional)',
                'required' => false,
                'placeholder' => 'Select a course',
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
        ]);
    }
}
