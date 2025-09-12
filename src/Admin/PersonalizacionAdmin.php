<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Sonata\Form\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class PersonalizacionAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('codigo', TextType::class, [
                'label' => 'Código (Clave Primaria)',
                'help' => 'Este es el ID de la técnica, debe ser único.'
            ])
            ->add('nombre', TextType::class)
            ->add('numeroMaximoColores', IntegerType::class)
            ->add('trabajoMinimoPorColor', MoneyType::class, [
                'currency' => 'EUR',
            ])
            // Corregido: incremento_precio -> incrementoPrecio
            ->add('incrementoPrecio', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Incremento de Precio',
            ])
            ->add('precios', CollectionType::class, [
                'by_reference' => false, // Importante para que los cambios se guarden
            ], [
                'edit' => 'inline',
                'inline' => 'table',
                // Para que esto funcione, necesitamos un Admin para PersonalizacionPrecioCantidad
                'admin_code' => 'App\Admin\PersonalizacionPrecioCantidadAdmin',
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('nombre', StringFilter::class)
            ->add('codigo', StringFilter::class);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('codigo')
            ->add('nombre');
    }
}