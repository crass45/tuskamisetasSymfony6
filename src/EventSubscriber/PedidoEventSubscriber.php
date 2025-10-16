<?php

namespace App\EventSubscriber;

use App\Entity\Empresa;
use App\Entity\Pedido;
use App\Service\PedidoMailerService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class PedidoEventSubscriber implements EventSubscriberInterface
{
    private array $originalStates = [];
    private EntityManagerInterface $em;

    public function __construct(private PedidoMailerService $mailerService,
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

        // Guardamos el estado original antes de que se actualice
        $entityManager = $args->getObjectManager();
        $uow = $entityManager->getUnitOfWork();
        $originalData = $uow->getOriginalEntityData($entity);

        $this->originalStates[$entity->getId()] = $originalData['estado'] ?? null;

        // --- INICIO DE LA NUEVA LÓGICA DE RECÁLCULO DE TOTALES ---
        $this->recalculateTotals($entity);

        // Forzamos a Doctrine a recalcular los cambios de la entidad
        $meta = $entityManager->getClassMetadata(get_class($entity));
        $uow->recomputeSingleEntityChangeSet($meta, $entity);
        // --- FIN DE LA NUEVA LÓGICA ---
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
//YA NO TENEMOS EN CUENTA EL ESTADO ANTERIOR NI NADA. SIMPLEMENTE ENVIAMOS EL MAIL SI SE MARCA EL CHECK
//        $originalState = $this->originalStates[$entity->getId()] ?? null;
//        $newState = $entity->getEstado();

        // Comprobamos si el estado ha cambiado, si el nuevo estado es diferente Y si el checkbox está marcado
        if ($entity->isEnviaMail() /*&& $newState && $newState !== $originalState*/) {
            $this->mailerService->sendEmailForStatus($entity);
        }

        // Limpiamos el estado guardado
        unset($this->originalStates[$entity->getId()]);
    }
}