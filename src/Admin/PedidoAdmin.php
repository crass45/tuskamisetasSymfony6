<?php

namespace App\Admin;

use App\Entity\Pedido;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Filter\CallbackFilter;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\Form\Type\CollectionType;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class PedidoAdmin extends AbstractAdmin
{

//https://www.mrw.es/seguimiento_envios/MRW_historico_nacional.asp?enviament=02804F056109
    // Inyección de todos los servicios necesarios
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface        $mailer,
        private Environment            $twig,
        private Pdf                    $snappy,
        private RequestStack           $requestStack
    )
    {
        parent::__construct();
    }


    // --- INICIO DE LA CORRECCIÓN ---

    /**
     * Este método modifica directamente la consulta de la lista para asegurar
     * que siempre se ordene por fecha descendente por defecto.
     */
    protected function configureQuery(ProxyQueryInterface $query): ProxyQueryInterface
    {
//        $sortBy = $this->getDatagrid()->getValue('_sort_by');

        // Si el usuario no ha hecho clic en ninguna columna para ordenar,
        // aplicamos nuestro orden por defecto.
//        if (!$sortBy) {
        $query->addOrderBy($query->getRootAliases()[0] . '.fecha', 'DESC');
//        }

        return $query;
    }

    protected function configureFormFields(FormMapper $form): void
    {
        /** @var Pedido|null $pedido */
        $pedido = $this->getSubject();

        $infoGruposHtml = '';

        if ($pedido && $pedido->getId() && $pedido->getContacto()) {
            $contacto = $pedido->getContacto();
            $usuario = $contacto->getUsuario();

            $badges = [];

            // 1. Grupos reales del Usuario (si tiene usuario asociado)
            if ($usuario) {
                foreach ($usuario->getGroups() as $grupo) {
                    $badges[] = sprintf(
                        '<span class="label label-primary" style="font-size: 11px; margin-right: 5px; padding: 4px 8px;">%s</span>',
                        $grupo->getName()
                    );
                }
            }

            // 2. Grupos "Hardcodeados" / Lógica de Negocio (basado en Contacto)

            // a) Recargo de Equivalencia
            // Ajusta 'isRecargoEquivalencia()' al método real de tu entidad Contacto
            if (method_exists($contacto, 'isRecargoEquivalencia') && $contacto->isRecargoEquivalencia()) {
                $badges[] = '<span class="label label-warning" style="font-size: 11px; margin-right: 5px; padding: 4px 8px;">Recargo Equivalencia</span>';
            } elseif (property_exists($contacto, 'recargo') && $contacto->isRecargoEquivalencia()) {
                // Alternativa si se llama getRecargo()
                $badges[] = '<span class="label label-warning" style="font-size: 11px; margin-right: 5px; padding: 4px 8px;">Recargo Equivalencia</span>';
            }

            // b) Intracomunitario
            // Ajusta 'isIntracomunitario()' al método real
            if (method_exists($contacto, 'isIntracomunitario') && $contacto->isIntracomunitario()) {
                $badges[] = '<span class="label label-success" style="font-size: 11px; margin-right: 5px; padding: 4px 8px;">Intracomunitario</span>';
            }

            // 3. Generar HTML final
            if (count($badges) > 0) {
                $infoGruposHtml = '<div style="margin-top: 8px;">' . implode(' ', $badges) . '</div>';
            } else {
                $infoGruposHtml = '<span class="text-muted" style="font-size: 11px;"><i class="fa fa-info-circle"></i> Sin grupos ni condiciones especiales</span>';
            }
        }

        $form->tab("Pedido");

        // 2. Generamos el HTML del botón dinámicamente
        $botonVerImagen = '';
        if ($pedido && $pedido->getMontaje()) {
            $ruta = $pedido->getMontaje();

            $botonVerImagen = sprintf(
                '<a href="%s" target="_blank" class="btn btn-sm btn-info" style="margin-top: 5px;">' .
                '<i class="fa fa-external-link" aria-hidden="true"></i> Ver Montaje</a>',
                $ruta
            );
        }

        $form->with("General", ["class" => "col-md-9"])
            ->add('gruposUsuarioInfo', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'label' => 'Tipo de Cliente', // Título bonito
                'mapped' => false,               // No se guarda en BBDD
                'required' => false,
                'disabled' => true,              // No editable

                // EL TRUCO: Ponemos el HTML en la ayuda
                'help' => $infoGruposHtml,
                'help_html' => true,

                // EL TRUCO VISUAL: Ocultamos el "input" gris feo para dejar solo el texto
                'attr' => ['style' => 'display: none;']
            ])
            ->add('fecha', DateTimePickerType::class, ['format' => 'dd-MM-yyyy', "disabled" => true])
            ->add('fechaEntrega', DateTimePickerType::class, ['format' => 'dd-MM-yyyy', 'required' => false])
            ->add('bultosEstimados', null, ["disabled" => true])
            ->add('codigoSermepa', null, ["disabled" => true])
            ->add('seguimientoEnvio', TextType::class, ['required' => false])
            ->add('referenciaInterna', TextType::class, ['label' => 'Nombre del montaje', 'required' => false])
            ->add('montaje', TextType::class, ['label' => 'Enlace al montaje (Drive)', 'required' => false, 'help' => $botonVerImagen,
                'help_html' => true
                ])
            ->add('enviaMail', CheckboxType::class, ['required' => false, 'label' => 'Enviar e-mail al cambiar estado','mapped' => true])
            ->add('estado')
            ->add('incidencias', TextareaType::class, ['required' => false, 'attr' => ['class' => 'tinymce']])
            ->end()
            ->with("Subtotales", ["class" => "col-md-3"])
            ->add('diasAdicionales', IntegerType::class, ['label' => 'Días adicionales para el envío', 'required' => false])
            ->add('pedidoExpres', CheckboxType::class, ['required' => false])
            ->add('recogerEnTienda', CheckboxType::class, ['required' => false])
            ->add('subTotal', NumberType::class)
            ->add('envio', NumberType::class, ['required' => false])
            ->add('iva', NumberType::class, ['required' => false])
            ->add('recargoEquivalencia', NumberType::class, ['required' => false])
            ->add('total', NumberType::class)
            ->add('cantidadPagada', NumberType::class)
            ->end()
            ->with("Lineas Libres")
            ->add('lineasLibres', CollectionType::class, [ // Corregido: pedidoHasLineasLibre -> lineasLibres
                'by_reference' => false,
            ], [
                'edit' => 'inline',
                'inline' => 'table',
            ])
            ->end()
            ->end()
            ->tab('Observaciones')
            ->with('Observaciones')
            ->add('observacionesInternas', TextareaType::class, ['required' => false, 'attr' => ['class' => 'tinymce']])
            ->add('observaciones', TextareaType::class, ['label' => 'Observaciones introducidas por el cliente', 'required' => false, 'attr' => ['class' => 'tinymce']])
            ->end()
            ->end()
            ->tab('Detalle Pedido')
            ->with('Líneas del Pedido', ['class' => 'col-md-12'])
            ->add('lineas', CollectionType::class, [
                'label' => false,
                'by_reference' => false,
//                'allow_add' => true,
//                'allow_delete' => true,
            ], [
                'edit' => 'inline',
                'inline' => 'table',
                'admin_code' => PedidoLineaAdmin::class,
            ])
            ->end()
            ->end();

        if ($pedido?->getContacto()) {
            $form
                ->tab('Datos Facturación')
                ->with('Datos del Cliente', ['class' => 'col-md-12'])
                ->add('contacto', ModelListType::class)
                ->add('contacto.nombre', TextType::class, ['label' => 'Nombre', 'disabled' => true])
                ->add('contacto.apellidos', TextType::class, ['label' => 'Apellidos', 'disabled' => true])
                ->add('contacto.cif', TextType::class, ['label' => 'DNI/CIF', 'disabled' => true])
                ->add('contacto.telefonoOtro', TextType::class, ['label' => 'Teléfono', 'attr' => ['readonly' => true]])
                ->add('contacto.telefonoMovil', TextType::class, ['label' => 'Teléfono Móvil', 'attr' => ['readonly' => true]])
                ->end()
                ->end();
            if (!$pedido->getRecogerEnTienda()) {
                $form
                    ->tab('Dirección Envio')
                    ->with('Datos de Envío', ['class' => 'col-md-12'])
                    // --- INICIO DE LA MEJORA ---
                    ->add('agencia', ChoiceType::class, [
                        'mapped' => false,
                        'label' => 'Selecciona la Agencia de envío',
                        'choices' => [
                            'Nacex' => '1',
                            'MRW' => '2',
                            'ViaXpress' => '3'
                        ],
                        'required' => false,
                        'placeholder' => 'Elige una agencia...'
                    ])
                    ->add('bultos', IntegerType::class, [
                        'mapped' => false,
                        'label' => 'Nº de Bultos',
                        'data' => 1,
                        'attr' => ['min' => 1]
                    ])
                    ->add('servicio', ChoiceType::class, [
                        'mapped' => false,
                        'label' => 'Tipo de Servicio (MRW)',
                        'choices' => [
                            'Normal' => '0205',
                            '24h Aseguradas' => '0115',
                            'Entrega Sabados' => '0015',
                            'Baleares Maritimo' => '0370',
                        ],
                        'required' => false,
                        'attr' => ['class' => 'servicio-mrw-select'] // Clase para el JS
                    ])
                    ->add('direccion', ModelListType::class)
                    // --- FIN DE LA MEJORA ---
                    ->end()
                    ->end();
            }
        } else {
            $form
                ->tab('Datos Cliente')
                ->with('Cliente Temporal')
                ->add('nombreClienteTemporal', TextType::class)
                ->add('emailClienteTemporal', TextType::class)
                ->add('ciudadClienteTemporal', TextType::class)
                ->add('contacto', ModelListType::class, ['label' => '¿Asociar a un cliente existente?']) // Corregido: idUsuario -> contacto
                ->end()
                ->end();
        }
    }

