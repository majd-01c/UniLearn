<?php

namespace App\Form;

use App\Entity\ForumCategory;
use App\Entity\ForumTopic;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumTopicType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Topic Title',
                'attr' => [
                    'placeholder' => 'What is your question or topic about?',
                    'class' => 'form-control'
                ]
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Content',
                'attr' => [
                    'placeholder' => 'Describe your question or topic in detail...',
                    'class' => 'form-control',
                    'rows' => 8
                ]
            ])
            ->add('category', EntityType::class, [
                'class' => ForumCategory::class,
                'choice_label' => 'name',
                'label' => 'Category',
                'placeholder' => 'Select a category',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function (\App\Repository\ForumCategoryRepository $repo) {
                    return $repo->createQueryBuilder('c')
                        ->where('c.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('c.position', 'ASC');
                }
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumTopic::class,
        ]);
    }
}
