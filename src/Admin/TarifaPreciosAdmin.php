<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\NumberFilter;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;

final class TarifaPreciosAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('cantidad', IntegerType::class, [
                'label' => 'A partir de (cantidad)',
            ])
            ->add('precio', MoneyType::class, [
                'label' => 'Precio por unidad',
                'currency' => 'EUR',
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('cantidad', NumberFilter::class)
            ->add('precio', NumberFilter::class);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('cantidad')
            ->add('precio', 'currency', [
                'currency' => 'EUR',
                'editable' => true,
            ]);
    }
}