<?php
// src/Form/Type/ContactoType.php

namespace App\Form\Type;

use App\Entity\Contacto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class)
            ->add('apellidos', TextType::class)
            ->add('cif', TextType::class, array('label'=>'DNI/CIF','required' => false))
            ->add('telefonoMovil', TextType::class, array('label' => 'Telefono Móvil', 'required' => false))
            ->add('telefonoOtro', TextType::class, array('label' => 'Otro Telefono', 'required' => false))
            // --- INICIO DE LA CORRECCIÓN ---
            // Ahora usa el formulario específico para la dirección de facturación.
            ->add('direccionFacturacion', DireccionFacturacionType::class, [
                'label' => false, 'required' => false
            ]);
        // --- FIN DE LA CORRECCIÓN ---
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Contacto::class]);
    }
}

