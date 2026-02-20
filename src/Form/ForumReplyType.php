<?php

namespace App\Form;

use App\Entity\ForumReply;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ForumReplyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Your Reply',
                'attr' => [
                    'placeholder' => 'Write your reply here...',
                    'class' => 'form-control',
                    'rows' => 5
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Your reply cannot be empty.']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 5000,
                        'minMessage' => 'Your reply must be at least {{ limit }} characters long.',
                        'maxMessage' => 'Your reply cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumReply::class,
        ]);
    }
}
