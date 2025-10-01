<?php
// src/Controller/Admin/PedidoCRUDController.php

namespace App\Controller\Admin;

use App\Entity\Factura;
use App\Entity\Pedido;
use App\Model\Carrito;
use App\Model\Presupuesto;
use App\Model\PresupuestoProducto;
use App\Model\PresupuestoTrabajo;
use App\Service\GoogleAnalyticsService;
use App\Service\MrwApiService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPQRCode\QRcode;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class PedidoCRUDController extends CRUDController
{
    // Symfony inyectará los servicios que necesites aquí
    public function __construct(
        private Pdf $snappy,
        private GoogleAnalyticsService $googleAnalyticsService,
        private EntityManagerInterface $em,
        private MrwApiService $mrwApiService // Se inyecta el servicio de MRW
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

    /**
     * NUEVA ACCIÓN: Carga un pedido existente en el carrito del frontend para su edición.
     */
    public function editarPedidoAction(Request $request, SessionInterface $session): Response
    {
        $pedido = $this->assertObjectExists($request, true);
        $this->admin->checkAccess('edit', $pedido);

        $carrito = new Carrito();
        $carrito->setObservaciones($pedido->getObservaciones());
        $carrito->setServicioExpres($pedido->getPedidoExpres());
//        $carrito->setRecogerTienda($pedido->getRecogerEnTienda());

        // Agrupamos las líneas por personalización para reconstruir los 'presupuestos'
        $lineasAgrupadas = [];
        foreach ($pedido->getLineas() as $linea) {
            $key = $linea->getPersonalizacion() ?? 'sin-personalizacion';
            $lineasAgrupadas[$key][] = $linea;
        }

        foreach ($lineasAgrupadas as $grupo) {
            $presupuesto = new Presupuesto();
            // Añadimos los trabajos (serán los mismos para todo el grupo)
            if (isset($grupo[0])) {
                foreach ($grupo[0]->getPersonalizaciones() as $pers) {
                    $presupuestoTrabajo = new PresupuestoTrabajo();
                    $presupuestoTrabajo->fromPedidoLineaHasTrabajo($pers);
                    $presupuesto->addTrabajo($presupuestoTrabajo);
                }
            }
            // Añadimos los productos
            foreach ($grupo as $linea) {
                $presupuestoProducto = new PresupuestoProducto();
                $presupuestoProducto->fromPedidoLinea($linea);
                $presupuesto->addProducto($presupuestoProducto, $pedido->getContacto()->getUsuario());
            }
            $carrito->addItem($presupuesto);
        }

        // Guardamos el carrito y el ID del pedido en la sesión
        $session->set('carrito', $carrito);
        $session->set('editing_order_id', $pedido->getId());

        $this->addFlash('sonata_flash_info', 'Estás editando el pedido ' . $pedido . '. Los cambios se guardarán sobre el pedido original.');

        // Redirigimos al carrito del frontend
        return new RedirectResponse($this->generateUrl('app_cart_show'));
    }

    /**
     * NUEVA ACCIÓN: Documenta un envío con MRW.
     */
    public function documentarEnvioMrwAction(Request $request, int $bultos, string $servicio): RedirectResponse
    {
        $pedido = $this->assertObjectExists($request, true);
        $this->admin->checkAccess('edit', $pedido);

        if ($pedido->getSeguimientoEnvio()) {
            $this->addFlash('sonata_flash_error', 'Este envío ya ha sido documentado anteriormente.');
            return new RedirectResponse($this->admin->generateUrl('edit', ['id' => $pedido->getId()]));
        }

        // Llama al servicio para gestionar la lógica de la API
        $urlSeguimiento = $this->mrwApiService->documentarEnvio($pedido,$bultos, $servicio);

        if ($urlSeguimiento) {
            $this->addFlash('sonata_flash_success', 'Envío documentado correctamente con MRW. Seguimiento: ' . $urlSeguimiento);
        } else {
            $this->addFlash('sonata_flash_error', 'Ha ocurrido un error al documentar el envío con MRW.');
        }

        return new RedirectResponse($this->admin->generateUrl('edit', ['id' => $pedido->getId()]));
    }

    /**
     * NUEVA ACCIÓN: Muestra la etiqueta de envío correspondiente a la agencia.
     */
    public function verEtiquetasAction(Request $request): Response
    {
        $pedido = $this->assertObjectExists($request, true);
        $this->admin->checkAccess('show', $pedido);

        $urlSeguimiento = $pedido->getSeguimientoEnvio();

        if (!$urlSeguimiento) {
            $this->addFlash('sonata_flash_error', 'Este pedido no tiene un envío documentado.');
            return new RedirectResponse($this->admin->generateUrl('edit', ['id' => $pedido->getId()]));
        }

        if (str_contains($urlSeguimiento, 'mrw.es')) {
            // Es un envío de MRW
            parse_str(parse_url($urlSeguimiento, PHP_URL_QUERY), $queryParams);
            $numeroEnvio = $queryParams['enviament'] ?? null;

            if (!$numeroEnvio) {
                $this->addFlash('sonata_flash_error', 'No se pudo extraer el número de envío de la URL de seguimiento de MRW.');
                return new RedirectResponse($this->admin->generateUrl('edit', ['id' => $pedido->getId()]));
            }

            $pdfData = $this->mrwApiService->getEtiqueta($numeroEnvio);

            if ($pdfData) {
                return new Response(
                    $pdfData,
                    200,
                    ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="etiqueta_mrw.pdf"']
                );
            } else {
                $this->addFlash('sonata_flash_error', 'No se pudo obtener la etiqueta de MRW.');
                return new RedirectResponse($this->admin->generateUrl('edit', ['id' => $pedido->getId()]));
            }
        }

        // Aquí iría la lógica para las otras agencias (Nacex, Envialia, etc.)

        $this->addFlash('sonata_flash_warning', 'No se ha implementado la visualización de etiquetas para esta agencia de transporte.');
        return new RedirectResponse($this->admin->generateUrl('edit', ['id' => $pedido->getId()]));
    }
}