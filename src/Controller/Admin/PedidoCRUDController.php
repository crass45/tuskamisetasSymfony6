<?php
// src/Controller/Admin/PedidoCRUDController.php

namespace App\Controller\Admin;

use App\Entity\Pedido;
use App\Service\GoogleAnalyticsService;
use Knp\Snappy\Pdf;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PedidoCRUDController extends CRUDController
{
    // Symfony inyectará los servicios que necesites aquí
    public function __construct(
        private Pdf $snappy,
        private GoogleAnalyticsService $googleAnalyticsService
    ) {
    }

    // A continuación, el esqueleto para todas tus acciones personalizadas.
    // Deberás mover la lógica que tenías en tu controlador antiguo a estos métodos.

    public function showPDFAction(Request $request): Response
    {
        $pedido = $this->admin->getSubject();
        // TODO: Tu lógica para generar el PDF del presupuesto/pedido.
        return new Response(sprintf('PDF para Pedido %s', $pedido->getNombre()));
    }

    public function showOrdenPedidoAction(Request $request): Response
    {
        $pedido = $this->admin->getSubject();
        // TODO: Tu lógica para generar la orden de pedido en PDF.
        return new Response(sprintf('Orden de Pedido para %s', $pedido->getNombre()));
    }

    public function showProformaAction(Request $request): Response
    {
        $pedido = $this->admin->getSubject();
        // TODO: Tu lógica para generar la proforma en PDF.
        return new Response(sprintf('Proforma para %s', $pedido->getNombre()));
    }

    public function documentarEnvioNacexAction(Request $request, string $agencia, int $bultos, string $servicio): Response
    {
        $pedido = $this->admin->getSubject();
        // TODO: Tu lógica para conectar con la API de Nacex/Enviália.
        return new Response(sprintf('Documentando envío para %s con %s', $pedido->getNombre(), $agencia));
    }

    public function verEtiquetasAction(Request $request): Response
    {
        $pedido = $this->admin->getSubject();
        // TODO: Tu lógica para generar las etiquetas de envío en PDF.
        return new Response(sprintf('Etiquetas para %s', $pedido->getNombre()));
    }

    public function editarPedidoAction(Request $request): Response
    {
        $pedido = $this->admin->getSubject();
        // TODO: Tu lógica para la vista de edición especial del pedido.
        return new Response(sprintf('Editando pedido especial %s', $pedido->getNombre()));
    }

    public function facturarAction(Request $request): Response
    {
        $pedido = $this->admin->getSubject();
        // TODO: Tu lógica para crear la Factura a partir de este Pedido.

        // Y finalmente, notificas a Google
        $this->googleAnalyticsService->sendPurchaseEvent($pedido);

        $this->addFlash('sonata_flash_success', 'Factura creada y evento de compra enviado a Google Analytics.');


        return $this->redirectToRoute('admin_app_pedido_edit', ['id' => $pedido->getId()]); // Ejemplo de redirección
    }

    public function showFacturaAction(Request $request): Response
    {
        $pedido = $this->admin->getSubject();
        $factura = $pedido->getFactura();
        // TODO: Tu lógica para mostrar la factura asociada.
        return new Response(sprintf('Mostrando factura %s', $factura?->getNombre()));
    }

    public function recalcularAction(Request $request): Response
    {
        $pedido = $this->admin->getSubject();
        // TODO: Tu lógica para recalcular los totales del pedido.
        return $this->redirectToRoute('admin_app_pedido_edit', ['id' => $pedido->getId()]);
    }

    public function reloadEnvioAction(Request $request): Response
    {
        // TODO: Tu lógica para la acción 'reloadEnvio'.
        return new Response('Recargando envío...');
    }
}