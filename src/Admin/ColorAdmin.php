<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class ColorAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('id', TextType::class, [
                'label' => 'ID (Código)',
            ])
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Color',
            ])
            ->add('codigoRGB', ColorType::class, [
                'label' => 'Color (RGB)',
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('nombre', null, ['label' => 'Nombre'])
            ->add('id', null, ['label' => 'ID (Código)'])
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', null, ['label' => 'ID (Código)'])
            ->add('nombre', null, [
                'editable' => true,
                'label' => 'Nombre',
            ])
            ->add('codigoRGB', null, [
                'label' => 'Color',
                'template' => 'admin/list_color_swatch.html.twig', // Mejora visual
            ]);
    }
}