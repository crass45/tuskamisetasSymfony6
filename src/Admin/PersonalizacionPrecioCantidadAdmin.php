<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\NumberFilter;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;

final class PersonalizacionPrecioCantidadAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('cantidad', IntegerType::class)
            ->add('precio', MoneyType::class, ['currency' => 'EUR'])
            ->add('precio2', MoneyType::class, ['currency' => 'EUR', 'label' => 'Precio Extra por Color (Prenda Blanca)'])
            ->add('precioColor', MoneyType::class, ['currency' => 'EUR', 'label' => 'Precio (Prenda de Color)'])
            ->add('precioColor2', MoneyType::class, ['currency' => 'EUR', 'label' => 'Precio Extra por Color (Prenda de Color)'])
            ->add('pantalla', MoneyType::class, ['currency' => 'EUR', 'label' => 'Coste de Pantalla'])
            ->add('repeticion', MoneyType::class, ['currency' => 'EUR', 'label' => 'Coste de Repetición']);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('personalizacion.nombre', StringFilter::class)
            ->add('personalizacion.codigo', StringFilter::class)
            ->add('cantidad', NumberFilter::class);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('personalizacion', null, [
                'associated_property' => 'nombre'
            ])
            ->add('cantidad', null, ['editable' => true])
            ->add('precio', 'currency', [
                'editable' => true,
                'currency' => 'EUR'
            ]);
    }
}