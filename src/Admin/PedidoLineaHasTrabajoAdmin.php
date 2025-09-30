<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class PedidoLineaHasTrabajoAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('pedidoTrabajo', ModelListType::class, [
                'label' => 'Trabajo Realizado',
                'btn_delete' => false,
                'btn_add' => false,
                'btn_edit' => false,
                'btn_list' => false,
            ])
//            ->add('cantidad', IntegerType::class)
            ->add('ubicacion', TextType::class, ['required' => false])
            ->add('observaciones', TextareaType::class, ['required' => false]);
//            ->add('repeticion', CheckboxType::class, ['required' => false]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('pedidoTrabajo')
            ->add('cantidad')
            ->add('ubicacion')
            ->add('repeticion');
    }
}