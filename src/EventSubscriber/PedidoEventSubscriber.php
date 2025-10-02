<?php

namespace App\EventSubscriber;

use App\Entity\Pedido;
use App\Service\PedidoMailerService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class PedidoEventSubscriber implements EventSubscriberInterface
{
    private array $originalStates = [];

    public function __construct(private PedidoMailerService $mailerService)
    {
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