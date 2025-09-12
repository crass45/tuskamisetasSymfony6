<?php
// src/Form/Type/UsuarioType.php

namespace App\Form\Type;

use App\Entity\Sonata\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UsuarioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // MIGRACIÓN: El campo 'username' se ha renombrado a 'email' para coincidir con la entidad User.
            ->add('email', EmailType::class, [
                'label' => 'Dirección de email',
                'required' => true
            ])
            // MIGRACIÓN: Se usa 'plainPassword' para que SonataUserBundle se encargue de encriptarlo.
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Contraseña'],
                'second_options' => ['label' => 'Repetir contraseña'],
                'invalid_message' => 'Las contraseñas no coinciden.',
            ])
            // MIGRACIÓN: Se anida el formulario de ContactoType para la propiedad 'contacto'
            ->add('contacto', ContactoType::class, [
                'label' => 'DATOS DE USUARIO'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // MIGRACIÓN: El formulario ahora se asocia a la entidad User.
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
