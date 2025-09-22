<?php
// src/Controller/CheckoutController.php

namespace App\Controller;

use App\Entity\Contacto;
use App\Entity\Direccion;
use App\Entity\Empresa;
use App\Entity\Pedido;
use App\Form\Type\ContactoType;
use App\Form\Type\DireccionEnvioType;
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
        private OrderService           $orderService // El "cerebro" que creará el pedido
    )
    {
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
    public function addressAction(Request $request, SessionInterface $session): Response
    {
        $contacto = $this->em->getRepository(Contacto::class)->findOneBy(['usuario' => $this->getUser()]) ?? new Contacto();
        $contacto->setUsuario($this->getUser());
        if (!$contacto->getDireccionFacturacion()) {
            $contacto->setDireccionFacturacion(new Direccion());
        }

        $formContacto = $this->createForm(ContactoType::class, $contacto);
        $formEnvio = $this->createForm(DireccionEnvioType::class); // Se crea el form de envío

        return $this->render('checkout/address.html.twig', [
            'contacto' => $contacto,
            'formContacto' => $formContacto,
            'formEnvio' => $formEnvio, // Se pasa a la plantilla
            'carrito' => $session->get('carrito')
        ]);
    }

    #[Route('/process', name: 'app_checkout_process', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function processAction(Request $request, SessionInterface $session): Response
    {
        $carrito = $session->get('carrito');
        if (!$carrito instanceof Carrito) {
            return $this->redirectToRoute('app_cart_show');
        }

        $contacto = $this->em->getRepository(Contacto::class)->findOneBy(['usuario' => $this->getUser()]) ?? new Contacto();
        $contacto->setUsuario($this->getUser());

        $formContacto = $this->createForm(ContactoType::class, $contacto);
        $formEnvio = $this->createForm(DireccionEnvioType::class);

        $formContacto->handleRequest($request);


        if ($formContacto->isSubmitted() && $formContacto->isValid()) {
            $tipoEnvio = $request->request->getInt('tipoEnvio', 1);
            $direccionEnvio = ($tipoEnvio === 1) ? $contacto->getDireccionFacturacion() : null;

            if ($tipoEnvio === 2) {

//                if ($formEnvio->isSubmitted() && $formEnvio->isValid()) {
//                    $direccionEnvio = $formEnvio->getData();
//                    $direccionEnvio->setIdContacto($contacto);
//                } else {
//                    $this->addFlash('error', 'Por favor, revisa los datos de la nueva dirección de envío.');
//                    return $this->render('checkout/address.html.twig', ['contacto' => $contacto, 'formContacto' => $formContacto, 'formEnvio' => $formEnvio, 'carrito' => $carrito]);
//                }

                ///PONEMOS LA LOGICA QUE TENIAMOS EN NUESTRO ANTERIOR SYMFONY
//                if ($formEnvio->isSubmitted() && $formEnvio->isValid()) {
                $comboDirecciones = $request->request->get('misdirecciones');
                if ($comboDirecciones == -1) {
                    //SE CREA UNA NUEVA DIRECCIÓN
                    $direccionEnvio = new Direccion();
                } else {
                    //SE UTILIZA UNA DIRECCIÓN EXISTENTE
                    //TODO VER COMO OBTENER LA DIRECCIÓN
//                    $direccionEnvio = $em->getRepository('')->findOneBy(array('id' => $comboDirecciones));
                }
                if ($direccionEnvio == null) {
                    $direccionEnvio = new Direccion();
                }

                //si la dirección es predeterminada se pone el resto como no predeterminadas
                if ($formEnvio->get('predeterminada')->getData()) {
                    foreach ($contacto->getDireccionesEnvio() as $direccion) {
                        $direccion->setPredeterminada(false);
                        $this->em->persist($direccion);
                        $this->em->persist($contacto);
                    }
                }
                $direccionEnvio->setNombre($formEnvio->get('nombre')->getData());
                $direccionEnvio->setPredeterminada($formEnvio->get('predeterminada')->getData());
                $direccionEnvio->setDir($formEnvio->get('dir')->getData());
                $direccionEnvio->setCp($formEnvio->get('cp')->getData());
                $direccionEnvio->setTelefonoMovil($formEnvio->get('telefonoMovil')->getData());
                $direccionEnvio->setPoblacion($formEnvio->get('poblacion')->getData());
                $direccionEnvio->setProvincia($formEnvio->get('provincia')->getData());
                $direccionEnvio->setProvinciaBD($formEnvio->get('provinciaBD')->getData());
//                $direccionEnvio->setPais($formEnvio->get('paisBD')->getData());
                $direccionEnvio->setPaisBD($formEnvio->get('paisBD')->getData());
                $direccionEnvio->setIdContacto($contacto);
                $this->em->persist($direccionEnvio);

            } elseif ($tipoEnvio === 3) {
                $empresa = $this->em->getRepository(Empresa::class)->findOneBy([]);
                $direccionEnvio = $empresa ? $empresa->getDireccionEnvio() : null;

            }

//            if ($direccionEnvio && !$direccionEnvio->getId()) {
            $this->em->persist($direccionEnvio);
//            }
            $this->em->persist($contacto);
            $this->em->flush();

            $pedido = $this->orderService->createOrderFromCart($carrito, $contacto, $direccionEnvio);
            //MARCAMOS RECOGER EN TIENDA EN EL PEDIDO EN CASO QUE PROCEDA
            if($tipoEnvio===3){
                $pedido->setRecogerEnTienda(true);
            }
            $session->remove('carrito');

            if ($pedido->necesitaPagoOnline()) {
                return $this->redirectToRoute('app_payment_start', ['id' => $pedido->getId()]);
            }
            return $this->redirectToRoute('app_checkout_success', ['id' => $pedido->getId()]);
        }

        $this->addFlash('error', 'Por favor, revisa tus datos de facturación.');
        return $this->render('checkout/address.html.twig', ['contacto' => $contacto, 'formContacto' => $formContacto, 'formEnvio' => $formEnvio, 'carrito' => $carrito]);
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
