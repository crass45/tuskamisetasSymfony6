<?php
// src/Admin/DescuentoAdmin.php

namespace App\Admin;

use App\Entity\Tarifa;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType; // <-- CAMBIAMOS EL 'USE'
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

final class DescuentoAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('grupo', ModelListType::class, [
                'label' => 'Grupo de Usuarios',
                'btn_add' => false,
                'btn_list' => false,
                'btn_delete' => false,
            ])
            ->add('tarifaAnterior', EntityType::class, [ // <-- CAMBIO DE TIPO
                'class' => Tarifa::class, // <-- Opción obligatoria para EntityType
                'choice_label' => 'nombre',
                'label' => 'Tarifa de Origen',
                'required' => false,
            ])
            ->add('tarifa', EntityType::class, [ // <-- CAMBIO DE TIPO
                'class' => Tarifa::class, // <-- Opción obligatoria para EntityType
                'choice_label' => 'nombre',
                'label' => 'Tarifa a Aplicar',
                'required' => false,
            ])
            ->add('descuento', IntegerType::class, [
                'label' => 'Porcentaje de Descuento (%)',
                'required' => false,
                'help' => 'Dejar en blanco si se aplica un cambio de Tarifa.',
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('grupo', ModelFilter::class, [
                'label' => 'Grupo',
                'field_options' => [
                    'choice_label' => 'name'
                ]
            ])
            ->add('tarifa', ModelFilter::class, [
                'label' => 'Tarifa Aplicada',
                'field_options' => [
                    'choice_label' => 'nombre'
                ]
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id')
            ->add('grupo', null, [
                'label' => 'Grupo',
                'associated_property' => 'name',
            ])
            ->add('tarifaAnterior', null, [
                'label' => 'Tarifa Origen',
                'associated_property' => 'nombre',
            ])
            ->add('tarifa', null, [
                'label' => 'Tarifa Aplicada',
                'associated_property' => 'nombre',
            ])
            ->add('descuento', null, [
                'label' => 'Dto. (%)',
            ]);
    }
}