<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\BooleanFilter;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Sonata\DoctrineORMAdminBundle\Filter\NumberFilter;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class FamiliaAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('Datos Principales', ['class' => 'col-md-12'])
            ->add('id', TextType::class, [
                'label' => 'ID (Código)',
                'help' => 'La clave primaria de la familia, no es autoincremental.'
            ])
            ->add('ordenMenu', IntegerType::class, [
                'label' => 'Orden en el Menú'
            ])
            ->end()
            ->with('Contenido Traducible')
            ->add('nombre', TextType::class, [
                'label' => 'Nombre'
            ])
            ->add('tituloSEO', TextType::class, [
                'label' => 'Título SEO',
                'required' => false,
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción SEO',
                'required' => false,
            ])
            // --> CAMBIO 2: Reemplazamos CKEditorType
            ->add('textoArriba', TextareaType::class, [
                'label' => 'Texto Parte Superior',
                'required' => false,
                'attr' => ['class' => 'tinymce']
            ])
            // --> CAMBIO 3: Reemplazamos CKEditorType
            ->add('textoAbajo', TextareaType::class, [
                'label' => 'Texto Parte Inferior',
                'required' => false,
                'attr' => ['class' => 'tinymce']
            ])
            ->end();
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('translations.nombre', null, ['label' => 'Nombre'])
            ->add('promocional', BooleanFilter::class)
            ->add('proveedor', ModelFilter::class, [
                'field_options' => [
                    'choice_label' => 'nombre'
                ]
            ])
            ->add('ordenMenu', NumberFilter::class);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id')
            ->add('nombre', null, ['label' => 'Nombre'])
            ->add('ordenMenu', null, [
                'editable' => true,
                'label' => 'Orden'
            ]);
    }
}