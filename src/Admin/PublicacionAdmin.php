<?php

namespace App\Admin;


use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Sonata\Form\Type\DateTimeRangePickerType;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class PublicacionAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('activo', CheckboxType::class, ['required' => false])
            ->add('titulo', TextType::class)
            ->add('nombreUrl', TextType::class, [
                'label' => 'URL (se genera automáticamente)',
                'disabled' => true, // El campo no se puede editar
                'required' => false,
            ])
            ->add('metadescripcion', TextType::class)
            ->add('textoPortada', TextareaType::class, ['attr' => ['class' => 'tinymce']])
            ->add('fecha', DateTimePickerType::class, [
                'format' => 'dd-MM-yyyy',
                'required' => false,
            ])
            ->add('url_imagen_portada', TextType::class, [ // Asumo que es una propiedad de la entidad
                'label' => 'URL Imagen de Portada'
            ])
            ->add('contenido', TextareaType::class,['attr' => ['class' => 'tinymce']]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('titulo', StringFilter::class)
            ->add('fecha', DateTimeRangeFilter::class, [
                'field_type' => DateTimeRangePickerType::class,
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('fecha', null, [
                'format' => 'd/m/Y'
            ])
            ->addIdentifier('titulo')
            ->add('activo', null, ['editable' => true]);
    }
}