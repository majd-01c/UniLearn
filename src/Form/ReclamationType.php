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

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Sujet de la réclamation',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Problème avec la note d\'examen'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'attr' => ['class' => 'form-control', 'rows' => 6, 'placeholder' => 'Décrivez votre réclamation en détail...'],
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
