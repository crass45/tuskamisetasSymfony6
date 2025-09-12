<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Sonata\Form\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class ZonaEnvioAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('nombre', TextType::class)
            ->add('envioGratis', MoneyType::class, [
                'label' => 'Importe para envío gratis (0 para desactivar)',
                'currency' => 'EUR',
            ])
            ->add('incrementoTiempoPedido', IntegerType::class, [
                'label' => 'Días de incremento en el envío'
            ])
            // Corregido: zonaEnvioHasProvincias -> provincias
            ->add('provincias', ModelType::class, [
                'label' => 'Provincias incluidas en esta zona',
                'multiple' => true,
                'expanded' => true, // Muestra como checkboxes
                'by_reference' => false,
                'property' => 'nombre'
            ])
            ->add('precios', CollectionType::class, [
                'by_reference' => false,
                'label' => 'Franjas de Precios por Bultos',
            ], [
                'edit' => 'inline',
                'inline' => 'table',
                'admin_code' => 'App\Admin\ZonaEnvioPrecioCantidadAdmin',
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('nombre', StringFilter::class);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('nombre')
            // Corregido: 'precioCaja' no existe en la entidad. Se reemplaza por 'envioGratis'.
            ->add('envioGratis', 'currency', [
                'label' => 'Envío Gratis a partir de',
                'currency' => 'EUR'
            ])
            ->add('incrementoTiempoPedido', null, ['label' => 'Incremento Días']);
    }
}