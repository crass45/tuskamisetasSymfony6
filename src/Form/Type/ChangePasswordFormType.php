<?php
// src/Form/Type/ChangePasswordFormType.php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Por favor, introduce una contraseña',
                        ]),
                        new Length([
                            'min' => 6,
                            'minMessage' => 'Tu contraseña debe tener al menos {{ limit }} caracteres',
                            'max' => 4096,
                        ]),
                    ],
                    'label' => 'Nueva contraseña',
                    'attr' => ['class' => 'form-control form-control-lg']
                ],
                'second_options' => [
                    'label' => 'Repetir contraseña',
                    'attr' => ['class' => 'form-control form-control-lg']
                ],
                'invalid_message' => 'Las contraseñas no coinciden.',
                // No mapeamos este campo a la entidad User directamente
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
