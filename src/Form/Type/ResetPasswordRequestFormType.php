<?php
// src/Form/Type/ResetPasswordRequestFormType.php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Introduce la dirección de email asociada a tu cuenta',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Por favor, introduce tu email',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'tu-email@ejemplo.com'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
