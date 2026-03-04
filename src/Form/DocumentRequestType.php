<?php

namespace App\Form;

use App\Entity\DocumentRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DocumentRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('documentType', ChoiceType::class, [
                'label' => 'Document Type',
                'choices' => [
                    'Internship Certificate' => 'attestation_stage',
                    'Enrollment Certificate' => 'attestation_inscription',
                    'Transcript' => 'releve_notes',
                    'Certificate of Achievement' => 'attestation_reussite',
                    'Student Status Certificate' => 'certificat_scolarite',
                    'Other' => 'autre',
                ],
                'placeholder' => 'Select a document type',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please select a document type.']),
                ],
            ])
            ->add('additionalInfo', TextareaType::class, [
                'label' => 'Additional Information',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Add any relevant details...', 'maxlength' => 1000],
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Additional information cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentRequest::class,
        ]);
    }
}
