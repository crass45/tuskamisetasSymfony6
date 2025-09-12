<?php
// src/Admin/ContactoAdmin.php

namespace App\Admin;

use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\AdminType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class ContactoAdmin extends AbstractAdmin
{
    // NO HAY ATRIBUTO #[Admin] AQUÍ
    // Pega este método completo en tu clase App\Admin\ContactoAdmin

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->tab('Resumen')
            ->with('Información Personal', ['class' => 'col-md-6'])
            ->add('nombre')
            ->add('apellidos')
            ->add('cif', null, ['label' => 'CIF/DNI'])
            ->end()
            ->with('Información de Contacto', ['class' => 'col-md-6'])
            ->add('usuario.email', null, ['label' => 'Email de Acceso'])
            ->add('email', null, ['label' => 'Emails Adicionales'])
            ->add('telefonoMovil')
            ->add('telefonoOtro')
            ->end()
            ->with('Configuración de Cliente')
            ->add('tarifa', null, ['associated_property' => 'nombre'])
            ->add('recargoEquivalencia')
            ->add('intracomunitario')
            ->end()
            ->end()
            ->tab('Direcciones')
            ->with('Dirección de Facturación')
            // Muestra la dirección usando su propia plantilla 'show'
            ->add('direccionFacturacion')
            ->end()
            ->with('Direcciones de Envío')
            ->add('direccionesEnvio', null, [
                'associated_property' => '__toString'
            ])
            ->end()
            ->end()
        ;
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('Usuario', ['class' => 'col-md-6'])
            ->add('usuario', ModelType::class, [
                'choice_label' => 'username',
                'label' => 'Usuario de Sonata',
            ])
            ->add('nombre', TextType::class)
            ->add('apellidos', TextType::class, ['required' => false])
            ->add('cif', TextType::class, ['required' => false])
            ->end()
            ->with('Contacto', ['class' => 'col-md-6'])
            ->add('telefonoMovil', TextType::class, ['required' => false])
            ->add('telefonoOtro', TextType::class, ['required' => false])
            ->add('email', TextType::class, [
                'label' => 'Emails adicionales (separados por comas)',
                'required' => false
            ])
            ->end()
            ->with('Configuración', ['class' => 'col-md-12'])
            ->add('tarifa', ModelType::class, [
                'btn_add' => false
            ])
            ->add('recargoEquivalencia', CheckboxType::class, ['required' => false])
            ->add('intracomunitario', CheckboxType::class, ['required' => false])
            ->end()
            ->with('Dirección de Facturación')
            ->add('direccionFacturacion', AdminType::class, [
                'delete' => false,
            ])
            ->end()
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('usuario', ModelFilter::class, [
                'field_options' => [
                    'choice_label' => 'username'
                ]
            ])
            ->add('nombre')
            ->add('telefonoMovil');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('usuario.email', null, [
                'label' => 'Usuario / Email',
                'associated_property' => 'email'
            ])
            ->addIdentifier('nombre')
            ->add('apellidos')
            ->add('telefonoMovil');
    }

    protected function configureTabMenu(MenuItemInterface $menu, string $action, ?AdminInterface $childAdmin = null): void
    {
        if ('edit' !== $action) {
            return;
        }

        $admin = $this->isChild() ? $this->getParent() : $this;
        $id = $admin->getRequest()->get('id');

        if (!$id) {
            return;
        }

        $pedidosAdmin = $this->getConfigurationPool()->getAdminByAdminCode('sonata.admin.pedidos');

        $menu->addChild('Pedidos de este Cliente', [
            'uri' => $pedidosAdmin->generateUrl('list', ['filter' => ['contacto' => ['value' => $id]]])
        ]);
    }
}