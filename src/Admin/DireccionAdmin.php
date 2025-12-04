<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class DireccionAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        // NOTA: He eliminado el bloque de código inicial para '$link_parameters'
        // porque no se estaba utilizando en ningún campo del formulario.

        $form
            ->add('nombre', TextType::class, ['required' => false])
            ->add('telefonoMovil', TextType::class, ['required' => false])
            ->add('telefonoOtro', TextType::class, ['required' => false])
            ->add('dir', TextType::class, ['label' => 'Dirección'])
            ->add('cp', TextType::class, ['label' => 'Código Postal'])
            ->add('poblacion', TextType::class, ['label' => 'Población'])
            ->add('provinciaBD')
            ->add('provincia', TextType::class, [
                'label' => 'Provincia (texto)',
                'help' => 'Se rellena automáticamente al seleccionar la Provincia de la BD.'
            ])
            ->add('paisBD')
            ->add('pais', TextType::class, [
                'label' => 'País (texto)',
                'help' => 'Se rellena automáticamente al seleccionar el País de la BD.'
            ])
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('nombre')
            ->add('dir', null, ['label' => 'Dirección'])
            ->add('cp', null, ['label' => 'Código Postal'])
            ->add('poblacion')
            ->add('provinciaBD', ModelFilter::class, [
                'label' => 'Provincia (desde BD)',
//                'field_options' => [
//                    'property' => 'nombre'
//                ]
            ])
            ->add('paisBD', ModelFilter::class, [
                'label' => 'País (desde BD)',
//                'field_options' => [
//                    'property' => 'nombre'
//                ]
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('nombre')
            ->add('dir', null, ['label' => 'Dirección'])
            ->add('cp',null, ['label' => 'C.P.'])
            ->add('poblacion',null, ['label' => 'Población'])
            ->add('provinciaBD', null, [
                'label' => 'Provincia',
                'associated_property' => 'nombre',
            ]);
    }
}