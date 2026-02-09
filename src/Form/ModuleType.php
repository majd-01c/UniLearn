<?php

namespace App\Form;

use App\Entity\Module;
use App\Enum\PeriodUnit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ModuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Module Name',
                'attr' => [
                    'placeholder' => 'Enter module name',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 2, 'max' => 255])
                ]
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'Duration',
                'attr' => [
                    'placeholder' => 'Enter duration',
                    'class' => 'form-control',
                    'min' => 1
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Positive()
                ]
            ])
            ->add('periodUnit', EnumType::class, [
                'label' => 'Period Unit',
                'class' => PeriodUnit::class,
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn($choice) => ucfirst($choice->value),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Module::class,
        ]);
    }
}
