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
        private MailerInterface        $mailer,
        private TwigEnvironment        $twig,
        private Security               $security,
        private FechaEntregaService    $deliveryDateService,
        private PriceCalculatorService $priceCalculator,
        private ShippingCalculatorService $shippingCalculator
    )
    {
    }

    /**
     * Decide si crear un nuevo pedido o actualizar uno existente.
     */
    public function createOrUpdateOrderFromCart(
        Carrito    $carrito,
        Contacto   $contacto,
        ?Direccion $direccionEnvio = null,
        ?string    $googleClientId = null,
        ?int       $editingOrderId = null,
        ?int       $tipoEnvio = null
    ): Pedido
    {
        if ($editingOrderId) {
            return $this->updateOrderFromCart($carrito, $editingOrderId, $tipoEnvio);
        }

        return $this->createOrderFromCart($carrito, $contacto, $direccionEnvio, $googleClientId, $tipoEnvio);
    }

    public function createOrderFromCart(
        Carrito    $carrito,
        Contacto   $contacto,
        ?Direccion $direccionEnvio,
        ?string    $googleClientId,
        ?int       $tipoEnvio
    ): Pedido
    {
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
//        $this->sendConfirmationEmails($pedido, $empresa);
        return $pedido;
    }

    /**
     * Actualiza un pedido existente con los datos de un carrito.
     */
    public function updateOrderFromCart(Carrito $carrito, int $orderId, ?int $tipoEnvio): Pedido
    {
        $pedido = $this->em->getRepository(Pedido::class)->find($orderId);
        if (!$pedido) {
            throw new \Exception('No se encontró el pedido para actualizar.');
        }

        // 1. Borramos las líneas y trabajos antiguos para evitar inconsistencias
        foreach ($pedido->getLineas() as $linea) {
            $this->em->remove($linea);
        }
        // También borramos las líneas libres que pudieran existir
        foreach ($pedido->getLineasLibres() as $lineaLibre) {
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
        // 1. LLAMAMOS AL SERVICIO PARA OBTENER TODOS LOS CÁLCULOS
        $resultados = $this->priceCalculator->calculateFullPresupuesto($carrito);
        $contacto = $pedido->getContacto();

        if (!$pedido->getId()) {
            $estadoInicial = $this->em->getRepository(Estado::class)->find(1);
            if ($estadoInicial) { $pedido->setEstado($estadoInicial); }
        }

        $pedido->setObservaciones($carrito->getObservaciones());
        $pedido->setPedidoExpres($carrito->isServicioExpres());
        $pedido->setRecogerEnTienda($tipoEnvio === 3);

        // 2. CREAMOS LAS LÍNEAS Y TRABAJOS USANDO LOS DATOS DEL SERVICIO
        $trabajosCreados = [];
        $itemIndex = 0;
        foreach ($carrito->getItems() as $presupuesto) {
            $grupoCalculado = $resultados['desglose_grupos'][$itemIndex];
            $arrayTrabajosParaLinea = [];

            foreach ($presupuesto->getTrabajos() as $trabajoPresupuesto) {
                $unmanagedPersonalizacion = $trabajoPresupuesto->getTrabajo();
                if (!$unmanagedPersonalizacion) continue;

                $codigoUnico = $trabajoPresupuesto->getIdentificadorTrabajo();
                if (!isset($trabajosCreados[$codigoUnico])) {
                    // ¡CORRECCIÓN CLAVE! Buscamos si el PedidoTrabajo ya existe por su código.
                    $trabajoBD = $this->em->getRepository(PedidoTrabajo::class)->findOneBy(['codigo' => $codigoUnico]);

                    if (!$trabajoBD) {
                        // Si no existe, lo creamos.
                        $trabajoBD = new PedidoTrabajo();
                        $trabajoBD->setCodigo($codigoUnico);

                        // Obtenemos la versión "gestionada" por Doctrine de la Personalizacion.
                        $personalizacionEntity = $this->em->find(Personalizacion::class, $unmanagedPersonalizacion->getCodigo());

                        if ($personalizacionEntity) {
                            $trabajoBD->setPersonalizacion($personalizacionEntity);
                            $trabajoBD->setNColores($trabajoPresupuesto->getCantidad());
                            $trabajoBD->setUrlImagen($trabajoPresupuesto->getUrlImage());
                            $trabajoBD->setContacto($contacto);
                            $this->em->persist($trabajoBD);
                        }
                    }
                    $trabajosCreados[$codigoUnico] = $trabajoBD;
                }
                $arrayTrabajosParaLinea[] = ['trabajoBD' => $trabajosCreados[$codigoUnico], 'presupuestoTrabajo' => $trabajoPresupuesto];
            }

            foreach ($grupoCalculado['desglose_productos'] as $lineaCalculada) {
                // Obtenemos la versión gestionada del Producto.
                $productoEntity = $this->em->find(Producto::class, $lineaCalculada['producto']->getId());
                if ($productoEntity) {
                    $pedidoLinea = new PedidoLinea();
                    $pedidoLinea->setCantidad($lineaCalculada['unidades']);
                    $pedidoLinea->setProducto($productoEntity);
                    $pedidoLinea->setPrecio((string)$lineaCalculada['precio_unitario_final_sin_iva']);

                    $personalizacionCadena = "";
                    foreach ($arrayTrabajosParaLinea as $trabajoData) {
                        $pedidoLineaTrabajo = new PedidoLineaHasTrabajo();
                        $pedidoLineaTrabajo->setPedidoLinea($pedidoLinea);
                        $pedidoLineaTrabajo->setPedidoTrabajo($trabajoData['trabajoBD']);
                        $pedidoLineaTrabajo->setUbicacion($trabajoData['presupuestoTrabajo']->getUbicacion());
                        $pedidoLineaTrabajo->setObservaciones($trabajoData['presupuestoTrabajo']->getObservaciones());
                        $pedidoLineaTrabajo->setCantidad($trabajoData['presupuestoTrabajo']->getCantidad());
                        $pedidoLinea->addPersonalizacione($pedidoLineaTrabajo);
                        $personalizacionCadena .= (string) $trabajoData['presupuestoTrabajo'] . "\n";
                    }
                    $pedidoLinea->setPersonalizacion(trim($personalizacionCadena));
                    $pedido->addLinea($pedidoLinea);
                }
            }
            $itemIndex++;
        }

        // --- 3. ¡NUEVA LÓGICA DE GASTOS DE ENVÍO! ---
        //establecemos los gastos de envío a una zona no encontrada de momento 55€
        $gastosEnvio = 55;
        // Obtenemos la dirección de envío del pedido
        $direccionEnvio = $pedido->getDireccion();

        if ($direccionEnvio && $direccionEnvio->getProvinciaBD()) {
            // A través de la provincia, obtenemos la Zona de Envío
            $zonaEnvio = $direccionEnvio->getProvinciaBD()->getZonasEnvio()[0];
//            var_dump($zonaEnvio);

            // Llamamos a nuestro ShippingCalculatorService para obtener el coste
            $gastosEnvio = $this->shippingCalculator->calculateShippingCost(
                $carrito,
                $zonaEnvio,
                $resultados['subtotal_sin_iva']
            );
        }

        // Si es recoger en tienda, forzamos los gastos a 0, independientemente de la dirección
        if ($pedido->getRecogerEnTienda()) {
            $gastosEnvio = 0;
        }

        // ¡AQUÍ ESTÁ LA LÓGICA! Obtenemos el coste del servicio exprés si está activado
        $costeExpres = 0;
        if ($pedido->getPedidoExpres()) {
            $empresa = $this->em->getRepository(Empresa::class)->find(1);
            if ($empresa) {
                $costeExpres = (float)$empresa->getPrecioServicioExpres();
            }
        }

        // 4. ASIGNAMOS LOS TOTALES FINALES DESDE EL SERVICIO
        $subtotal = $resultados['subtotal_sin_iva'];
        $baseImponible = $subtotal + $gastosEnvio + $costeExpres;
        $iva = $baseImponible * ($resultados['iva_aplicado'] / 100);
        $total = $baseImponible + $iva;

        $pedido->setSubTotal($subtotal);
        $pedido->setEnvio($gastosEnvio);
        $pedido->setIva($iva);
        $pedido->setTotal($total);
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

