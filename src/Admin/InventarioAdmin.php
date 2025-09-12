<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Sonata\DoctrineORMAdminBundle\Filter\NumberFilter;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class InventarioAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('Inventario')
            ->add('producto', TextType::class)
            ->add('caja')
            ->add('cantidad', IntegerType::class)
            ->add('observaciones', TextareaType::class, [
                'required' => false
            ])
            ->end();
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            // Corregido: idProducto -> producto
            ->add('producto.modelo.referencia', StringFilter::class, ['label' => 'Referencia'])
            ->add('producto.color', ModelFilter::class, [
                'label' => 'Color',
                'field_options' => ['choice_label' => 'nombre']
            ])
            ->add('producto.modelo.fabricante', ModelFilter::class, [
                'label' => 'Fabricante',
                'field_options' => ['choice_label' => 'nombre']
            ])
            ->add('caja', NumberFilter::class, ['label' => 'Nº Caja']);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            // Corregido: idProducto -> producto
            ->addIdentifier('producto.modelo.referencia', null, ['label' => 'Referencia'])
            ->add('producto', null, [
                'label' => 'Producto'
            ])
            ->add('producto.modelo.fabricante', null, [
                'label' => 'Fabricante',
                'associated_property' => 'nombre'
            ])
            ->add('producto.talla', null, ['label' => 'Talla'])
            ->add('producto.color', null, [
                'label' => 'Color',
                'associated_property' => 'nombre'
            ])
            ->add('caja', null, ['label' => 'Nº Caja'])
            ->add('cantidad', null, ['editable' => true]);
    }
}