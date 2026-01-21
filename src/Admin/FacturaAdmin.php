<?php

namespace App\Admin;

use App\Entity\Factura;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\AdminType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeFilter;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class FacturaAdmin extends AbstractAdmin
{
    protected function configureQuery(ProxyQueryInterface $query): ProxyQueryInterface
    {
        $query->addOrderBy($query->getRootAliases()[0] . '.fecha', 'DESC');
        return $query;
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $factura = $this->getSubject();
        $pedido = $factura ? $factura->getPedido() : null;
        $htmlEnlacePedido = '';

        if ($pedido) {
            // Generamos la ruta.
            // NOTA: 'admin_app_pedido_edit' es la ruta estándar de edición de Sonata.
            // Si quieres que vaya a tu acción personalizada, usa 'admin_app_pedido_editarPedido'
            $rutaPedido = $this->getRouteGenerator()->generate('admin_app_pedido_edit', ['id' => $pedido->getId()]);

            $htmlEnlacePedido = sprintf(
                '<div style="margin-top: 5px; padding: 10px; background-color: #f0f0f0; border-left: 4px solid #3c8dbc;">
                    <i class="fa fa-shopping-cart"></i> Pedido asociado: <strong>%s</strong><br>
                    <a href="%s" target="_blank" style="font-weight: bold; text-decoration: underline;">
                       <i class="fa fa-external-link"></i> Pincha aquí para ver/editar el pedido completo
                    </a>
                 </div>',
                $pedido->__toString(), // Muestra el nombre/ref del pedido
                $rutaPedido
            );
        }

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
            // --> CAMBIO 2: Reemplazamos CKEditorType por TinyMCEType
            ->add('comentarios', TextareaType::class, [
                'label' => 'Observaciones para la factura',
                'required' => false, // Es buena práctica añadirlo si puede estar vacío
                'attr' => ['class' => 'tinymce']
            ])
            ->add('pedido.contacto', TextType::class, [
                "disabled" => true,
                'label' => 'Usuario (Contacto)',
                'mapped' => false,
                'data' => $this->getSubject()?->getPedido()?->getContacto()?->__toString(),
            ])
//            ->end()
//            ->with('Pedido Asociado')
//            ->add('pedido', AdminType::class, ['label' => false])
            // --- NUEVO CAMPO DE ENLACE ---
            ->add('enlace_pedido_info', TextType::class, [
                'mapped' => false,    // No se guarda en base de datos
                'required' => false,
                'label' => 'Acceso al Pedido',
                // Ocultamos el input de texto, solo queremos ver el HTML de ayuda
                'attr' => ['style' => 'display:none;'],
                // Aquí inyectamos el HTML que creamos arriba
                'help' => $htmlEnlacePedido,
                'help_html' => true,
            ])
            // -----------------------------
            ->end();
    }

    protected array $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_sort_by' => 'fecha',
    ];

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('nombre')
            ->add('fecha', DateTimeFilter::class)
            ->add('cif')
            ->add('pedido.contacto.usuario.email', StringFilter::class, ['label' => 'Email']);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('fecha', null, [
                'label' => 'Fecha de Creación',
                'format' => 'd/m/Y H:i',
            ])
            ->addIdentifier('nombre')
            ->add('pedido', null, [
                'associated_property' => 'nombre',
            ])
            ->add('pedido.contacto', null, [
                'label' => 'Cliente',
            ])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'showFacturaFactura' => [
                        'template' => 'admin/CRUD/list_action_show_factura.html.twig'
                    ]
                ]

            ]);
    }

    protected function configureActionButtons(array $buttonList, string $action, ?object $object = null): array
    {

        // AÑADIMOS LA NUEVA ACCIÓN
        if (in_array($action, ['edit', 'show']) && $object) {
            $buttonList['createRectificativa'] = [
                'template' => 'admin/CRUD/list__action_crear_factura_rectificativa.html.twig',
            ];
            $buttonList['showFacturaFactura'] = [
                'template' => 'admin/CRUD/button_show_factura_factura.html.twig',
            ];
        }

        return $buttonList;
    }



    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->add('showFacturaFactura', $this->getRouterIdParameter() . '/show-factura');
        $collection->add('showFacturaRectificativa', $this->getRouterIdParameter() . '/show-rectificativa');
        $collection->add('createRectificativa', $this->getRouterIdParameter() . '/crea-rectificativa');
        $collection->remove('create');
    }
}