<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('role', ChoiceType::class, [
                'choices' => [
                    'Student' => 'STUDENT',
                    'Teacher' => 'TEACHER',
                    'Admin' => 'ADMIN',
                    'Partner' => 'PARTNER',
                ],
                'label' => 'Role',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'You must select a role.']),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Please enter an email address.']),
                    new Email(['message' => 'Please enter a valid email address.']),
                    new Assert\Length(['max' => 180, 'maxMessage' => 'Email cannot exceed {{ limit }} characters.']),
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a password.']),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Password must be at least {{ limit }} characters long.',
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Is Active',
                'required' => false,
            ])
            ->add('name', TextType::class, [
                'label' => 'Full Name',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 200,
                        'maxMessage' => 'Full name cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 30,
                        'maxMessage' => 'Phone number cannot exceed {{ limit }} characters.',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[\d\s\+\-\(\)\.]*$/',
                        'message' => 'Please enter a valid phone number (digits, spaces, +, -, () allowed).',
                    ]),
                ],
            ])
            ->add('location', TextType::class, [
                'label' => 'Location',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Location cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('about', TextareaType::class, [
                'label' => 'About',
                'required' => false,
                'attr' => ['rows' => 4],
                'constraints' => [
                    new Assert\Length([
                        'max' => 2000,
                        'maxMessage' => 'About text cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('skillsInput', TextType::class, [
                'label' => 'Skills (comma-separated)',
                'required' => false,
                'mapped' => false,
                'help' => 'Enter skills separated by commas (e.g., PHP, JavaScript, MySQL)',
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'Is Verified',
                'required' => false,
            ])
            ->add('needsVerification', CheckboxType::class, [
                'label' => 'Needs Verification',
                'required' => false,
            ])
            ->add('emailVerifiedAt', DateTimeType::class, [
                'label' => 'Email Verified At',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('emailVerificationCode', TextType::class, [
                'label' => 'Email Verification Code',
                'required' => false,
            ])
            ->add('codeExpiryDate', DateTimeType::class, [
                'label' => 'Code Expiry Date',
                'required' => false,
                'widget' => 'single_text',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
