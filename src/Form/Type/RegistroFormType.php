<?php

namespace App\Form\Type;

use App\Entity\Sonata\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

class RegistroFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'translation_domain' => 'SonataUserBundle', // O FOSUserBundle si tienes las traducciones activas
                'required' => true
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => ['translation_domain' => 'SonataUserBundle'],
                'first_options' => ['label' => 'form.password'],
                'second_options' => ['label' => 'form.password_confirmation'],
                'invalid_message' => 'fos_user.password.mismatch',
                // 'error_bubbling' => true, // En SF6 suele ser mejor false para ver el error junto al campo
            ])
            ->add('privacidad', CheckboxType::class, [
                'label' => 'He leído y acepto la política de privacidad',
                'mapped' => false, // IMPORTANTE: Esto no se guarda en la entidad User
                'required' => true,
                'constraints' => [
                    new IsTrue([
                        'message' => 'Debes aceptar la política de privacidad para registrarte.',
                    ]),
                ],
            ])

            // Si quieres incluir los datos de contacto (Nombre, Teléfono, etc.) descomenta esto:
            /*
            ->add('contacto', ContactoType::class, [
                'label' => 'Datos de Usuario'
            ])
            */
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class, // Tu entidad actual de Symfony 6
        ]);
    }
}