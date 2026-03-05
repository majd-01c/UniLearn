<?php

namespace App\Form;

use App\Entity\ForumComment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ForumCommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => $options['is_reply'] ? 'Your Reply' : 'Your Comment',
                'attr' => [
                    'placeholder' => $options['is_reply'] ? 'Write your reply here...' : 'Write your comment here...',
                    'class' => 'form-control',
                    'rows' => $options['is_reply'] ? 3 : 5
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Your comment cannot be empty.']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 5000,
                        'minMessage' => 'Your comment must be at least {{ limit }} characters long.',
                        'maxMessage' => 'Your comment cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumComment::class,
            'is_reply' => false,
        ]);
    }
}
