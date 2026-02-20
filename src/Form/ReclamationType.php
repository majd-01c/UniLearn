<?php

namespace App\Form;

use App\Entity\Reclamation;
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
                'label' => 'Sujet de la réclamation',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Problème avec la note d\'examen'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Vous devez saisir un sujet pour votre réclamation.']),
                    new Assert\Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'Le sujet doit comporter au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le sujet ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'attr' => ['class' => 'form-control', 'rows' => 6, 'placeholder' => 'Décrivez votre réclamation en détail...'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Vous devez fournir une description détaillée de votre réclamation.']),
                    new Assert\Length([
                        'min' => 20,
                        'max' => 3000,
                        'minMessage' => 'La description doit comporter au moins {{ limit }} caractères.',
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('relatedCourse', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'title',
                'label' => 'Cours concerné (optionnel)',
                'required' => false,
                'placeholder' => 'Sélectionnez un cours',
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
