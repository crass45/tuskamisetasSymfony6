<?php

namespace App\EventSubscriber;

use App\Entity\Factura;
use App\Repository\FacturaRepository;
use App\Service\VerifactuService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class VerifactuSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private VerifactuService $verifactuService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private bool $isEnabled = false
    ) {}

    public function getSubscribedEvents(): array
    {
        return [Events::postPersist];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        if(!$this->isEnabled){
            return;
        }
        $entity = $args->getObject();

        // Solo actuar si es una nueva Factura y aún no tiene hash
        if (!$entity instanceof Factura || $entity->getVerifactuHash() !== null) {
            return;
        }

        try {
            // 1. Buscar el hash de la factura anterior para poder encadenar.
            /** @var FacturaRepository $facturaRepo */
            $facturaRepo = $this->em->getRepository(Factura::class);
//            HAY QUE VER COMO OBTENER LOS DATOS DE LA FACTURA BIEN SEA NORMAL O RECTIFICATIVA
            $previousFactura = $facturaRepo->findLastVerifactuRecordData();

            // 2. Crear el objeto de registro llamando al servicio correcto
            $record = $this->verifactuService->createRegistrationRecord($entity, $previousFactura);

            // 3. Generamos la URL para el QR a partir del registro
            $qrUrl = $this->verifactuService->getQrContent($record);


            // 4. Guardamos tanto el hash como la imagen del QR en la entidad Factura
            $entity->setVerifactuHash($record->hash);
            $entity->setVerifactuQr($qrUrl);

            // 6. Persistimos estos nuevos datos en la base de datos
            $this->em->flush();

            // NOTA: En este punto, el requisito mínimo de SIF (guardar el hash) está cumplido.
            // Opcionalmente, aquí podrías guardar el XML del registro si lo necesitaras:
            // file_put_contents('var/verifactu/' . $record->invoiceId->invoiceNumber . '.xml', $record->toXml());

        } catch (\Exception $e) {
            $this->logger->critical(
                'Error al generar registro VeriFactu para la factura ' . $entity->getNombre(),
                [
                    'invoice_id' => $entity->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString() // Añadimos más detalle para depurar
                ]
            );
        }
    }
}

