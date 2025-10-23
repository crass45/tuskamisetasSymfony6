<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Form\FormMapper;

final class FacturaRectificativaLineaAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('descripcion', null, ['label' => 'Descripción'])
            ->add('cantidad', null, ['label' => 'Cantidad'])
            ->add('precio', null, ['label' => 'Precio Unitario'])
        ;
    }
}
