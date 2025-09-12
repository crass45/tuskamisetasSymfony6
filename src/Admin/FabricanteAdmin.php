<?php
// src/Admin/FabricanteAdmin.php

namespace App\Admin;

use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class FabricanteAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        // NOTA: Ya no necesitamos el bucle manual ni TranslationsType.
        // SonataTranslationBundle creará las pestañas de idiomas automáticamente.

        $form
            ->with('Datos Generales', ['class' => 'col-md-6'])
            ->add('nombre', TextType::class, [
                'help' => 'El nombre principal del fabricante. Este campo NO es traducible.'
            ])
            ->add('activo', CheckboxType::class, ['required' => false])
            ->add('showMenu', CheckboxType::class, [
                'label' => "Mostrar en Menú",
                'required' => false,
            ])
            ->add('imagen', ModelListType::class, [
                'required' => false,
            ])
            ->end()
            ->with('Contenido Traducible', ['class' => 'col-md-6'])
            // Simplemente añadimos los campos que marcamos como @Gedmo\Translatable
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (Traducible)',
                'required' => false
            ])
            ->add('tituloSEO', TextType::class, [
                'label' => 'Título SEO (Traducible)',
                'required' => false
            ])
            ->add('textoArriba', CKEditorType::class, [
                'label' => 'Texto Parte Superior (Traducible)',
                'required' => false,
            ])
            ->add('textoAbajo', CKEditorType::class, [
                'label' => 'Texto Parte Inferior (Traducible)',
                'required' => false,
            ])
            ->end()
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('nombre');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('nombre')
            ->add('showMenu', null, [
                'editable' => true,
                'label' => 'Visible en Menú',
            ])
            ->add('activo', null, [
                'editable' => true,
                'label' => 'Activo'
            ]);
    }
}