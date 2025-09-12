<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;

final class ParametroAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('gastosEnvio', MoneyType::class, [
                'label' => 'Gastos de Envío por defecto',
                'currency' => 'EUR',
                'required' => false,
            ])
            ->add('iva', PercentType::class, [
                'label' => 'IVA General (%)',
                'scale' => 2, // Nº de decimales
                'type' => 'integer', // Guarda el valor como un entero (ej. 21 para 21%)
                'required' => false,
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id')
            ->add('gastosEnvio', 'currency', ['currency' => 'EUR'])
            ->add('iva', 'percent');
    }
}