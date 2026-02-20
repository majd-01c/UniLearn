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
                'label' => 'Type de document',
                'choices' => [
                    'Attestation de Stage' => 'attestation_stage',
                    'Attestation d\'Inscription' => 'attestation_inscription',
                    'Relevé de Notes' => 'releve_notes',
                    'Attestation de Réussite' => 'attestation_reussite',
                    'Certificat de Scolarité' => 'certificat_scolarite',
                    'Autre' => 'autre',
                ],
                'placeholder' => 'Sélectionnez un type de document',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Vous devez sélectionner un type de document.']),
                ],
            ])
            ->add('additionalInfo', TextareaType::class, [
                'label' => 'Informations complémentaires',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Ajoutez des détails si nécessaire...'],
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Les informations complémentaires ne peuvent pas dépasser {{ limit }} caractères.',
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
