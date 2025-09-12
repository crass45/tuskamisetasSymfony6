<?php
// src/Form/Type/ContactoType.php

namespace App\Form\Type;

use App\Entity\Contacto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ContactoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // MIGRACIÓN: Se usan los tipos de campo modernos y se añaden las reglas de validación.
        $builder
            ->add('nombre', TextType::class, [
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('apellidos', TextType::class, [
                'required' => false,
            ])
            ->add('cif', TextType::class, [
                'label' => 'DNI/CIF',
                'required' => false,
            ])
            ->add('telefonoMovil', TextType::class, [
                'label' => 'Teléfono Móvil',
                'required' => false,
            ])
            ->add('telefonoOtro', TextType::class, [
                'label' => 'Otro Teléfono',
                'required' => false,
            ])
            // MIGRACIÓN: El formulario incrustado ahora se añade usando su clase ::class
            ->add('direccionFacturacion', DireccionType::class, [ // <-- Asegúrate de que el nombre es 'direccionFacturacion'
                'label' => 'DIRECCIÓN DE FACTURACIÓN',
                'required' => true,
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

