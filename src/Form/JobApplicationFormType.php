<?php

namespace App\Form;

use App\Entity\JobApplication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Form\Type\VichFileType;

/**
 * Form for job application submission
 */
class JobApplicationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => 'Cover Letter / Message',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Tell the employer why you are interested in this position...',
                    'rows' => 6,
                ],
                'constraints' => [
                    new Assert\Length(['max' => 5000]),
                ],
            ])
            ->add('cvFile', VichFileType::class, [
                'label' => 'Upload CV / Resume',
                'required' => false,
                'allow_delete' => false,
                'download_uri' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => JobApplication::class,
        ]);
    }
}
