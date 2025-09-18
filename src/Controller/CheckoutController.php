<?php
// src/Controller/CheckoutController.php

namespace App\Controller;

use App\Entity\Contacto;
use App\Entity\Direccion;
use App\Entity\Pedido;
use App\Form\Type\ContactoType;
use App\Form\Type\DireccionType;
use App\Model\Carrito;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/checkout', requirements: ['_locale' => 'es|en|fr'])]
class CheckoutController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrderService $orderService // El "cerebro" que creará el pedido
    ) {
    }

    /**
     * PASO 1: Inicia el proceso de checkout.
     * Reemplaza a 'carritoConfirmacionAction'.
     */
    #[Route('/start', name: 'app_checkout_start', methods: ['POST'])]
    public function startAction(Request $request, SessionInterface $session): Response
    {
        $carrito = $session->get('carrito');
        if (!$carrito instanceof Carrito || empty($carrito->getItems())) {
            return $this->redirectToRoute('app_cart_show', ['_locale' => $request->getLocale()]);
        }

        $carrito->setObservaciones($request->request->get('pedidoObservaciones', ''));
        $session->set('carrito', $carrito);

        // Si el usuario ya ha iniciado sesión, lo mandamos al paso de la dirección
        if ($this->getUser()) {
            return $this->redirectToRoute('app_checkout_address', ['_locale' => $request->getLocale()]);
        }

        // Si no, le mostramos la página para que inicie sesión o continúe como invitado
        return $this->render('checkout/login_guest.html.twig');
    }

    /**
     * PASO 2: Muestra la página para introducir/confirmar las direcciones.
     */
    #[Route('/address', name: 'app_checkout_address')]
    #[IsGranted('ROLE_USER')]
    public function addressAction(SessionInterface $session): Response
    {
        $contacto = $this->em->getRepository(Contacto::class)->findOneBy(['usuario' => $this->getUser()]);
        if (!$contacto) {
            $contacto = new Contacto();
            $contacto->setUsuario($this->getUser());
            $contacto->setDireccionFacturacion(new Direccion());
        }

        $formContacto = $this->createForm(ContactoType::class, $contacto);

        // CORRECCIÓN: Se usa la clase DireccionType que ya fusionamos.
//        $formEnvio = $this->createForm(DireccionType::class);
        $formEnvio = $this->createForm(DireccionType::class, null, [
            'is_shipping_address' => true,
        ]);

        return $this->render('checkout/address.html.twig', [
            'contacto' => $contacto,
            'formContacto' => $formContacto->createView(),
            'formEnvio' => $formEnvio->createView(),
            'carrito' => $session->get('carrito')
        ]);
    }

    /**
     * NUEVA ACCIÓN: Se llama por AJAX para cargar un formulario de dirección existente.
     */
    #[Route('/load-address/{id}', name: 'app_checkout_load_address', defaults: ['id' => null])]
    #[IsGranted('ROLE_USER')]
    public function loadAddressAction(?Direccion $direccion = null): Response
    {
        if (!$direccion) {
            $direccion = new Direccion();
        }

        // Comprobamos que la dirección pertenece al usuario actual por seguridad
        if ($direccion->getId() && $direccion->getIdContacto()->getUsuario() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Esta dirección no le pertenece.');
        }

//        $formEnvio = $this->createForm(DireccionType::class, $direccion);
        // CORRECCIÓN: Le pasamos la misma opción al formulario que se carga por AJAX
        $formEnvio = $this->createForm(DireccionType::class, $direccion, [
            'is_shipping_address' => true,
        ]);

        // Renderizamos solo el parcial del formulario
        return $this->render('checkout/partials/_address_form.html.twig', [
            'form' => $formEnvio->createView()
        ]);
    }

    /**
     * PASO 3: Procesa los datos de dirección y crea el pedido.
     * Reemplaza a la parte de procesamiento de 'pedidoConfirmacionAction'.
     */
    #[Route('/process', name: 'app_checkout_process', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function processAction(Request $request, SessionInterface $session): Response
    {
        $carrito = $session->get('carrito');
        $contacto = $this->em->getRepository(Contacto::class)->findOneBy(['usuario' => $this->getUser()]);

        // Aquí iría la lógica para validar los formularios de Contacto y Dirección
        // y para guardar la nueva dirección si es necesario.

        // Usamos nuestro servicio "cerebro" para crear el pedido
        $pedido = $this->orderService->createOrderFromCart($carrito, $contacto, null /* pasar aquí la dirección de envío si es diferente */);

        // Limpiamos el carrito de la sesión
        $session->remove('carrito');
        // Decidimos a dónde redirigir al usuario
        if ($pedido->necesitaPagoOnline()) { // Necesitaremos añadir este método a la entidad Pedido
            return $this->redirectToRoute('app_payment_start', ['id' => $pedido->getId()]);
        }

        // Si no necesita pago, es un presupuesto, vamos a la página de éxito
        return $this->redirectToRoute('app_checkout_success', ['id' => $pedido->getId()]);
    }

    /**
     * PASO 4 (ÉXITO): Muestra la página de "gracias por tu pedido/presupuesto".
     */
    #[Route('/success/{id}', name: 'app_checkout_success')]
    public function successAction(Pedido $pedido): Response
    {
        // ...
        return $this->render('checkout/success.html.twig', ['pedido' => $pedido]);
    }
}
