<?php

namespace App\Admin;

use App\Entity\Pedido;
use Doctrine\ORM\EntityManagerInterface;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Knp\Snappy\Pdf;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\Form\Type\CollectionType;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class PedidoAdmin extends AbstractAdmin
{
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

    protected array $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_sort_by' => 'fecha',
    ];

    protected function configure(): void
    {
        // Corregido: idUsuario -> contacto
        $this->parentAssociationMapping = 'contacto';
//        $this->setDatagridValues([
//            '_page' => 1,
//            '_sort_order' => 'DESC',
//            '_sort_by' => 'fecha',
//        ]);
    }

    protected function configureFormFields(FormMapper $form): void
    {
        /** @var Pedido|null $pedido */
        $pedido = $this->getSubject();

        $form
            ->tab("Pedido");
            if ($pedido && $pedido->getContacto() && $pedido->getContacto()->getUsuario()) {
//                $form->with("General") // Volvemos a abrir el mismo grupo de campos
//                ->add('contacto.groups', ModelType::class, [
//                    'label' => "Grupos del Usuario (solo lectura)",
//                    'disabled' => true,
//                    'btn_add' => false,
//                    'required' => false,
//                    'multiple' => true,
//                    'choice_label' => 'name',
//                ])
//                    ->end();
            }
            $form->with("General", ["class" => "col-md-9"])
            // Corregido: idUsuario -> contacto
//            ->add('contacto.usuario.groups', ModelType::class, [
//                'label' => "Grupos",
//                'disabled' => true,
//                'btn_add' => false,
//                'required' => false,
//                'multiple' => true,
//            ])
            ->add('fecha', DateTimePickerType::class, ['format' => 'dd-MM-yyyy', "disabled" => true])
            ->add('fechaEntrega', DateTimePickerType::class, ['format' => 'dd-MM-yyyy', 'required' => false])
            ->add('bultosEstimados', null, ["disabled" => true])
            ->add('codigoSermepa', null, ["disabled" => true])
            ->add('seguimientoEnvio', TextType::class, ['required' => false])
            ->add('referenciaInterna', TextType::class, ['label' => 'Nombre del montaje', 'required' => false])
            ->add('montaje', TextType::class, ['label' => 'Enlace al montaje (WeTransfer)', 'required' => false])
            ->add('enviaMail', CheckboxType::class, ['required' => false, 'label' => 'Enviar e-mail al cambiar estado'])
            ->add('estado', ModelType::class)
            ->add('incidencias', CKEditorType::class, ['required' => false])
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
            ->add('observacionesInternas', CKEditorType::class, ['required' => false])
            ->add('observaciones', CKEditorType::class, ['label' => 'Observaciones introducidas por el cliente', 'required' => false])
            ->end()
            ->end()
            ->tab("Detalle Pedido")
            ->with("Lineas de Pedido")
//            ->add('lineas', ModelListType::class, [ // Corregido: pedidoHasLineas -> lineas
//                'by_reference' => false,
//            ], [
//                'edit' => 'inline',
//                'inline' => 'table',
//            ])
            ->end()
            ->end();

        if ($pedido?->getContacto()) {
            $form
                ->tab('Datos Facturación')
                ->with('Datos del Cliente')
                ->add('contacto.nombre', TextType::class, ['label' => 'Nombre', 'disabled' => true])
                ->add('contacto.apellidos', TextType::class, ['label' => 'Apellidos', 'disabled' => true])
                ->add('contacto.direccionFacturacion.dir', TextType::class, ['label' => 'Dirección', 'disabled' => true])
                // ... y así sucesivamente para los otros campos de solo lectura ...
                ->end()
                ->end();
            if (!$pedido->getRecogerEnTienda()) {
                $form->tab('Dirección Envio')
                    ->with('Datos de Envío')
                    ->add('agencia', ChoiceType::class, [
                        'mapped' => false,
                        'label' => 'Selecciona la Agencia de envío',
                        'choices' => [
                            'Nacex' => '1',
                            'MRW' => '2',
                            'ViaXpress' => '3'
                        ],
                        'required' => false
                    ])
                    // ... resto de campos de envío no mapeados ...
                    ->add('direccion', ModelListType::class) // Corregido: idDireccion -> direccion
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

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
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
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('fecha', 'datetime', ['format' => 'd-m-Y'])
            ->add('nombre')
            ->add('fechaEntrega', 'datetime', ['format' => 'd-m-Y', 'template' => 'admin/CRUD/list_fecha_pedido.html.twig'])
            ->add('contacto') // Corregido
            ->add('estado', null, ['template' => 'admin/CRUD/list_estado_pedido.html.twig'])
            ->add('total', 'currency', ['currency' => 'EUR'])
            ->add('referenciaInterna')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'showPDF' => ['template' => 'admin/CRUD/list_action_orden_pedido.html.twig']
                ]
            ]);
    }

    // El método configureShowFields se migra de forma similar, corrigiendo rutas de propiedades...

    public function preUpdate(object $object): void
    {
        if (!$object instanceof Pedido) {
            return;
        }

        $uow = $this->entityManager->getUnitOfWork();
        $original = $uow->getOriginalEntityData($object);

        $estadoAnt = $original['estado'] ?? null;

        // ALMACENAMOS EN EL LOG
        if ($estadoAnt !== $object->getEstado()) {
            $pedidoLog = new PedidosLog();
            $pedidoLog->setEstado($object->getEstado());
            $pedidoLog->setUsuario($object->getContacto());
            $this->entityManager->persist($pedidoLog);
            // No hacemos flush aquí, se hará al final de la operación.
        }

        // ENVIAR EMAIL AL CAMBIAR ESTADO
        if ($object->isEnviaMail() && $object->getEstado()?->isEnviaCorreo()) {
            // Toda la lógica de Swift_Message se debe reemplazar con Symfony Mailer
            $email = (new Email())
                ->from('comercial@tuskamisetas.com')
                ->to($object->getContacto()?->getUsuario()?->getEmail())
                ->subject('Actualización de tu pedido ' . $object->getNombre())
                ->html($this->twig->render('emails/order_status_update.html.twig', [
                    'pedido' => $object
                ]));

            $this->mailer->send($email);
            $object->setEnviaMail(false); // Desmarcar para no reenviar

            $flashBag = $this->requestStack->getSession()->getFlashBag();
            $flashBag->add('sonata_flash_success', 'Correo de estado enviado correctamente.');
        }
    }

    public function postUpdate(object $object): void
    {
        if (!$object instanceof Pedido) {
            return;
        }

        // Lógica de Recalcular Totales
        $subtotal = 0;
        foreach ($object->getLineas() as $linea) {
            $subtotal += (float)$linea->getPrecio() * $linea->getCantidad();
        }
        foreach ($object->getLineasLibre() as $linea) {
            $subtotal += (float)$linea->getPrecio() * $linea->getCantidad();
        }

        // El resto de la lógica de cálculo...
        $object->setSubTotal($subtotal);
        // ... calcular iva, total, etc.
        $this->entityManager->flush();
    }

    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->add('showPDF', $this->getRouterIdParameter() . '/show-pdf');
        $collection->add('showOrdenPedido', $this->getRouterIdParameter() . '/show-orden-pedido');
        $collection->add('showProforma', $this->getRouterIdParameter() . '/show-proforma');
        $collection->add('documentarEnvioNACEX', $this->getRouterIdParameter() . '/documentar-envio-nacex/{agencia}/{bultos}/{servicio}');
        $collection->add('verEtiquetas', $this->getRouterIdParameter() . '/ver-etiquetas');
        $collection->add('editarPedido', $this->getRouterIdParameter() . '/editar-pedido');
        $collection->add('facturar', $this->getRouterIdParameter() . '/facturar');
        $collection->add('showFactura', $this->getRouterIdParameter() . '/show-factura');
        $collection->add('recalcular', $this->getRouterIdParameter() . '/recalcular');
        $collection->add('reloadEnvio', 'reload-envio');
        $collection->remove('create');
    }
}