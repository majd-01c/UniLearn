<?php

namespace App\Form;

use App\Entity\Schedule;
use App\Entity\Classe;
use App\Entity\Course;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('classe', EntityType::class, [
                'class' => Classe::class,
                'choice_label' => 'name',
                'label' => 'Classe',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('course', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'title',
                'label' => 'Cours',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('teacher', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'name',
                'label' => 'Enseignant',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dayOfWeek', ChoiceType::class, [
                'label' => 'Jour de la semaine',
                'choices' => [
                    'Lundi' => 'monday',
                    'Mardi' => 'tuesday',
                    'Mercredi' => 'wednesday',
                    'Jeudi' => 'thursday',
                    'Vendredi' => 'friday',
                    'Samedi' => 'saturday',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('room', TextType::class, [
                'label' => 'Salle',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Salle A101'],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Schedule::class,
        ]);
    }
}
