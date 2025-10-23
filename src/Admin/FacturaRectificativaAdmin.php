<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\Form\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

final class FacturaRectificativaAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('General', ['class' => 'col-md-12'])
            ->add('facturaPadre', ModelType::class, [
                'label' => 'Factura Original',
                'disabled' => true,
                'btn_add' => false,
            ])
            ->add('numeroFactura', null, [
                'label' => 'Número de Factura Rectificativa',
            ])
            ->add('motivo', TextareaType::class, [
                'label' => 'Motivo de la Rectificación',
                'required' => true,
            ])
            ->end()
            ->with('Líneas de la Factura', ['class' => 'col-md-12'])
            ->add('lineas', CollectionType::class, [
                'label' => false,
                'by_reference' => false,
            ], [
                'edit' => 'inline',
                'inline' => 'table',
                // Le decimos a Sonata que use el 'mini-admin' para renderizar cada fila
                'admin_code' => 'admin.factura_rectificativa_linea',
            ])
            ->end()
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('numeroFactura', null, ['label' => 'Número'])
            ->add('facturaPadre.pedido.contacto', null, ['label' => 'Contacto'])
            ->add('facturaPadre', null, ['label' => 'Factura Original'])
            ->add('motivo', null, ['label' => 'Motivo'])
            ->add('fecha', null, [
                'label' => 'Fecha de Creación',
                'format' => 'd/m/Y H:i',
            ])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show_pdf' => [
                        'template' => 'admin/CRUD/list_action_show_rectificativa_pdf.html.twig'
                    ],
                    // --- ACCIÓN AÑADIDA ---
                    'generateVerifactu' => [
                        'template' => 'admin/CRUD/list__action_generate_verifactu.html.twig'
                    ],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }


    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('create');
        $collection->add('showFacturaRectificativa', $this->getRouterIdParameter() . '/show-pdf');
        // --- RUTA AÑADIDA ---
        // Esta ruta apunta a la acción que crearemos en FacturaRectificativaCRUDController
        $collection->add('generateVerifactu', $this->getRouterIdParameter() . '/generate-verifactu');
    }
}
