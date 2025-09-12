<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\Form\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;

final class PedidoLineaAdmin extends AbstractAdmin
{
    protected function configure(): void
    {
        // Corregido: idPedido -> pedido
        $this->parentAssociationMapping = 'pedido';
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            // Corregido: idProducto -> producto
            ->add('producto', ModelListType::class, [
                'btn_add' => false,
                'btn_delete' => false,
                'label' => 'Producto'
            ])
            ->add('cantidad', IntegerType::class)
            ->add('precio', MoneyType::class, [
                'currency' => 'EUR',
            ])
            ->add('personalizaciones', CollectionType::class, [
                'by_reference' => false,
                'label' => 'Personalizaciones'
            ], [
                'edit' => 'inline',
                'inline' => 'table',
                // Para que esto funcione, necesitamos un Admin para PedidoLineaHasTrabajo
                'admin_code' => 'App\Admin\PedidoLineaHasTrabajoAdmin',
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            // Corregido: idProducto -> producto
            ->addIdentifier('producto.referencia', null, ['label' => 'Referencia de Producto'])
            ->add('cantidad');
    }
}