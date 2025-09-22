<?php
// src/Controller/PaymentController.php

namespace App\Controller;

use App\Entity\Estado;
use App\Entity\Pedido;
use App\Service\OrderService;
use App\Service\RedsysApiService;
use App\Service\FechaEntregaService; // Importamos el servicio de fechas
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RedsysApiService $redsysApi,
        private FechaEntregaService $fechaEntregaService,
        private OrderService $orderService, // Se inyecta el OrderService
        private LoggerInterface $logger    // Se inyecta el Logger
    ) {
    }

    /**
     * La página de inicio del pago se mantiene igual.
     */
    #[Route('/{_locale}/payment/start/{id}', name: 'app_payment_start', requirements: ['_locale' => 'es|en|fr'])]
    public function startPaymentAction(Pedido $pedido): Response
    {
        // Validaciones (el pedido existe, no está pagado, etc.)
        if ($pedido->isPagado()) {
            $this->addFlash('warning', 'Este pedido ya ha sido pagado.');
            return $this->redirectToRoute('app_home');
        }

        // Usamos nuestro servicio para generar los datos del formulario de Redsys
        $redsysFormData = $this->redsysApi->generateRedsysFormData($pedido);

        // Usamos el servicio para calcular las fechas de entrega
        $deliveryDates = $this->fechaEntregaService->getDeliveryDatesForModel($pedido->getLineas()->first()->getProducto()->getModelo());

        return $this->render('payment/start.html.twig', [
            'pedido' => $pedido,
            'redsys_data' => $redsysFormData,
            'dias1' => $deliveryDates['fechaEntregaSinImprimir']->format('d/M'), // Pasamos las fechas a la plantilla
            'dias2' => $deliveryDates['fechaEntregaSinImprimir2']->format('d/M'),
        ]);
    }

    /**
     * Gestiona la notificación 'server-to-server' que envía el banco.
     * Reemplaza a 'pedidoPagoConfirmadoBancoAction'.
     */
    /**
     * URL de Notificación: Ahora usa la ruta y el nombre de tu proyecto original.
     */
    #[Route('/{_locale}/pago_confirmado_banco', name: 'app_payment_notification', methods: ['POST'], requirements: ['_locale' => 'es|en|fr'])]
    public function notificationAction(Request $request): Response
    {
        $this->logger->info('[Redsys Notification] Recibida notificación POST.');

        $signatureVersion = $request->request->get('Ds_SignatureVersion');
        $parameters = $request->request->get('Ds_MerchantParameters');
        $receivedSignature = $request->request->get('Ds_Signature');

        if (!$parameters || !$receivedSignature) {
            $this->logger->error('[Redsys Notification] Faltan parámetros en la petición.');
            return new Response('Error: Faltan parámetros', 400);
        }

        // 1. Verificar la firma
        $key = $this->getParameter('redsys.secret_key'); // Asumiendo que la clave está en services.yaml
        $calculatedSignature = $this->redsysApi->createMerchantSignatureNotif($key, $parameters);

        if ($calculatedSignature !== $receivedSignature) {
            $this->logger->error('[Redsys Notification] La firma no coincide. Recibida: ' . $receivedSignature . ' | Calculada: ' . $calculatedSignature);
            return new Response('Error: Firma no válida', 400);
        }

        // 2. Decodificar los parámetros y comprobar el resultado
        $decodedParams = $this->redsysApi->decodeMerchantParameters($parameters);
        $responseCode = (int)($decodedParams['Ds_Response'] ?? -1);

        $this->logger->info('[Redsys Notification] Firma correcta. Código de respuesta: ' . $responseCode);

        if ($responseCode >= 0 && $responseCode <= 99) {
            // 3. El pago es correcto. Procesar el pedido.
            $orderCode = $decodedParams['Ds_Order'] ?? null;
            $pedido = $this->em->getRepository(Pedido::class)->findOneBy(['codigoSermepa' => $orderCode]);

            if ($pedido) {
                $importePagado = (float)($decodedParams['Ds_Amount'] ?? 0) / 100;
                $pedido->setCantidadPagada($pedido->getCantidadPagada() + $importePagado);

                // Actualizar estado a "Pagado" (asumiendo que el ID 9 es 'Pagado')
                $estadoPagado = $this->em->getRepository(Estado::class)->find(9);
                if ($estadoPagado) {
                    $pedido->setEstado($estadoPagado);
                }

                // Recalcular fecha de entrega (esta lógica debería estar en el FechaEntregaService)
                // $nuevaFecha = $this->fechaEntregaService->recalculateForPaidOrder($pedido);
                // $pedido->setFechaEntrega($nuevaFecha);

                $this->em->flush();

                // Usar el OrderService para enviar los correos de confirmación de pago
                $this->orderService->sendPaymentSuccessEmails($pedido);

                $this->logger->info('[Redsys Notification] Pedido ' . $pedido->getId() . ' actualizado correctamente.');
            } else {
                $this->logger->error('[Redsys Notification] No se encontró el pedido con el código: ' . $orderCode);
            }
        }

        // Siempre se devuelve OK para que Redsys sepa que hemos recibido la notificación
        return new Response('OK', 200);
    }

    /**
     * URL de Éxito: Ahora usa la ruta y el nombre de tu proyecto original.
     */
    #[Route('/{_locale}/compraOK', name: 'app_payment_success', requirements: ['_locale' => 'es|en|fr'])]
    public function successAction(): Response
    {
        // Reemplaza a 'compraOKAction'
        return $this->render('payment/success.html.twig');
    }

    /**
     * URL de Error: Ahora usa la ruta y el nombre de tu proyecto original.
     */
    #[Route('/{_locale}/compraKO', name: 'app_payment_error', requirements: ['_locale' => 'es|en|fr'])]
    public function errorAction(): Response
    {
        // Reemplaza a 'compraKOAction'
        return $this->render('payment/error.html.twig');
    }
}