// --- FIN DE LA MEJORA ---
    protected
    function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('nombre', null, ['label' => 'Código Presupuesto'])
            ->add('referenciaInterna', null, ['label' => 'Nombre Montaje'])
            ->add('contacto.cif', null, ['label' => 'CIF']) // Corregido
            ->add('contacto.usuario.email', null, ['label' => 'Email']) // Corregido
            ->add('estado')
            ->add('cantidadPagada')
            ->add('fechaEntrega', DateTimeRangeFilter::class)
            ->add('fecha', DateTimeRangeFilter::class)
            ->add('codigoSermepa');
        // --- INICIO DE LA MEJORA ---
        // Se añade el filtro personalizado que llama a un método de callback
        if (!$this->isChild()) {
            $datagrid->add('pedidoAProveedor', CallbackFilter::class, [
                'label' => 'Pedido a Proveedor',
                'callback' => [$this, 'isPedidoAProveedor'],
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => [
                        'No' => 0,
                        'Sí' => 1,
                    ],
                    'placeholder' => 'Ambos',
                ],
            ]);
        }
        // --- FIN DE LA MEJORA ---

    }

    protected
    function configureListFields(ListMapper $list): void
    {
        $list
            ->add('fecha', 'datetime', ['format' => 'd-m-Y'])
            ->addIdentifier('nombre')
            ->add('fechaEntrega', 'datetime', ['format' => 'd-m-Y', 'template' => 'admin/CRUD/list_fecha_pedido.html.twig'])
            ->add('contacto') // Corregido
            ->add('estado', null, ['template' => 'admin/CRUD/list_estado_pedido.html.twig'])
            ->add('total', 'currency', ['currency' => 'EUR'])
            ->add('referenciaInterna', null, [
                'label' => 'Nombre Montaje',
                'template' => 'admin/CRUD/list_pedido_montaje.html.twig' // Usamos nuestra plantilla
            ])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'showPDF' => ['template' => 'admin/CRUD/list_action_orden_pedido.html.twig']
                ]
            ]);
    }

