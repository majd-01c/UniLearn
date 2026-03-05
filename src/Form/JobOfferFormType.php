<?php

namespace App\Form;

use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobOfferType as JobOfferTypeEnum;
use App\Service\JobOffer\SkillsProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form for creating and editing job offers.
 * Validation constraints are defined on the entity (single source of truth).
 */
class JobOfferFormType extends AbstractType
{
    public function __construct(
        private readonly SkillsProvider $skillsProvider,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User|null $partner */
        $partner = $options['partner'];

        // Get skill choices (value => label format)
        $skillChoices = [];
        foreach ($this->skillsProvider->getAllSkillsForPartner($partner) as $skill) {
            $skillChoices[$skill] = $skill;
        }

        // Education level choices
        $educationChoices = array_flip($this->skillsProvider->getEducationLevels());

        // Language choices
        $languageChoices = array_combine(
            $this->skillsProvider->getLanguages(),
            $this->skillsProvider->getLanguages()
        );

        // Experience year choices
        $experienceChoices = array_flip($this->skillsProvider->getExperienceYearOptions());

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du poste',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ex: Développeur Full Stack PHP',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'You must enter a job title.']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'The job title must be at least {{ limit }} characters.',
                        'maxMessage' => 'The job title cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('type', EnumType::class, [
                'label' => 'Type de contrat',
                'class' => JobOfferTypeEnum::class,
                'required' => true,
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn($choice) => ucfirst(strtolower(str_replace('_', ' ', $choice->value))),
                'constraints' => [
                    new Assert\NotNull(['message' => 'You must select a job type.']),
                ],
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ex: Tunis, Tunisie ou Télétravail',
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'The location cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description du poste',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Décrivez les responsabilités et les tâches...',
                    'rows' => 8,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'You must provide a job description.']),
                    new Assert\Length([
                        'min' => 20,
                        'max' => 10000,
                        'minMessage' => 'The description must be at least {{ limit }} characters.',
                        'maxMessage' => 'The description cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('requirements', TextareaType::class, [
                'label' => 'Prérequis (texte libre)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Listez les qualifications et compétences...',
                    'rows' => 4,
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 5000,
                        'maxMessage' => 'Requirements cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            
            // ATS Requirement Fields - Using hidden fields for dynamic skill management
            ->add('requiredSkills', ChoiceType::class, [
                'label' => 'Compétences requises (ATS)',
                'choices' => $skillChoices,
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => [
                    'class' => 'd-none skills-data',
                    'data-field-type' => 'required',
                ],
                'label_attr' => [
                    'class' => 'form-label fw-bold',
                ],
                'help' => 'Sélectionnez les compétences que le candidat DOIT avoir',
            ])
            ->add('preferredSkills', ChoiceType::class, [
                'label' => 'Compétences souhaitées (bonus)',
                'choices' => $skillChoices,
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => [
                    'class' => 'd-none skills-data',
                    'data-field-type' => 'preferred',
                ],
                'label_attr' => [
                    'class' => 'form-label fw-bold',
                ],
                'help' => 'Compétences optionnelles qui donneront des points bonus',
            ])
            ->add('minExperienceYears', ChoiceType::class, [
                'label' => 'Expérience minimum',
                'choices' => $experienceChoices,
                'required' => false,
                'placeholder' => 'Aucune exigence',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('minEducation', ChoiceType::class, [
                'label' => 'Niveau d\'études minimum',
                'choices' => $educationChoices,
                'required' => false,
                'placeholder' => 'Aucune exigence',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('requiredLanguages', ChoiceType::class, [
                'label' => 'Langues requises',
                'choices' => $languageChoices,
                'multiple' => true,
                'expanded' => true, // Checkboxes
                'required' => false,
                'attr' => [
                    'class' => 'language-checkboxes',
                ],
            ])
            
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Date de publication',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Laisser vide pour publier immédiatement',
            ])
            ->add('expiresAt', DateTimeType::class, [
                'label' => 'Date d\'expiration',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Optionnel: date de fin de l\'offre',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => JobOffer::class,
            'partner' => null,
        ]);

        $resolver->setAllowedTypes('partner', [User::class, 'null']);
    }
}
