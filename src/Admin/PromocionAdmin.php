<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class PromocionAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('fechaInicio', DateTimePickerType::class, [
                'label' => 'Fecha de Inicio',
                'format' => 'dd-MM-yyyy HH:mm'
            ])
            ->add('fechaFin', DateTimePickerType::class, [
                'label' => 'Fecha de Fin',
                'format' => 'dd-MM-yyyy HH:mm'
            ])
            ->add('codigo', TextType::class)
            ->add('cantidad', IntegerType::class, [
                'label' => 'Usos Máximos (cantidad)',
                'required' => false
            ])
            ->add('porcentaje', PercentType::class, [
                'label' => 'Porcentaje de Descuento',
                'required' => false,
                'type' => 'integer'
            ])
            ->add('gastosEnvio', CheckboxType::class, [
                'label' => 'Aplica a Gastos de Envío',
                'required' => false
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('codigo', StringFilter::class);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('codigo')
            ->add('fechaInicio', null, [
                'label' => 'Inicio',
                'format' => 'd/m/Y H:i'
            ])
            ->add('fechaFin', null, [
                'label' => 'Fin',
                'format' => 'd/m/Y H:i'
            ])
            ->add('cantidad', null, ['label' => 'Usos'])
            ->add('porcentaje', 'percent', ['label' => 'Dto. (%)'])
            ->add('gastosEnvio', null, ['label' => 'Envío Gratis']);
    }
}