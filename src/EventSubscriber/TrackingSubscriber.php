<?php
// src/EventSubscriber/TrackingSubscriber.php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TrackingSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        // Lista de parámetros de seguimiento de anuncios que queremos guardar
        $trackingParams = ['gclid', 'gbraid', 'wbraid'];

        foreach ($trackingParams as $param) {
            if ($request->query->has($param)) {
                $session->set($param, $request->query->get($param));
            }
        }
    }
}