// El método configureShowFields se migra de forma similar, corrigiendo rutas de propiedades...

//    public
//    function preUpdate(object $object): void
//    {
//        if (!$object instanceof Pedido) {
//            return;
//        }
//
//        $uow = $this->entityManager->getUnitOfWork();
//        $original = $uow->getOriginalEntityData($object);
//
//        $estadoAnt = $original['estado'] ?? null;
//
//        // ALMACENAMOS EN EL LOG
//        if ($estadoAnt !== $object->getEstado()) {
//            //DE MOMENTO NO TENEMOS LOGS.
////            $pedidoLog = new PedidosLog();
////            $pedidoLog->setEstado($object->getEstado());
////            $pedidoLog->setUsuario($object->getContacto());
////            $this->entityManager->persist($pedidoLog);
//            // No hacemos flush aquí, se hará al final de la operación.
//        }
//
//        // ENVIAR EMAIL AL CAMBIAR ESTADO
//        if ($object->isEnviaMail() && $object->getEstado()?->isEnviaCorreo()) {
//            // Toda la lógica de Swift_Message se debe reemplazar con Symfony Mailer
//            $email = (new Email())
//                ->from('comercial@tuskamisetas.com')
//                ->to($object->getContacto()?->getUsuario()?->getEmail())
//                ->subject('Actualización de tu pedido ' . $object->getNombre())
//                ->html($this->twig->render('emails/order_status_update.html.twig', [
//                    'pedido' => $object
//                ]));
//
//            $this->mailer->send($email);
//            $object->setEnviaMail(false); // Desmarcar para no reenviar
//
//            $flashBag = $this->requestStack->getSession()->getFlashBag();
//            $flashBag->add('sonata_flash_success', 'Correo de estado enviado correctamente.');
//        }
//    }

    public
    function postUpdate(object $object): void
    {
        if (!$object instanceof Pedido) {
            return;
        }

        // Lógica de Recalcular Totales
        $subtotal = 0;
        foreach ($object->getLineas() as $linea) {
            $subtotal += (float)$linea->getPrecio() * $linea->getCantidad();
        }
        foreach ($object->getLineasLibres() as $linea) {
            $subtotal += (float)$linea->getPrecio() * $linea->getCantidad();
        }

        // El resto de la lógica de cálculo...
        $object->setSubTotal($subtotal);
        // ... calcular iva, total, etc.
        $this->entityManager->flush();
    }

    protected
    function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->add('showPDF', $this->getRouterIdParameter() . '/show-pdf');
        $collection->add('showOrdenPedido', $this->getRouterIdParameter() . '/show-orden-pedido');
        $collection->add('showProforma', $this->getRouterIdParameter() . '/show-proforma');
        $collection->add('verEtiquetas', $this->getRouterIdParameter().'/ver-etiquetas');
        $collection->add('editarPedido', $this->getRouterIdParameter() . '/editar-pedido');
        $collection->add('facturar', $this->getRouterIdParameter() . '/facturar');
        $collection->add('showFactura', $this->getRouterIdParameter() . '/show-factura');
        $collection->add('recalcular', $this->getRouterIdParameter() . '/recalcular');
        $collection->add('documentarEnvioNACEX', $this->getRouterIdParameter() . '/documentar-envio-nacex/{bultos}/{servicio}');
        $collection->add('documentarEnvioMrw', $this->getRouterIdParameter().'/documentar-envio-mrw/{bultos}/{servicio}');
        $collection->add('reloadEnvio', 'reload-envio');
        $collection->remove('create');
    }

    /**
     * Se añaden los botones a la vista de edición.
     */
    protected
    function configureActionButtons(array $buttonList, string $action, ?object $object = null): array
    {
        if (in_array($action, ['edit', 'show']) && $object) {
            $buttonList['showPDF'] = [
                'template' => 'admin/button_show_pdf.html.twig',
            ];
            $buttonList['showOrdenPedido'] = [
                'template' => 'admin/button_show_orden_pedido.html.twig',
            ];
            $buttonList['showProforma'] = [
                'template' => 'admin/button_show_proforma.html.twig',
            ];
            $buttonList['recalcular'] = [
                'template' => 'admin/button_recalculate.html.twig',
            ];
            $buttonList['editarPedido'] = [
                'template' => 'admin/button_edit_as_cart.html.twig',
            ];

            // --- INICIO DE LA MEJORA ---
            // Se añade lógica condicional para mostrar el botón correcto
            if ($object->getSeguimientoEnvio()) {
                // Si el pedido ya ha sido enviado, se muestra el botón para ver la etiqueta
                $buttonList['verEtiquetas'] = [
                    'template' => 'admin/button_ver_etiqueta.html.twig',
                ];
            } else {
                // Si no, se muestra el botón para documentar el envío
                $buttonList['documentarEnvio'] = [
                    'template' => 'admin/button_documentar_envio.html.twig',
                ];
            }
            // --- FIN DE LA MEJORA ---

            if ($object->getFactura()) {
                $buttonList['showFactura'] = [
                    'template' => 'admin/button_show_factura.html.twig',
                ];
            } else {
                $buttonList['facturar'] = [
                    'template' => 'admin/button_facturar.html.twig',
                ];
            }
        }

        return $buttonList;
    }

    public
    function isPedidoAProveedor(ProxyQueryInterface $queryBuilder, string $alias, string $field, FilterData $value): bool
    {
        // Si no se ha seleccionado ninguna opción en el filtro, no hacemos nada
        if (!$value->hasValue()) {
            return false;
        }

        // Si el usuario selecciona "No", se aplica la lógica de filtrado
        if ($value->getValue() == '0') {
            $queryBuilder
                ->andWhere(sprintf('%s.estado IN (:estados_proveedor)', $alias))
                ->andWhere(sprintf('%s.cantidadPagada > 0', $alias))
                ->andWhere(sprintf('%s.fechaEntrega IS NOT NULL', $alias))
                ->setParameter('estados_proveedor', [3, 4, 10]);

            // Se devuelve 'true' para indicar que el filtro se ha aplicado
            return true;
        }

        return false;
    }
}