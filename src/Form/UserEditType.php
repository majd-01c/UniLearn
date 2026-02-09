<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form for editing an existing user (Admin only)
 */
class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Email is required']),
                    new Assert\Email(['message' => 'Please enter a valid email address']),
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Role',
                'required' => true,
                'choices' => [
                    'Administrator' => 'ADMIN',
                    'Student' => 'STUDENT',
                    'Teacher' => 'TEACHER',
                    'Business Partner' => 'BUSINESS_PARTNER',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Role is required']),
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'required' => true,
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'First name is required']),
                    new Assert\Length(['max' => 100]),
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'required' => true,
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Last name is required']),
                    new Assert\Length(['max' => 100]),
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\Length(['max' => 20]),
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description/Bio',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                ],
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'Account Status',
                'required' => true,
                'choices' => [
                    'Active' => true,
                    'Inactive' => false,
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
