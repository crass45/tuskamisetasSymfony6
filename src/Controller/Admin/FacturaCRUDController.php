<?php

namespace App\Controller\Admin;

use App\Admin\FacturaRectificativaAdmin;
use App\Entity\Empresa;
use App\Entity\Factura;
use App\Entity\FacturaRectificativa;
use App\Entity\FacturaRectificativaLinea;
use App\Repository\FacturaRectificativaRepository;
use App\Repository\FacturaRepository;
use App\Service\VerifactuService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use PHPQRCode\QRcode;
use Knp\Snappy\Pdf;

class FacturaCRUDController extends CRUDController
{

    public function __construct(private EntityManagerInterface         $em,
                                private Pdf                            $snappy,
                                private FacturaRectificativaRepository $rectificativaRepo,
                                private FacturaRepository              $facturaRepo, // <-- Inyección añadida
                                private VerifactuService               $verifactuService, // <-- Inyección añadida
                                private LoggerInterface                $logger, // <-- Inyección añadida
                                private FacturaRectificativaAdmin      $rectificativaAdmin,
                                private readonly FacturaRectificativaAdmin $facturaRectificativaAdmin,
                                private bool $verifactuEnabled = false
    )
    {
    }


    public
    function showFacturaFacturaAction(Request $request): Response
    {
        $pedido = $this->assertObjectExists($request, true)->getPedido();
        $this->admin->checkAccess('show', $pedido);

        if (!$pedido->getFactura()) {
            $this->addFlash('sonata_flash_error', 'Este pedido no tiene una factura asociada.');
            return new RedirectResponse($this->admin->generateUrl('edit', ['id' => $pedido->getId()]));
        }

        $urlToEncode = $pedido->getFactura()->getVerifactuQr();
        $outputPngFile = sys_get_temp_dir() . '/qr_' . $pedido->getId() . '.png'; // Se guarda en un directorio temporal

        $qrCodeDataUri = null;
        if ($urlToEncode) {
            QRcode::png($urlToEncode, $outputPngFile, 'H', 8, 4);
            if (file_exists($outputPngFile)) {
                // 3. Lee el contenido binario del archivo
                $fileContent = file_get_contents($outputPngFile);

                // 4. Codifica en Base64 y crea el Data URI
                $qrCodeDataUri = 'data:image/png;base64,' . base64_encode($fileContent);

                // 5. (Opcional) Borra el archivo temporal, ya no lo necesitas
                unlink($outputPngFile);
            }
        } else {
            // Manejar el caso de que no haya URL de qr (opcional)
            $outputPngFile = null;
        }

        $filename = str_replace('/', '_', 'Factura - ' . $pedido->getFactura());
        $html = $this->renderView('plantilla_pdf/factura.html.twig', ['pedido' => $pedido, 'qrCodeFile' => $qrCodeDataUri]);
        $this->snappy->setOption('footer-html', $this->renderView('plantilla_pdf/footer.html.twig'));

        return new Response(
            $this->snappy->getOutputFromHtml($html),
            200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"']
        );
    }

// --- NUEVO MÉTODO PARA GENERAR EL PDF ---
    public
    function showFacturaRectificativaAction(Request $request): Response
    {
        $id = $request->get('id');

        $factRectifi = $this->em->getRepository(FacturaRectificativa::class)->find($id);


        if (!$factRectifi) {
            throw new NotFoundHttpException(sprintf('No se encuentra la factura rectificativa con ID: %s', $id));
        }

        return $this->muestraFacturaRectificativa($factRectifi);
    }


