<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class GastosEnvioAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('codigoPostal', TextType::class, [
                'label' => 'Código Postal',
            ])
            ->add('precio', MoneyType::class, [
                'label' => 'Precio Envío',
                'currency' => 'EUR',
            ])
            ->add('precioReducido', MoneyType::class, [
                'label' => 'Precio Envío Reducido',
                'currency' => 'EUR',
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('codigoPostal', StringFilter::class);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('codigoPostal', null, ['label' => 'Código Postal'])
            ->add('precio', null, [
                'label' => 'Precio',
                'template' => '@SonataAdmin/CRUD/list_currency.html.twig',
                'currency' => 'EUR',
            ])
            ->add('precioReducido', null, [
                'label' => 'Precio Reducido',
                'template' => '@SonataAdmin/CRUD/list_currency.html.twig',
                'currency' => 'EUR',
            ]);
    }
}