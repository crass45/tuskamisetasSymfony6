<?php

namespace App\Admin;

use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class ProveedorAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->tab('Información')
            ->with('Datos Principales')
            ->add('nombre', TextType::class)
            ->add('tarifa')
            ->add('controlDeStock', CheckboxType::class, ['required' => false])
            ->add('permiteVentaSinStock', CheckboxType::class, ['required' => false])
            ->add('compraMinima', IntegerType::class, [
                'label' => 'Compra Mínima',
                'help' => 'Cantidad mínima de camisetas que hay que comprar para que nos deje pedir',
                'required' => false,
            ])
            ->add('diasEnvio', IntegerType::class, [
                'label' => 'Días retraso de envío',
                'help' => 'Días que incrementa este proveedor el envío',
                'required' => false,
            ])
            ->add('ventaEnPack', CheckboxType::class, [
                'help' => 'Indica si el proveedor vende en pack forzosamente',
                'required' => false,
            ])
            ->add('descuentoEspecial', PercentType::class, [
                'type' => 'integer', // Asume que es un porcentaje
            ])
            ->add('acumulaTotal', CheckboxType::class, ['required' => false])
            ->end()
            ->with('Contacto')
            ->add('email', TextType::class, ['required' => false])
            ->add('telefonoMovil', TextType::class, ['required' => false])
            ->add('telefonoOtro', TextType::class, ['required' => false])
            ->add('longitud', NumberType::class, ['required' => false])
            ->add('latitud', NumberType::class, ['required' => false])
            ->end()
            ->with('Observaciones')
            ->add('observaciones', CKEditorType::class, ['required' => false])
            ->end()
            ->end()
            ->tab('Datos Financieros y Sociales')
            ->with('Pago por Transferencia')
            ->add('cuentaBancaria', TextType::class, ['required' => false])
            ->end()
            ->with('Redes Sociales')
            ->add('web', TextType::class, ['required' => false])
            ->add('facebook', TextType::class, ['required' => false])
            ->add('twitter', TextType::class, ['required' => false])
            ->add('youtube', TextType::class, ['required' => false])
            ->add('pinterest', TextType::class, ['required' => false])
            ->end()
            ->end();
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('nombre', StringFilter::class)
            ->add('tarifa', ModelFilter::class, [
                'field_options' => [
                    'choice_value' => 'nombre'
                ]
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('nombre')
            ->add('tarifa', null, [
                'editable' => true,
                'associated_property' => 'nombre',
            ])
            ->add('controlDeStock', null, ['editable' => true])
            ->add('permiteVentaSinStock', null, ['editable' => true])
            ->add('acumulaTotal', null, ['editable' => true]);
    }
}