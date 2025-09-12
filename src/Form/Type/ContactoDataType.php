<?php
// src/Form/Type/ContactoDataType.php

namespace App\Form\Type;

use App\Entity\Contacto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

// MIGRACIÓN: Se ha cambiado el nombre de la clase a ContactoDataType
final class ContactoDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('telefonoMovil', TextType::class, [
                'label' => 'Teléfono Móvil',
                'required' => false,
            ])
            ->add('telefonoOtro', TextType::class, [
                'label' => 'Otro Teléfono',
                'required' => false,
            ])
            ->add('direccion', DireccionType::class, [
                'label' => 'DIRECCIÓN',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contacto::class,
        ]);
    }
}
