<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

final class PedidoLineaLibreAdmin extends AbstractAdmin
{
    protected function configure(): void
    {
        // Corregido: idPedido -> pedido
        $this->parentAssociationMapping = 'pedido';
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('descripcion', TextareaType::class)
            ->add('cantidad', IntegerType::class)
            ->add('precio', MoneyType::class, [
                'currency' => 'EUR',
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('descripcion');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('descripcion')
            ->add('cantidad')
            ->add('precio', 'currency', [
                'currency' => 'EUR',
            ]);
    }
}