<?php

namespace App\Form;

use App\Entity\JobOffer;
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
        // Get skill choices (value => label format)
        $skillChoices = [];
        foreach ($this->skillsProvider->getSkillsByCategory() as $category => $skills) {
            foreach ($skills as $skill) {
                $skillChoices[$skill] = $skill;
            }
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
            ])
            ->add('type', EnumType::class, [
                'label' => 'Type de contrat',
                'class' => JobOfferTypeEnum::class,
                'required' => true,
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn($choice) => ucfirst(strtolower(str_replace('_', ' ', $choice->value))),
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ex: Tunis, Tunisie ou Télétravail',
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
            ])
            ->add('requirements', TextareaType::class, [
                'label' => 'Prérequis (texte libre)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Listez les qualifications et compétences...',
                    'rows' => 4,
                ],
            ])
            
            // ATS Requirement Fields
            ->add('requiredSkills', ChoiceType::class, [
                'label' => 'Compétences requises (ATS)',
                'choices' => $skillChoices,
                'multiple' => true,
                'expanded' => true, // Checkboxes
                'required' => false,
                'attr' => [
                    'class' => 'skills-checkboxes',
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
                'expanded' => true, // Checkboxes
                'required' => false,
                'attr' => [
                    'class' => 'skills-checkboxes',
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
        ]);
    }
}
