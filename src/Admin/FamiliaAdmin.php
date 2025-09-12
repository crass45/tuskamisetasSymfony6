<?php

namespace App\Admin;

use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
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
            ->with('Datos Principales', ['class' => 'col-md-8'])
            ->add('id', TextType::class, [
                'label' => 'ID (Código)',
                'help' => 'La clave primaria de la familia, no es autoincremental.'
            ])
//            ->add('category', ModelType::class, [
//                'label' => 'Categoría de Sonata',
//                'choice_label' => 'name',
//                'required' => false,
//            ])
            ->add('ordenMenu', IntegerType::class, [
                'label' => 'Orden en el Menú'
            ])
            ->end()
            ->with('Contenido Traducible') // Cambiamos el título del bloque para más claridad
            // Ya no usamos TranslationsType. Añadimos los campos directamente.
            // SonataTranslationBundle creará las pestañas de idioma automáticamente para estos campos.
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
            ->add('textoArriba', CKEditorType::class, [
                'label' => 'Texto Parte Superior',
                'required' => false,
            ])
            ->add('textoAbajo', CKEditorType::class, [
                'label' => 'Texto Parte Inferior',
                'required' => false,
            ])
            ->end();
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            // Así se filtra por un campo traducido
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
            // El campo 'nombre' se mostrará automáticamente en el idioma actual
            ->add('nombre', null, ['label' => 'Nombre'])
//            ->add('category', null, [
//                'label' => 'Categoría',
//                'associated_property' => 'name',
//            ])
            ->add('ordenMenu', null, [
                'editable' => true,
                'label' => 'Orden'
            ]);
    }
}