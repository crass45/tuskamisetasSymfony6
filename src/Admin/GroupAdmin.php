<?php
// src/Admin/GroupAdmin.php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\UserBundle\Form\Type\RolesMatrixType;
use Sonata\Form\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class GroupAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('General', ['class' => 'col-md-12'])
            ->add('name', TextType::class, [
                'label' => 'Nombre del Grupo'
            ])
            ->add('roles', RolesMatrixType::class, [
                'label' => 'Roles',
                'multiple' => true,
                'expanded' => true, // Muestra como checkboxes
                'required' => false,
            ])
            ->end()
            ->with('Reglas de Descuento', ['class' => 'col-md-12'])
            ->add('descuentos', CollectionType::class, [
                'by_reference' => false,
                'label' => 'Descuentos aplicados a este grupo'
            ], [
                'edit' => 'inline',
                'inline' => 'table',
                'admin_code' => 'App\Admin\DescuentoAdmin'
            ])
            ->end();
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('name', null, ['label' => 'Nombre', 'route'=>['name'=>'edit']])
            ->add('roles', 'array', ['label' => 'Roles Asignados']);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('name', null, ['label' => 'Nombre']);
    }
}