    private
    function muestraFacturaRectificativa(FacturaRectificativa $rectificativa): Response
    {
        // Obtenemos la información de la empresa
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy([]);

        $urlToEncode = $rectificativa->getVerifactuQr();
        $outputPngFile = sys_get_temp_dir() . '/qr_' . $rectificativa->getVerifactuHash() . '.png'; // Se guarda en un directorio temporal

        $qrCodeDataUri = null;
        if ($urlToEncode) {
            QRcode::png($urlToEncode, $outputPngFile, 'H', 8, 4);
            if (file_exists($outputPngFile)) {
                // 3. Lee el contenido binario del archivo
                $fileContent = file_get_contents($outputPngFile);

                // 4. Codifica en Base64 y crea el Data URI
                $qrCodeDataUri = 'data:image/png;base64,' . base64_encode($fileContent);

                // 5. (Opcional) Borra el archivo temporal, ya no lo necesitas
                unlink($outputPngFile);
            }
        } else {
            // Manejar el caso de que no haya URL de qr (opcional)
            $outputPngFile = null;
        }


        $filename = str_replace('/', '_', 'Factura_Rectificativa_' . $rectificativa->getNumeroFactura());
        $html = $this->renderView('plantilla_pdf/factura_rectificativa.html.twig', [
            'rectificativa' => $rectificativa,
            'empresa' => $empresa, // <-- Pasamos la empresa como parámetro
            'qrCodeFile' => $qrCodeDataUri
        ]);
        $this->snappy->setOption('footer-html', $this->renderView('plantilla_pdf/footer.html.twig'));

        return new Response(
            $this->snappy->getOutputFromHtml($html),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"'
            ]
        );
    }

    /**
     * Esta acción crea una factura rectificativa a partir de una factura original.
     */
    public
    function createRectificativaAction(Request $request): Response
    {
        $id = $request->get($this->admin->getIdParameter());
        /** @var Factura|null $facturaOriginal */
        $facturaOriginal = $this->admin->getObject($id);

        // 1. Validar que la factura original existe y es válida
        if (!$facturaOriginal || !$facturaOriginal->getPedido()) {
            throw new NotFoundHttpException(sprintf('No se encuentra la factura con ID: %s o no tiene un pedido asociado.', $id));
        }
        if ($facturaOriginal->getFacturaRectificativa()) {
            $this->addFlash('sonata_flash_error', 'Esta factura ya tiene una rectificativa asociada.');
            return $this->redirectToList();
        }

        // 2. Crear el objeto de la factura rectificativa
        $rectificativa = new FacturaRectificativa();
        $rectificativa->setFacturaPadre($facturaOriginal);

        // --- GUARDAMOS TODOS LOS DATOS DE LA FACTURA ---
        $rectificativa->setMotivo('Abono por devolución de mercancía.'); // Motivo por defecto
        $rectificativa->setFacturaPadre($facturaOriginal);
        $rectificativa->setCif($facturaOriginal->getCif());
        $rectificativa->setRazonSocial($facturaOriginal->getRazonSocial());
        $rectificativa->setCp($facturaOriginal->getCp());
        $rectificativa->setPoblacion($facturaOriginal->getPoblacion());
        $rectificativa->setDireccion($facturaOriginal->getDireccion());
        $rectificativa->setProvincia($facturaOriginal->getProvincia());
        $rectificativa->setPais($facturaOriginal->getPais());


        // 3. Copiar las líneas del pedido original, pero en negativo
        $pedidoOriginal = $facturaOriginal->getPedido();

        // Copiar líneas de producto
        foreach ($pedidoOriginal->getLineas() as $lineaOriginal) {
            $lineaRect = new FacturaRectificativaLinea();
            $lineaRect->setDescripcion(
                "(".$lineaOriginal->getProducto()->getReferencia().")--  ".$lineaOriginal->getProducto()
            );
            $lineaRect->setCantidad(-$lineaOriginal->getCantidad()); // Cantidad en negativo
            $lineaRect->setPrecio($lineaOriginal->getPrecio());
            $rectificativa->addLinea($lineaRect);
        }

        // Copiar líneas libres
        foreach ($pedidoOriginal->getLineasLibres() as $lineaLibreOriginal) {
            $lineaRect = new FacturaRectificativaLinea();
            $lineaRect->setDescripcion($lineaLibreOriginal->getDescripcion());
            $lineaRect->setCantidad(-$lineaLibreOriginal->getCantidad()); // Cantidad en negativo
            $lineaRect->setPrecio($lineaLibreOriginal->getPrecio());
            $rectificativa->addLinea($lineaRect);
        }

        // 4. Guardar en la base de datos
        $this->em->persist($rectificativa);
        $this->em->flush();

        $this->addFlash('sonata_flash_success', 'Factura rectificativa creada. Por favor, revise y confirme los detalles.');

        // 5. Redirigir a la página de EDICIÓN de la nueva factura rectificativa
        return new RedirectResponse($this->facturaRectificativaAdmin->generateUrl('edit', ['id' => $rectificativa->getId()]));
//        return $this->muestraFacturaRectificativa($rectificativa);
    }

    /**
     * Acción para generar el VeriFactu de la rectificativa (movida desde el otro controlador)
     */
    public function generateVerifactuAction(Request $request): Response
    {
        $id = $request->get($this->admin->getIdParameter());
        /** @var FacturaRectificativa|null $rectificativa */
        $rectificativa = $this->admin->getObject($id);

        if($rectificativa->getNumeroFactura()!=null){
            $this->addFlash('sonata_flash_error', "FACTURA YA NUMERADA (VERIFACTU DESACTIVADO)");
            return $this->redirectToList();
        }

        if (!$rectificativa) {
            throw new NotFoundHttpException("No se encuentra la factura rectificativa con ID: {$id}");
        }
        if ($rectificativa->getVerifactuHash()) {
            $this->addFlash('sonata_flash_warning', 'Esta factura rectificativa ya tiene un registro VeriFactu generado.');
            return $this->redirectToList();
        }

        try {
            // Buscamos el último número en la serie de rectificativas usando el repositorio
            $fiscalYear = (int)$rectificativa->getFecha()->format('y');
            $ultimoNumero = $this->rectificativaRepo->findLastNumberByYear($fiscalYear);
            $nuevoNumero = $ultimoNumero + 1;
//            Creamos el nuevo número de factura con la serie "R"
            $numeroFacturaRectificativa = "R" . $fiscalYear . "/" . sprintf('%05d', $nuevoNumero);
            $rectificativa->setNumeroFactura($numeroFacturaRectificativa);
            // --- FIN DE LA LÓGICA DE NUMERACIÓN ---

            if($this->verifactuEnabled) {
                // 1. Buscamos los DATOS COMPLETOS del último registro
                $previousRecordData = $this->facturaRepo->findLastVerifactuRecordData();

                // 2. Pasamos el array de datos al servicio
                $record = $this->verifactuService->createCreditNoteRecord($rectificativa, $previousRecordData);
                // --- FIN DE LA CORRECCIÓN ---

                // 3. Generamos el QR
                $qrUrl = $this->verifactuService->getQrContent($record);
//            $qrCodeContent = (new \QRCode($qrUrl))->render();

                // 4. Guardamos los datos en la entidad
                $rectificativa->setVerifactuHash($record->hash);
                $rectificativa->setVerifactuQr($qrUrl);
                $mensajeFlash = 'Factura rectificativa numerada y registro VeriFactu generado correctamente.';
            }else{
                $mensajeFlash = 'Factura rectificativa numerada correctamente (VeriFactu desactivado).';
            }

            // 5. Persistimos en la base de datos
            $this->em->flush();

            $this->addFlash('sonata_flash_success', $mensajeFlash);


        } catch (\Exception $e) {
            $this->addFlash('sonata_flash_error', 'Error al generar el registro VeriFactu: ' . $e->getMessage());
            // Añadimos el log de error que faltaba
            $this->logger->critical(
                'Error al generar VeriFactu manual para rectificativa ' . $rectificativa->getNumeroFactura(),
                ['id' => $rectificativa->getId(), 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
        }

        return $this->redirectToList();
    }

}

