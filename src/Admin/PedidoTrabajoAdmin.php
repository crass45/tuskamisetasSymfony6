<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

final class PedidoTrabajoAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with("General", ["class" => "col-md-12"])
            ->add('id', IntegerType::class, [
                'label' => 'ID (Fotolito)',
                'disabled' => true,
                'required' => false
            ])
            ->add('nombre', TextType::class, ['required' => false])
            ->add('urlImagen', UrlType::class, ['required' => false])
            ->add('personalizacion', ModelType::class, [
                'property' => 'nombre'
            ])
            ->add('nColores', IntegerType::class)
            ->add('imagenOriginal', ModelListType::class, ['required' => false])
            ->add('arteFin', ModelListType::class, ['required' => false])
            ->add('montaje', ModelListType::class, ['required' => false])
            ->end();
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('codigo', StringFilter::class)
            // Corregido: idUsuario -> contacto
            ->add('contacto', ModelFilter::class, [
                'label' => 'Usuario (Contacto)',
                'field_options' => [
                    'property' => 'nombre'
                ]
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('codigo')
            // Corregido: idUsuario -> contacto
            ->add('contacto', null, [
                'label' => 'Cliente',
                'associated_property' => '__toString'
            ])
            ->add('imagenOriginal', 'string', [
                'label' => 'Imagen',
                'template' => '@SonataMedia/MediaAdmin/list_image.html.twig'
            ]);
    }
}