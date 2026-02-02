<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Sonata\Form\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
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
            ->add('proveedor')
            // Corregido: incremento_precio -> incrementoPrecio
            ->add('incrementoPrecio', PercentType::class, [
                'label' => 'Incremento de Precio',
                'type' => 'integer',
                'symbol' => '%',
            ])
            ->add('tiempoPersonalizacion', null, [
                'label' => 'Días de Producción Extra',
                'help' => 'Días adicionales que se sumarán a la fecha de entrega (0 = Sin retraso)'
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
            ->add('proveedor')
            ->add('codigo', StringFilter::class);
    }

    // 1. Definir la nueva ruta
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->add('ver_productos', $this->getRouterIdParameter().'/ver-productos');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('codigo')
            ->add('nombre')
            ->add('proveedor')
            ->add('tiempoPersonalizacion', null, ['label' => 'Días Extra', 'editable' => true])
            ->add('incrementoPrecio', null, ['label' => '% de incremento', 'editable' => true])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'edit' => [],
                    //'delete' => [],
                    // 2. Añadir botón personalizado
                    'ver_productos' => [
                        'template' => 'admin/CRUD/list__action_ver_productos.html.twig',
                    ],
                ],
            ]);
    }
}