<?php
// src/Service/OrderService.php

namespace App\Service;

use App\Entity\Contacto;
use App\Entity\Direccion;
use App\Entity\Empresa;
use App\Entity\Estado;
use App\Entity\Pedido;
use App\Entity\PedidoLinea;
use App\Entity\PedidoTrabajo;
use App\Entity\PedidoLineaHasTrabajo;
use App\Entity\Personalizacion;
use App\Entity\Producto;
use App\Model\Carrito;
use App\Model\Presupuesto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment as TwigEnvironment;

/**
 * Servicio para gestionar la lógica de negocio de la creación de pedidos.
 */
class OrderService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private TwigEnvironment $twig,
        private Security $security,
        private FechaEntregaService $deliveryDateService
    ) {
    }

    /**
     * Decide si crear un nuevo pedido o actualizar uno existente.
     */
    public function createOrUpdateOrderFromCart(
        Carrito $carrito,
        Contacto $contacto,
        ?Direccion $direccionEnvio = null,
        ?string $googleClientId = null,
        ?int $editingOrderId = null,
        ?int $tipoEnvio = null
    ): Pedido {
        if ($editingOrderId) {
            return $this->updateOrderFromCart($carrito, $editingOrderId, $tipoEnvio);
        }

        return $this->createOrderFromCart($carrito, $contacto, $direccionEnvio, $googleClientId, $tipoEnvio);
    }

    private function createOrderFromCart(
        Carrito $carrito,
        Contacto $contacto,
        ?Direccion $direccionEnvio,
        ?string $googleClientId,
        ?int $tipoEnvio
    ): Pedido {
        $fecha = new \DateTime();
        $fiscalYear = (int)$fecha->format('y');
        $ultimoPedido = $this->em->getRepository(Pedido::class)->findOneBy(['fiscalYear' => $fiscalYear], ['numeroPedido' => 'DESC']);
        $numeroPedido = $ultimoPedido ? $ultimoPedido->getNumeroPedido() + 1 : 1;

        $pedido = new Pedido($fecha, $fiscalYear, $numeroPedido);
        $pedido->setGoogleClientId($googleClientId);
        $pedido->setContacto($contacto);
        $pedido->setDireccion($direccionEnvio ?? $contacto->getDireccionFacturacion());

        // Se rellena el pedido con los datos del carrito
        $this->fillPedidoFromCarrito($pedido, $carrito, $tipoEnvio);

        $this->em->persist($pedido);
        $this->em->flush();

        $empresa = $this->em->getRepository(Empresa::class)->findOneBy([]);
        $this->sendConfirmationEmails($pedido, $empresa);
        return $pedido;
    }

    /**
     * Actualiza un pedido existente con los datos de un carrito.
     */
    private function updateOrderFromCart(Carrito $carrito, int $orderId, ?int $tipoEnvio): Pedido
    {
        $pedido = $this->em->getRepository(Pedido::class)->find($orderId);
        if (!$pedido) {
            throw new \Exception('No se encontró el pedido para actualizar.');
        }

        // 1. Borramos las líneas y trabajos antiguos para evitar inconsistencias
        foreach($pedido->getLineas() as $linea) {
            $this->em->remove($linea);
        }
        // También borramos las líneas libres que pudieran existir
        foreach($pedido->getLineasLibres() as $lineaLibre){
            $this->em->remove($lineaLibre);
        }

        $this->em->flush(); // Se aplica el borrado

        // 2. Rellenamos el pedido con los nuevos datos del carrito
        $this->fillPedidoFromCarrito($pedido, $carrito, $tipoEnvio);

        $this->em->flush();
        return $pedido;
    }

    /**
     * Rellena un objeto Pedido con los datos de un Carrito.
     * Esta lógica ahora está centralizada aquí.
     */
    private function fillPedidoFromCarrito(Pedido $pedido, Carrito $carrito, ?int $tipoEnvio): void
    {
        $user = $this->security->getUser();
        $contacto = $pedido->getContacto();

        // 1. Asignar estado inicial si es un pedido nuevo
        if (!$pedido->getId()) {
            $estadoInicial = $this->em->getRepository(Estado::class)->find(1); // 'Pendiente'
            if ($estadoInicial) {
                $pedido->setEstado($estadoInicial);
            }
        }

        // 2. Asignar observaciones y opciones de envío
        $pedido->setObservaciones($carrito->getObservaciones());
        $pedido->setPedidoExpres($carrito->isServicioExpres());
        $pedido->setRecogerEnTienda($tipoEnvio === 3);

        // 3. Crear las líneas del pedido y los trabajos de personalización
        $trabajosCreados = [];
        foreach ($carrito->getItems() as $presupuesto) {
            $arrayTrabajosParaLinea = [];

            foreach ($presupuesto->getTrabajos() as $trabajoPresupuesto) {
                $personalizacionEntity = $this->em->getRepository(Personalizacion::class)->findOneBy(['codigo' => $trabajoPresupuesto->getTrabajo()->getCodigo()]);
                if ($personalizacionEntity) {
                    $codigoUnico = $trabajoPresupuesto->getIdentificadorTrabajo();
                    if (!isset($trabajosCreados[$codigoUnico])) {
                        $trabajoBD = new PedidoTrabajo();
                        $trabajoBD->setCodigo($codigoUnico);
                        $trabajoBD->setPersonalizacion($personalizacionEntity);
                        $trabajoBD->setNColores($trabajoPresupuesto->getCantidad());
                        $trabajoBD->setUrlImagen($trabajoPresupuesto->getUrlImage());
                        $trabajoBD->setContacto($contacto);
                        $this->em->persist($trabajoBD);
                        $trabajosCreados[$codigoUnico] = $trabajoBD;
                    }
                    $arrayTrabajosParaLinea[] = ['trabajoBD' => $trabajosCreados[$codigoUnico], 'presupuestoTrabajo' => $trabajoPresupuesto];
                }
            }

            foreach ($presupuesto->getProductos() as $productoPresupuesto) {
                if ($productoPresupuesto->getCantidad() > 0) {
                    $productoEntity = $this->em->getRepository(Producto::class)->find($productoPresupuesto->getProducto()->getId());
                    if ($productoEntity) {
                        $pedidoLinea = new PedidoLinea();
                        $pedidoLinea->setCantidad($productoPresupuesto->getCantidad());
                        $pedidoLinea->setProducto($productoEntity);
                        $pedidoLinea->setPrecio($productoEntity->getPrecioUnidad());

                        $personalizacionCadena = "";
                        foreach ($arrayTrabajosParaLinea as $trabajoData) {
                            $pedidoLineaTrabajo = new PedidoLineaHasTrabajo();
                            $pedidoLineaTrabajo->setPedidoLinea($pedidoLinea);
                            $pedidoLineaTrabajo->setPedidoTrabajo($trabajoData['trabajoBD']);
                            $pedidoLineaTrabajo->setUbicacion($trabajoData['presupuestoTrabajo']->getUbicacion());
                            $pedidoLineaTrabajo->setObservaciones($trabajoData['presupuestoTrabajo']->getObservaciones());
                            $pedidoLineaTrabajo->setCantidad($trabajoData['presupuestoTrabajo']->getCantidad());
                            $pedidoLinea->addPersonalizacione($pedidoLineaTrabajo);
                            $trabajo = $trabajoData['presupuestoTrabajo'];
                            $personalizacionCadena .= (string) $trabajo . "\n";
                        }
                        $pedidoLinea->setPersonalizacion(trim($personalizacionCadena));
                        $pedido->addLinea($pedidoLinea);
                    }
                }
            }
        }

        // 4. Calcular y asignar totales
        $subtotal = $carrito->getSubTotal($user);
        $gastosEnvio = $carrito->getGastosEnvio($user);
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy([]);
        $ivaGeneral = $empresa ? $empresa->getIvaGeneral() / 100 : 0.21;
        $iva = ($subtotal + $gastosEnvio) * $ivaGeneral;
        $total = $subtotal + $gastosEnvio + $iva;

        $pedido->setSubTotal($subtotal);
        $pedido->setEnvio($gastosEnvio);
        $pedido->setIva($iva);
        $pedido->setTotal($total);
    }

    private function sendConfirmationEmails(Pedido $pedido, Empresa $empresa): void
    {
        // --- INICIO DE LA CORRECCIÓN ---
        // Agrupamos las líneas del pedido por un identificador único de sus personalizaciones
        $groupedLines = [];
        foreach ($pedido->getLineas() as $linea) {
            $trabajoIds = [];
            // Recogemos los IDs de todos los trabajos asociados a esta línea
            foreach ($linea->getPersonalizaciones() as $pedidoLineaHasTrabajo) {
                // Usamos el ID del PedidoTrabajo, que es único para cada personalización
                $trabajoIds[] = $pedidoLineaHasTrabajo->getPedidoTrabajo()->getId();
            }

            // Si no hay trabajos, la clave es simple
            if (empty($trabajoIds)) {
                $key = 'sin-personalizacion';
            } else {
                // Ordenamos los IDs para que la clave sea consistente y los unimos
                sort($trabajoIds);
                $key = implode('-', $trabajoIds);
            }

            $groupedLines[$key][] = $linea;
        }
        // --- FIN DE LA CORRECCIÓN ---

        // Enviar email al administrador
        $adminEmail = (new Email())
            ->from('comercial@tuskamisetas.com')
            ->to('comercial@tuskamisetas.com')
            ->subject('Nuevo Pedido Recibido: ' . $pedido)
            // 2. Pasamos el nuevo array agrupado a la plantilla
            ->html($this->twig->render('emails/correoPedido.html.twig', array('pedido' => $pedido,'grouped_lines'=>$groupedLines, 'url_pedido' => 'https://tuskamisetas.com/admin/ss/tienda/pedido/' . $pedido->getId() . '/edit', 'metodoPago' => 'Transferencia Bancaría', 'emailTitulo' => '¡Nuevo pedido en la tienda!', 'emailSubtitulo' => 'Se ha realizado un nuevo pedido en la web', 'emailTexto' => 'puedes ver más detalles desde la administración.<br><br><b>Observaciones:</b> ' . $pedido->getObservaciones(), 'empresa' => $empresa)));

        $this->mailer->send($adminEmail);

        // Enviar email al cliente
        $clientEmail = (new Email())
            ->from('comercial@tuskamisetas.com')
            ->to($pedido->getContacto()->getUsuario()->getEmail())
            ->subject('Confirmación de tu pedido en Tuskamisetas: ' . $pedido)
            // 3. Pasamos el mismo array agrupado a la plantilla del cliente
            ->html($this->twig->render('emails/correoPedido.html.twig', array('pedido' => $pedido,'grouped_lines'=>$groupedLines, 'url_pedido' => 'https://tuskamisetas.com/admin/ss/tienda/pedido/' . $pedido->getId() . '/edit', 'metodoPago' => 'Transferencia Bancaría', 'emailTitulo' => '¡Nuevo pedido en la tienda!', 'emailSubtitulo' => 'Se ha realizado un nuevo pedido en la web', 'emailTexto' => 'puedes ver más detalles desde la administración.<br><br><b>Observaciones:</b> ' . $pedido->getObservaciones(), 'empresa' => $empresa)));
        $this->mailer->send($clientEmail);
    }



    /**
     * NUEVO MÉTODO: Envía los correos de confirmación cuando un pago es exitoso.
     * Migración de la lógica de tu 'pedidoPagoConfirmadoBancoAction'.
     */
    public function sendPaymentSuccessEmails(Pedido $pedido): void
    {
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy([]);
        $emailCliente = $pedido->getContacto()?->getUsuario()?->getEmail();

        if (!$emailCliente) {
            // No se puede enviar correo si no hay un email de cliente.
            // Aquí podrías añadir un log de error.
            return;
        }

        // 1. Enviar email al administrador
        $adminEmail = (new Email())
            ->from('comercial@tuskamisetas.com')
            ->to('comercial@tuskamisetas.com')
            ->bcc('info@tuskamisetas.com')
            ->subject('Pedido Pagado: ' . $pedido)
            ->html($this->twig->render('emails/payment_success_admin.html.twig', [
                'pedido' => $pedido,
                'empresa' => $empresa
            ]));
        $this->mailer->send($adminEmail);

        // 2. Enviar email al cliente
        $clientEmail = (new Email())
            ->from('comercial@tuskamisetas.com')
            ->to($emailCliente)
            ->subject('¡Hemos recibido el pago de tu pedido! (' . $pedido . ')')
            ->html($this->twig->render('emails/payment_success_client.html.twig', [
                'pedido' => $pedido,
                'empresa' => $empresa
            ]));
        $this->mailer->send($clientEmail);
    }
}

