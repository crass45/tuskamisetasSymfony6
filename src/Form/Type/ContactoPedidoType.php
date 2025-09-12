<?php
// src/Form/Type/ContactoPedidoType.php

namespace App\Form\Type;

use App\Entity\Contacto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ContactoPedidoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // MIGRACIÓN: Se usan los tipos de campo modernos y se añaden las reglas de validación aquí.
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'constraints' => [
                    new NotBlank(['message' => 'El nombre es obligatorio.']),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'constraints' => [
                    new NotBlank(['message' => 'El email es obligatorio.']),
                    new EmailConstraint(['message' => 'La dirección de email no es válida.']),
                ],
            ])
            ->add('ciudad', TextType::class, [
                'label' => 'Ciudad',
                'required' => false,
            ])
            ->add('telefonoMovil', TextType::class, [
                'label' => 'Teléfono',
                'required' => false,
            ])
            ->add('observaciones', TextareaType::class, [
                'label' => 'Observaciones',
                'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // MIGRACIÓN: Se actualiza el nombre del método y la sintaxis de 'data_class'.
        $resolver->setDefaults([
            'data_class' => Contacto::class,
        ]);
    }

    // MIGRACIÓN: El método getName() es obsoleto y se ha eliminado.
}
