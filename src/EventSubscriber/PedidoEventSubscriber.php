<?php

namespace App\EventSubscriber;

use App\Entity\Empresa;
use App\Entity\Pedido;
use App\Service\FechaEntregaService;
use App\Service\PedidoMailerService;
use App\Service\ShippingCalculatorService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class PedidoEventSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $em;


    public function __construct(private PedidoMailerService $mailerService,
                                private FechaEntregaService $fechaEntregaService,
                                private ShippingCalculatorService $shippingCalculator,
                                EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::postUpdate,
        ];
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Pedido) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $uow = $entityManager->getUnitOfWork();

        // La forma correcta y robusta: preguntamos a Doctrine por los cambios exactos
        $changeSet = $uow->getEntityChangeSet($entity);

        // Comprobamos si el campo 'recogidaEnTienda' ha cambiado
        if (isset($changeSet['recogerEnTienda'])) {
            $esRecogidaEnTienda = $changeSet['recogerEnTienda'][1]; // [1] es el valor NUEVO

            if ($esRecogidaEnTienda) {
                // Si se marca "Recoger en tienda", el envío es 0.
                $entity->setEnvio(0);
            } else {
                // Si se desmarca, recalculamos el envío.
                $costeEnvio = $this->shippingCalculator->calculateForPedido($entity);
                $entity->setEnvio($costeEnvio ?? 0);
            }
        }
        // --- FIN DE LA LÓGICA DE ENVÍOS ---

        // Comprobamos si el campo 'cantidadPagada' ha cambiado
        if (isset($changeSet['cantidadPagada'])) {
            $originalCantidadPagada = (float)($changeSet['cantidadPagada'][0] ?? 0.0); // Valor ANTIGUO
            $newCantidadPagada = (float)($changeSet['cantidadPagada'][1] ?? 0.0);      // Valor NUEVO

            if ($originalCantidadPagada <= 0 && $newCantidadPagada > 0) {
                $fechas = $this->fechaEntregaService->getFechasEntregaPedido($entity, new \DateTime());
                if ($fechas) {
                    $fechaSeleccionada = $entity->getPedidoExpres() ? $fechas['express'] : $fechas['min'];
                    if ($fechaSeleccionada instanceof \DateTimeInterface) {
                        $entity->setFechaEntrega($fechaSeleccionada);
                    }
                }
            }
            //ponemos la fecha de entrega anull para que no aparezca en los pedidos pendientes
            if($newCantidadPagada==0){
                $entity->setFechaEntrega(null);
            }
        }
        // Recalculamos los totales DESPUÉS de nuestra lógica
        $this->recalculateTotals($entity);


        // Forzamos a Doctrine a registrar TODOS los cambios (fecha y totales)
        $meta = $entityManager->getClassMetadata(get_class($entity));
        $uow->recomputeSingleEntityChangeSet($meta, $entity);
    }

    /**
     * ¡NUEVO MÉTODO! Recalcula los totales de un pedido basándose en sus propias líneas y datos.
     * No sobrescribe los precios unitarios, solo actualiza los totales.
     */
    private function recalculateTotals(Pedido $pedido): void
    {
        // 1. Calculamos el subtotal sumando los totales de cada línea de pedido.
        $subtotal = 0;
        foreach ($pedido->getLineas() as $linea) {
            // El total de la línea es precio * cantidad
            $subtotal += $linea->getPrecio() * $linea->getCantidad();
        }
        foreach ($pedido->getLineasLibres() as $lineaLibre) {
            $subtotal += $lineaLibre->getPrecio() * $lineaLibre->getCantidad();
        }

        // 2. Obtenemos el coste del envío y del servicio exprés directamente del pedido.
        $gastosEnvio = (float)$pedido->getEnvio();

        $empresa = $this->em->getRepository(Empresa::class)->find(1);
        $costeExpres = 0;
        if ($pedido->getPedidoExpres()) {
            if ($empresa) {
                $costeExpres = (float)$empresa->getPrecioServicioExpres();
            }
        }
        $pedido->setPrecioPedidoExpres($costeExpres);

        // 3. Calculamos la base imponible y el IVA.
        $baseImponible = $subtotal + $gastosEnvio + $costeExpres;

        // Obtenemos el porcentaje de IVA (asumimos que todas las líneas tienen el mismo)
        $ivaGeneral = $empresa->getIvaGeneral() / 100;
        $gastosEnvio = $pedido->getEnvio();


        $iva = $baseImponible * ($ivaGeneral);
        $recargoEquivalencia = 0;
        if ($pedido->getContacto()->isRecargoEquivalencia()) {
            $recargoEquivalenciaGeneral = $empresa->getRecargoEquivalencia() / 100;
            $recargoEquivalencia = ($baseImponible) * $recargoEquivalenciaGeneral;
        }
        if ($pedido->getContacto()->isIntracomunitario()) {
            $iva = 0;
        }

        $total = $baseImponible + $iva+$recargoEquivalencia;

        // 4. Asignamos los nuevos totales al pedido.
        $pedido->setRecargoEquivalencia($recargoEquivalencia);
        $pedido->setSubTotal(round($subtotal, 2));
        // No tocamos el envío, ya que puede ser modificado manualmente
        $pedido->setIva(round($iva, 2));
        $pedido->setTotal(round($total, 2));
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Pedido) {
            return;
        }

        // Comprobamos si el estado ha cambiado, si el nuevo estado es diferente Y si el checkbox está marcado
        if ($entity->isEnviaMail()) {
            $this->mailerService->sendEmailForStatus($entity);
        }

    }
}