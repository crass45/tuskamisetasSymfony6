<?php
// src/Controller/Admin/PedidoCRUDController.php

namespace App\Controller\Admin;

use App\Entity\Factura;
use App\Entity\Pedido;
use App\Service\GoogleAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPQRCode\QRcode;

final class PedidoCRUDController extends CRUDController
{
    // Symfony inyectará los servicios que necesites aquí
    public function __construct(
        private Pdf $snappy,
        private GoogleAnalyticsService $googleAnalyticsService,
        private EntityManagerInterface $em
    ) {
    }

    // A continuación, el esqueleto para todas tus acciones personalizadas.
    // Deberás mover la lógica que tenías en tu controlador antiguo a estos métodos.

    /**
     * Muestra el presupuesto en formato PDF.
     */
    public function showPDFAction(Request $request): Response
    {
        $pedido = $this->assertObjectExists($request, true);
        $this->admin->checkAccess('show', $pedido);

        $filename = 'Presupuesto - ' . $pedido;
        $html = $this->renderView('plantilla_pdf/presupuesto.html.twig', ['pedido' => $pedido]);

        // NOTA: Deberás migrar tu plantilla de footer si la necesitas.
        // $this->snappy->setOption('footer-html', $this->renderView('plantilla_pdf/footer.html.twig'));

        return new Response(
            $this->snappy->getOutputFromHtml($html),
            200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"']
        );
    }

    /**
     * NUEVA ACCIÓN: Muestra la orden de pedido en formato PDF, incluyendo un código QR.
     */
    public function showOrdenPedidoAction(Request $request): Response
    {
        $pedido = $this->assertObjectExists($request, true);
        $this->admin->checkAccess('show', $pedido);

        $filename = 'Orden de Pedido - ' . $pedido;

        // --- Lógica para generar el Código QR ---
        $urlToEncode = $pedido->getMontaje();
        $outputPngFile = sys_get_temp_dir() . '/qr_' . $pedido->getId() . '.png'; // Se guarda en un directorio temporal

        if ($urlToEncode) {
            QRcode::png($urlToEncode, $outputPngFile, 'H', 8, 4);
        } else {
            // Manejar el caso de que no haya URL de montaje (opcional)
            $outputPngFile = null;
        }

        $html = $this->renderView('plantilla_pdf/ordenPedido.html.twig', [
            'pedido' => $pedido,
            'qrCodeFile' => $outputPngFile
        ]);

        $pdfContent = $this->snappy->getOutputFromHtml($html);

        // Se borra el fichero temporal del QR después de generar el PDF
        if ($outputPngFile && file_exists($outputPngFile)) {
            unlink($outputPngFile);
        }

        return new Response(
            $pdfContent,
            200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"']
        );
    }

    /**
     * Muestra la factura proforma en formato PDF.
     */
    public function showProformaAction(Request $request): Response
    {
        $pedido = $this->assertObjectExists($request, true);
        $this->admin->checkAccess('show', $pedido);

        $filename = 'Factura Proforma - ' . $pedido;
        $html = $this->renderView('plantilla_pdf/proforma.html.twig', ['pedido' => $pedido]);

        return new Response(
            $this->snappy->getOutputFromHtml($html),
            200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"']
        );
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


    /**
     * Crea una factura para el pedido si no existe, y la muestra en PDF.
     */
    public function facturarAction(Request $request): Response
    {
        $pedido = $this->assertObjectExists($request, true);
        $this->admin->checkAccess('edit', $pedido);

        if ($pedido->getFactura()) {
            return $this->showFacturaAction($request);
        }

        $fecha = new \DateTime();
        $fiscalYear = (int)$fecha->format('y');

        // Lógica para obtener el siguiente número de factura
        $ultimoNumero = $this->em->getRepository(Factura::class)->findLastNumberByYear($fiscalYear);
        $numeroFactura = $ultimoNumero + 1;

        $factura = new Factura();
        $factura = $factura->createFromPedido($pedido, $fiscalYear, $numeroFactura);

        $this->em->persist($factura);
        $pedido->setFactura($factura);
        $this->em->flush();

        // Y finalmente, notificas a Google
        $this->googleAnalyticsService->sendPurchaseEvent($pedido);

        $this->addFlash('sonata_flash_success', 'Factura creada y evento de compra enviado a Google Analytics.');

        return $this->showFacturaAction($request);
    }


    /**
     * Muestra la factura existente de un pedido en PDF.
     */
    public function showFacturaAction(Request $request): Response
    {
        $pedido = $this->assertObjectExists($request, true);
        $this->admin->checkAccess('show', $pedido);

        if (!$pedido->getFactura()) {
            $this->addFlash('sonata_flash_error', 'Este pedido no tiene una factura asociada.');
            return new RedirectResponse($this->admin->generateUrl('edit', ['id' => $pedido->getId()]));
        }

        $filename = str_replace('/', '_', 'Factura - ' . $pedido->getFactura());
        $html = $this->renderView('plantilla_pdf/factura.html.twig', ['pedido' => $pedido]);

        return new Response(
            $this->snappy->getOutputFromHtml($html),
            200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"']
        );
    }

    /**
     * Recalcula los totales del pedido.
     */
    public function recalcularAction(Request $request): Response
    {
        $pedido = $this->assertObjectExists($request, true);
        $this->admin->checkAccess('edit', $pedido);

        // La lógica de recálculo debería estar en un servicio, pero la migramos aquí directamente por ahora.
        $globals = $this->twig->getGlobals();
        $ivaGeneral = $globals['empresa']->getIvaGeneral() / 100;
        $subtotal = 0;
        foreach ($pedido->getPedidoHasLineas() as $lineaPedido) {
            $subtotal += ($lineaPedido->getPrecio() * $lineaPedido->getCantidad());
        }

        $gastosEnvio = $pedido->getEnvio();
        $pedidoExpres = $pedido->isPedidoExpres() ? $globals['empresa']->getPrecioServicioExpres() : 0;

        $baseImponible = $subtotal + $pedidoExpres + $gastosEnvio;
        $iva = ($pedido->getIdUsuario()?->getIntracomunitario()) ? 0 : ($baseImponible * $ivaGeneral);

        $pedido->setSubtotal($subtotal);
        $pedido->setIva($iva);
        $pedido->setTotal($baseImponible + $iva); // Asumiendo que no hay recargo de equivalencia por ahora

        $this->em->flush();

        $this->addFlash('sonata_flash_success', 'Los totales del pedido han sido recalculados.');
        return new RedirectResponse($this->admin->generateUrl('edit', ['id' => $pedido->getId()]));
    }

    public function reloadEnvioAction(Request $request): Response
    {
        // TODO: Tu lógica para la acción 'reloadEnvio'.
        return new Response('Recargando envío...');
    }
}