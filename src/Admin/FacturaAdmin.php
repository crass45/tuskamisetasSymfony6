<?php

namespace App\Admin;

use App\Entity\Factura;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\AdminType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeFilter;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class FacturaAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('Datos', ['class' => 'col-md-12'])
            ->add('fecha', DateTimePickerType::class)
            ->add('nombre', TextType::class, ['label' => 'Número de Factura'])
            ->add('razonSocial', TextType::class, ['label' => 'Razón Social'])
            ->add('cif', TextType::class, ['label' => 'CIF / DNI'])
            ->add('direccion', TextType::class, ['label' => 'Dirección de facturación'])
            ->add('cp', TextType::class, ['label' => 'Código Postal'])
            ->add('poblacion', TextType::class, ['label' => 'Población'])
            ->add('provincia', TextType::class, ['label' => 'Provincia'])
            ->add('pais', TextType::class, ['label' => 'País'])
            ->add('comentarios', CKEditorType::class, ['label' => 'Observaciones para la factura'])
            // Corregido: idUsuario -> contacto
            ->add('pedido.contacto', TextType::class, [
                "disabled" => true,
                'label' => 'Usuario (Contacto)',
                'mapped' => false, // No se mapea directamente, es solo para mostrar
                'data' => $this->getSubject()?->getPedido()?->getContacto()?->__toString(),
            ])
            ->end()
            ->with('Pedido Asociado')
            // Incrustamos el PedidoAdmin para verlo/editarlo
            ->add('pedido', AdminType::class, ['label' => false])
            ->end();
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('nombre')
            ->add('fecha', DateTimeFilter::class)
            ->add('cif')
            // Corregido: idUsuario -> contacto y la ruta profunda
            ->add('pedido.contacto.usuario.email', StringFilter::class, ['label' => 'Email']);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('fecha')
            ->addIdentifier('nombre')
            ->add('pedido', null, [
                'associated_property' => 'nombre',
            ])
            // Corregido: idUsuario -> contacto
            ->add('pedido.contacto', null, [
                'label' => 'Cliente',
//                'associated_property' => '__toString',
            ])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                    'showFacturaFactura' => [
                        // NOTA: La ruta a la plantilla ha cambiado
                        'template' => 'admin/CRUD/list_action_show_factura.html.twig'
                    ]
                ]
            ]);
    }

    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        // Se conservan las rutas personalizadas
        $collection->add('showFacturaFactura', $this->getRouterIdParameter() . '/show-factura');
        $collection->add('creaRectificativa', $this->getRouterIdParameter() . '/crea-rectificativa');

        // Se elimina la acción de crear, ya que las facturas se generan desde los pedidos
        $collection->remove('create');
    }
}