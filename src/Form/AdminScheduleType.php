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
use Symfony\Component\Validator\Constraints as Assert;

class AdminScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('classe', EntityType::class, [
                'class' => Classe::class,
                'choice_label' => 'name',
                'label' => 'Class',
                'placeholder' => '-- Select a class --',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotNull(['message' => 'You must select a class.']),
                ],
            ])
            ->add('course', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'title',
                'label' => 'Course',
                'placeholder' => '-- Select a course --',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotNull(['message' => 'You must select a course.']),
                ],
            ])
            ->add('teacher', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn(User $u) => $u->getName() . ' (' . $u->getEmail() . ')',
                'label' => 'Teacher',
                'required' => false,
                'placeholder' => '-- Select a teacher --',
                'attr' => ['class' => 'form-control'],
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.role = :role')
                        ->setParameter('role', 'TEACHER')
                        ->orderBy('u.name', 'ASC');
                },
            ])
            ->add('dayOfWeek', ChoiceType::class, [
                'label' => 'Day of the Week',
                'choices' => [
                    'Monday' => 'monday',
                    'Tuesday' => 'tuesday',
                    'Wednesday' => 'wednesday',
                    'Thursday' => 'thursday',
                    'Friday' => 'friday',
                    'Saturday' => 'saturday',
                ],
                'placeholder' => '-- Select a day --',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'You must select a day of the week.']),
                ],
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Start Time',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotNull(['message' => 'You must enter a start time.']),
                ],
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'End Time',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotNull(['message' => 'You must enter an end time.']),
                ],
            ])
            ->add('room', TextType::class, [
                'label' => 'Room',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. Room A101'],
                'constraints' => [
                    new Assert\Length([
                        'max' => 100,
                        'maxMessage' => 'The room name cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Start Date',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotNull(['message' => 'You must enter a start date.']),
                ],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'End Date',
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
