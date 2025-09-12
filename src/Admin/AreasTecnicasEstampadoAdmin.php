<?php
// src/Admin/AreasTecnicasEstampadoAdmin.php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class AreasTecnicasEstampadoAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        // NOTA: No añadimos el campo 'tecnica' porque, al ser un formulario incrustado,
        // la relación con el padre (ModeloHasTecnicasEstampado) se gestiona automáticamente.

        $form
            ->add('areaname', TextType::class, ['label' => 'Nombre del Área'])
            ->add('areawidth', NumberType::class, ['label' => 'Ancho (mm)'])
            ->add('areahight', NumberType::class, ['label' => 'Alto (mm)'])
            ->add('maxcolores', IntegerType::class, ['label' => 'Max. Colores'])
            ->add('areaimg', TextType::class, [
                'label' => 'URL de la imagen del área',
                'required' => false,
            ]);
    }
}