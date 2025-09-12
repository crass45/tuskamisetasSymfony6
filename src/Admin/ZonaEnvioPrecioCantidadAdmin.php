<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;

final class ZonaEnvioPrecioCantidadAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('bultos', IntegerType::class, [
                'label' => 'A partir de (nº bultos)'
            ])
            ->add('precio', MoneyType::class, [
                'currency' => 'EUR',
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('zonaEnvio.nombre', StringFilter::class, [
                'label' => 'Nombre de la Zona de Envío'
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('zonaEnvio', null, [
                'associated_property' => 'nombre'
            ])
            ->add('bultos', null, ['editable' => true])
            ->add('precio', 'currency', [
                'currency' => 'EUR',
                'editable' => true
            ]);
    }
}