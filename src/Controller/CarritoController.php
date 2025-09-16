<?php
// src/Controller/CarritoController.php

namespace App\Controller;

use App\Entity\GastosEnvio;
use App\Model\Carrito;
use App\Model\Presupuesto;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/carrito', requirements: ['_locale' => 'es|en|fr'])]
class CarritoController extends AbstractController
{
    private EntityManagerInterface $em;
    private Pdf $snappy;

    // CORRECCIÓN: Se elimina SessionInterface del constructor
    public function __construct(EntityManagerInterface $entityManager, Pdf $snappy)
    {
        $this->em = $entityManager;
        $this->snappy = $snappy;
    }

    // CORRECCIÓN: El método ahora recibe la sesión como argumento
    private function getOrCreateCart(SessionInterface $session): Carrito
    {
        $carrito = $session->get('carrito');
        if (!$carrito instanceof Carrito) {
            $carrito = new Carrito();
            // ... lógica de inicialización ...
            $session->set('carrito', $carrito);
        }
        return $carrito;
    }

    // CORRECCIÓN: La acción ahora pide la SessionInterface
    #[Route('/', name: 'app_cart_show')]
    public function showCartAction(SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);
        // ... (resto del código)
    }

    /**
     * Procesa la adición del presupuesto actual al carrito.
     */
    // CORRECCIÓN: La acción ahora pide la SessionInterface
    #[Route('/add', name: 'app_cart_add', methods: ['POST'])]
    public function addAction(SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);

        $presupuestoActual = $session->get('presupuesto');

        if ($presupuestoActual instanceof Presupuesto) {
            $carrito->addItem($presupuestoActual, $this->getUser());
            $session->set('carrito', $carrito);
            $session->remove('presupuesto');

            $this->addFlash('success', '¡Producto añadido al carrito!');
        } else {
            $this->addFlash('error', 'No se ha podido añadir el producto al carrito.');
        }

        return $this->redirectToRoute('app_cart_show', ['_locale' => $session->get('_locale', 'es')]);
    }

    // ... (El resto de tus acciones deben seguir el mismo patrón:
    //      añadir SessionInterface como argumento donde sea necesario)
}

