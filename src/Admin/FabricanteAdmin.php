<?php
// src/Admin/FabricanteAdmin.php

namespace App\Admin;

// --> CAMBIO 1: Importamos el nuevo tipo de TinyMCE
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
        $form
            ->with('Datos Generales', ['class' => 'col-md-12'])
            ->add('nombre', TextType::class, [
                'help' => 'El nombre principal del fabricante. Este campo NO es traducible.'
            ])
            ->add('activo', CheckboxType::class, ['required' => false])
            ->add('vistaAlternativa', CheckboxType::class, [
                'label' => 'Usar Vista Alternativa de Galería',
                'help' => 'Si se activa, la imagen principal ocupará el 100% del ancho y las secundarias se mostrarán debajo en filas de 3.',
                'required' => false,
            ])
            ->add('showMenu', CheckboxType::class, [
                'label' => "Mostrar en Menú",
                'required' => false,
            ])
            ->add('imagen', ModelListType::class, [
                'required' => false,
            ])
            ->end()
            ->with('Contenido Traducible', ['class' => 'col-md-12'])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (Traducible)',
                'required' => false
            ])
            ->add('tituloSEO', TextType::class, [
                'label' => 'Título SEO (Traducible)',
                'required' => false
            ])
            // --> CAMBIO 2: Reemplazamos CKEditorType por TinyMCEType
            ->add('textoArriba', TextareaType::class, [
                'label' => 'Texto Parte Superior (Traducible)',
                'required' => false,
                'attr' => ['class' => 'tinymce']
            ])
            // --> CAMBIO 3: Reemplazamos CKEditorType por TinyMCEType
            ->add('textoAbajo', TextareaType::class, [
                'label' => 'Texto Parte Inferior (Traducible)',
                'required' => false,
                'attr' => ['class' => 'tinymce']
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