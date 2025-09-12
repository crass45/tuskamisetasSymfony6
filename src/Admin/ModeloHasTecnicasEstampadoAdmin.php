<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Sonata\Form\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

final class ModeloHasTecnicasEstampadoAdmin extends AbstractAdmin
{
    protected function configure(): void
    {
        $this->parentAssociationMapping = 'modelo';
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('personalizacion', ModelType::class, [
                'label' => 'Técnica de Personalización',
                'property' => 'nombre',
                'btn_add' => false,
            ])
            ->add('maxcolores', IntegerType::class, [
                'label' => 'Nº Máximo de Colores'
            ])
            ->add('areas', CollectionType::class, [
                'label' => 'Áreas de Impresión',
                'by_reference' => false, // Importante para que los cambios se guarden
            ], [
                'edit' => 'inline',
                'inline' => 'table',
                // Para que esto funcione, necesitamos un Admin para AreasTecnicasEstampado
                'admin_code' => 'App\Admin\AreasTecnicasEstampadoAdmin',
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('personalizacion', ModelFilter::class, [
                'field_options' => [
                    'property' => 'nombre'
                ]
            ]);
    }


    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('personalizacion', null, [
                'associated_property' => 'nombre',
                'label' => 'Personalización'
            ])
            ->add('maxcolores', null, ['label' => 'Max. Colores']);
    }
}