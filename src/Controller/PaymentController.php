<?php
// src/Controller/PaymentController.php

namespace App\Controller;

use App\Entity\Pedido;
use App\Service\RedsysApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/payment', requirements: ['_locale' => 'es|en|fr'])]
class PaymentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RedsysApiService $redsysApi
    ) {
    }

    /**
     * Muestra la página con el formulario que redirige a Redsys.
     * Reemplaza a 'pedidoPagoAction' y 'pedidoPagoDirecto'.
     */
    #[Route('/start/{id}', name: 'app_payment_start')]
    public function startPaymentAction(Pedido $pedido): Response
    {
        // Validaciones (el pedido existe, no está pagado, etc.)
        if (!$pedido || $pedido->isPagado()) {
            $this->addFlash('error', 'Este pedido no se puede pagar.');
            return $this->redirectToRoute('app_home');
        }

        // Usamos nuestro servicio para generar los datos del formulario de Redsys
        $redsysFormData = $this->redsysApi->generateRedsysFormData($pedido);

        return $this->render('payment/start.html.twig', [
            'pedido' => $pedido,
            'redsys_data' => $redsysFormData,
        ]);
    }

    /**
     * Gestiona la notificación 'server-to-server' que envía el banco.
     * Reemplaza a 'pedidoPagoConfirmadoBancoAction'.
     */
    #[Route('/notification', name: 'app_payment_notification', methods: ['POST'])]
    public function notificationAction(Request $request): Response
    {
        // Aquí iría tu lógica para decodificar los parámetros de Redsys,
        // verificar la firma, encontrar el pedido por su 'Ds_Order',
        // y si el pago es correcto, actualizar el estado del pedido y enviar correos.

        // Es muy importante devolver una respuesta simple para que Redsys sepa que hemos recibido la notificación.
        return new Response('OK');
    }

    #[Route('/success', name: 'app_payment_success')]
    public function successAction(): Response
    {
        return $this->render('payment/success.html.twig');
    }

    #[Route('/error', name: 'app_payment_error')]
    public function errorAction(): Response
    {
        return $this->render('payment/error.html.twig');
    }
}
