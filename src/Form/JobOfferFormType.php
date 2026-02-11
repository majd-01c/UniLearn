<?php

namespace App\Form;

use App\Entity\JobOffer;
use App\Enum\JobOfferType as JobOfferTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form for creating and editing job offers.
 * Validation constraints are defined on the entity (single source of truth).
 */
class JobOfferFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Job Title',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., Software Developer Intern',
                ],
            ])
            ->add('type', EnumType::class, [
                'label' => 'Job Type',
                'class' => JobOfferTypeEnum::class,
                'required' => true,
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn($choice) => ucfirst(strtolower(str_replace('_', ' ', $choice->value))),
            ])
            ->add('location', TextType::class, [
                'label' => 'Location',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., Paris, France or Remote',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Job Description',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Describe the job responsibilities, duties, and details...',
                    'rows' => 8,
                ],
            ])
            ->add('requirements', TextareaType::class, [
                'label' => 'Requirements',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'List the qualifications, skills, and requirements...',
                    'rows' => 6,
                ],
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Publish Date',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Leave empty to publish immediately',
            ])
            ->add('expiresAt', DateTimeType::class, [
                'label' => 'Expiration Date',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Optional: set when this offer should expire',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => JobOffer::class,
        ]);
    }
}
