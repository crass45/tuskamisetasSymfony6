<?php
// src/Controller/CarritoController.php

namespace App\Controller;

use App\Entity\Descuento;
use App\Entity\Empresa;
use App\Entity\GastosEnvio;
use App\Entity\ZonaEnvio;
use App\Model\Carrito;
use App\Model\Presupuesto;
use App\Repository\GastosEnvioRepository;
use App\Service\OrderService;
use App\Service\PriceCalculatorService;
use App\Service\ShippingCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/carrito', requirements: ['_locale' => 'es|en|fr'])]
class CarritoController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PriceCalculatorService $priceCalculator,
        private ShippingCalculatorService $shippingCalculator
    )
    {
    }

    /**
     * Helper para obtener el carrito de la sesión o crear uno nuevo.
     */
    private function getOrCreateCart(SessionInterface $session): Carrito
    {
        $carrito = $session->get('carrito');
        if (!$carrito instanceof Carrito) {
            $carrito = new Carrito();
            $zonaEnvioDefault = $this->em->getRepository(ZonaEnvio::class)->find(1);
            if ($zonaEnvioDefault) {
                // Asumimos que la lógica de precios está en el modelo Carrito.
                // Esta parte necesitará la lógica de tu entidad Carrito antigua.
            }
            $session->set('carrito', $carrito);
        }
        return $carrito;
    }

    /**
     * Muestra la página principal del carrito.
     * Reemplaza a 'carritoAction'.
     */
    #[Route('/', name: 'app_cart_show')]
    public function showAction(SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);
        $resultadosPrecios = null;
        $empresa = $this->em->getRepository(Empresa::class)->find(1); // Para el servicio expres
        $gastosEnvio = 0;


        $editingOrderId = $session->get('editing_order_id');
        $zonasEnvio = $this->em->getRepository(ZonaEnvio::class)->findAll();

        // La zona seleccionada la gestionaremos en el summary
        $zonaSeleccionadaId = $session->get('cart_shipping_zone', 1); // Default a 1 (Península)
        $zonaEnvio = $this->em->getRepository(ZonaEnvio::class)->find($zonaSeleccionadaId);
        // Si el carrito tiene productos, llamamos a nuestro servicio
        if ($carrito->getCantidadProductosTotales() > 0) {
            $resultadosPrecios = $this->priceCalculator->calculateFullPresupuesto($carrito);
            // --- ¡CAMBIO! Usamos un valor fijo para los gastos de envío ---
            $gastosEnvio = $this->shippingCalculator->calculateShippingCost(
                $carrito,
                $zonaEnvio,
                $resultadosPrecios['subtotal_sin_iva']
            );
        }

        // Si el cliente elige "Recoger en Tienda", los gastos son 0
        if ($carrito->getRecogerTienda()) {
            $gastosEnvio = 0;
        }

        return $this->render('carrito/show.html.twig', [
            'carrito' => $carrito,
            'resultados' => $resultadosPrecios, // Pasamos los resultados del cálculo
            'zonasEnvio' => $zonasEnvio,
            'zonaSeleccionada' => $zonaSeleccionadaId,
            'editing_order_id' => $editingOrderId,
            'empresa' => $empresa,
            'precioGastos' => $gastosEnvio
        ]);
    }

    /**
     * Procesa la adición del presupuesto actual al carrito.
     * Reemplaza a 'carritoAddAction'.
     */
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
        }

        return $this->redirectToRoute('app_cart_show', ['_locale' => $session->get('_locale', 'es')]);
    }

    /**
     * Elimina completamente un item (presupuesto) del carrito.
     * Reemplaza a 'carritoEliminaItemsAction'.
     */
    #[Route('/remove/{id}', name: 'app_cart_remove_item')]
    public function removeItemAction(Presupuesto $presupuesto, SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);
        // Asumimos que la clase Carrito tiene un método 'eliminaItem'.
        $carrito->eliminaItem($presupuesto);
        $session->set('carrito', $carrito);

        return $this->redirectToRoute('app_cart_show', ['_locale' => $session->get('_locale', 'es')]);
    }

    /**
     * Incrementa la cantidad de un producto en el carrito.
     * Reemplaza a 'carritoUpItemAction'.
     */
    #[Route('/item/increase/{referencia}/{cantidad}', name: 'app_cart_increase_item')]
    public function increaseItemAction(string $referencia, int $cantidad, SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);
        // MIGRACIÓN NOTA: La lógica para encontrar y modificar el item específico
        // ahora debería vivir dentro de la clase Carrito.
        // $carrito->upProducto($referencia, $cantidad, $this->getUser());
        $session->set('carrito', $carrito);
        return $this->redirectToRoute('app_cart_show', ['_locale' => $session->get('_locale', 'es')]);
    }

    /**
     * Decrementa la cantidad de un producto en el carrito.
     * Reemplaza a 'carritoDownItemAction'.
     */
    #[Route('/item/decrease/{referencia}/{cantidad}', name: 'app_cart_decrease_item')]
    public function decreaseItemAction(string $referencia, int $cantidad, SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);
        // MIGRACIÓN NOTA: La lógica para encontrar y modificar el item específico
        // ahora debería vivir dentro de la clase Carrito.
        // $carrito->lessProducto($referencia, $cantidad, $this->getUser());
        $session->set('carrito', $carrito);
        return $this->redirectToRoute('app_cart_show', ['_locale' => $session->get('_locale', 'es')]);
    }

    /**
     * Vacía el carrito por completo.
     * Reemplaza a 'carritoVaciaAction'.
     */
    #[Route('/vacia', name: 'app_cart_clear')]
    public function clearAction(SessionInterface $session): Response
    {
        $session->remove('carrito');
        $this->addFlash('notice', 'El carrito ha sido vaciado.');
        return $this->redirectToRoute('app_cart_show', ['_locale' => $session->get('_locale', 'es')]);
    }

    /**
     * Actualiza el resumen del carrito via AJAX.
     * Reemplaza a 'modificaResumenAction', 'recogerTiendaAction' y 'servicioExpresAction'.
     */
    #[Route('/update-summary', name: 'app_cart_update_summary', methods: ['POST'])]
    public function updateSummaryAction(Request $request, SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);
        $carrito->setServicioExpres($request->request->getBoolean('servicioExpres', false));
//        $carrito->setRecogerTienda($request->request->getBoolean('tienda', false));
        $session->set('carrito', $carrito);

        $zonaEnvioId = $request->request->getInt('zonaEnvio', 1);
        $session->set('cart_shipping_zone', $zonaEnvioId);
        $zonaEnvio = $this->em->getRepository(ZonaEnvio::class)->find($zonaEnvioId);

        $resultadosPrecios = $this->priceCalculator->calculateFullPresupuesto($carrito);
        $empresa = $this->em->getRepository(Empresa::class)->find(1);

        // ¡CAMBIO! Usamos el nuevo servicio también en la llamada AJAX
        $gastosEnvio = $this->shippingCalculator->calculateShippingCost(
            $carrito,
            $zonaEnvio,
            $resultadosPrecios['subtotal_sin_iva']
        );

        return $this->render('carrito/partials/_cart_summary.html.twig', [
            'carrito' => $carrito,
            'resultados' => $resultadosPrecios,
            'zonasEnvio' => $this->em->getRepository(ZonaEnvio::class)->findAll(),
            'zonaSeleccionada' => $zonaEnvioId,
            'precioGastos' => $gastosEnvio,
            'empresa' => $empresa,
        ]);
    }

    /**
     * Genera y descarga un PDF del carrito actual.
     * Reemplaza a 'downloadCarritoPdfAction'.
     */
    #[Route('/descargar-pdf', name: 'app_cart_download_pdf')]
    public function downloadCartPdfAction(SessionInterface $session, Pdf $snappy): Response
    {
        $carrito = $this->getOrCreateCart($session);
        $html = $this->renderView('pdf/cart_quote.html.twig', ['carrito' => $carrito]);

        return new Response(
            $snappy->getOutputFromHtml($html),
            200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="presupuesto.pdf"']
        );
    }

    /**
     * Aplica un código de descuento al carrito (llamado por AJAX).
     * Reemplaza a 'carritoCargaAction'.
     */
    #[Route('/apply-discount/{codigo}', name: 'app_cart_apply_discount')]
    public function applyDiscountAction(string $codigo, SessionInterface $session, GastosEnvioRepository $gastosEnvioRepo): Response
    {
        $carrito = $this->getOrCreateCart($session);

        $descuento = $this->em->getRepository(Descuento::class)->findOneBy(['codigo' => $codigo]);
        // Aquí iría tu lógica para validar si el descuento está activo...

        if ($descuento) {
            // MIGRACIÓN NOTA: La lógica para calcular y aplicar el descuento
            // ahora debería vivir dentro de la clase Carrito.
            // $carrito->aplicarDescuento($descuento, $gastosEnvioRepo);
        }

        $session->set('carrito', $carrito);

        // Devuelve el fragmento del detalle del carrito actualizado
        return $this->render('carrito/_cart_details.html.twig', ['carrito' => $carrito]);
    }

    /**
     * Devuelve la cantidad de items del carrito (para el icono de la cabecera).
     * Reemplaza a 'carritoCantidadAction'.
     */
    #[Route('/quantity', name: 'app_cart_quantity')]
    public function quantityAction(SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);
        return $this->render('carrito/_quantity.html.twig', ['cantidad' => $carrito->getCantidadProductosTotales()]);
    }

    /**
     * Elimina una línea de producto específica de un item (Presupuesto) del carrito.
     */
    #[Route('/remove-product/{itemIndex}/{productIndex}', name: 'app_cart_remove_product', requirements: ['itemIndex' => '\d+', 'productIndex' => '\d+'])]
    public function removeProductAction(int $itemIndex, int $productIndex, SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);

        // Llamamos al nuevo método de la clase Carrito
        $carrito->eliminaProductoPorIndice($itemIndex, $productIndex);

        $session->set('carrito', $carrito);
        $this->addFlash('notice', 'El producto ha sido eliminado del carrito.');

        return $this->redirectToRoute('app_cart_show', ['_locale' => $session->get('_locale', 'es')]);
    }

    #[Route('/increase-quantity/{itemIndex}/{productIndex}', name: 'app_cart_increase_quantity')]
    public function increaseQuantityAction(int $itemIndex, int $productIndex, SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);
        $carrito->increaseProductQuantity($itemIndex, $productIndex);
        $session->set('carrito', $carrito);
        return $this->redirectToRoute('app_cart_show', ['_locale' => $session->get('_locale', 'es')]);
    }

    #[Route('/decrease-quantity/{itemIndex}/{productIndex}', name: 'app_cart_decrease_quantity')]
    public function decreaseQuantityAction(int $itemIndex, int $productIndex, SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);
        $carrito->decreaseProductQuantity($itemIndex, $productIndex);
        $session->set('carrito', $carrito);
        return $this->redirectToRoute('app_cart_show', ['_locale' => $session->get('_locale', 'es')]);
    }

    /**
     * Actualiza la cantidad de un producto específico en el carrito a un valor concreto.
     */
    #[Route('/update-quantity/{itemIndex}/{productIndex}/{quantity}', name: 'app_cart_update_quantity', requirements: ['itemIndex' => '\d+', 'productIndex' => '\d+', 'quantity' => '\d+'])]
    public function updateQuantityAction(int $itemIndex, int $productIndex, int $quantity, SessionInterface $session): Response
    {
        $carrito = $this->getOrCreateCart($session);

        // Llamamos al nuevo método de la clase Carrito
        $carrito->updateProductQuantity($itemIndex, $productIndex, $quantity);

        $session->set('carrito', $carrito);
        $this->addFlash('success', 'La cantidad del producto ha sido actualizada.');

        return $this->redirectToRoute('app_cart_show', ['_locale' => $session->get('_locale', 'es')]);
    }

    /**
     * NUEVA ACCIÓN: Guarda los cambios de un pedido que se está editando.
     */
    #[Route('/carrito/save-edited', name: 'app_cart_save_edited_order', methods: ['POST'])]
    public function saveEditedOrderAction(SessionInterface $session, OrderService $orderService): Response
    {
        $carrito = $session->get('carrito');
        $editingOrderId = $session->get('editing_order_id');

        if (!$carrito instanceof Carrito || !$editingOrderId) {
            // Si no estamos en modo edición, redirigir al carrito normal
            return $this->redirectToRoute('app_cart_show');
        }

        // Usamos nuestro servicio para actualizar el pedido
        $pedido = $orderService->createOrUpdateOrderFromCart(
            $carrito,
            $this->getUser()->getContacto(),
            null,
            null,
            $editingOrderId
        );

        // Limpiamos la sesión para salir del modo edición
        $session->remove('carrito');
        $session->remove('editing_order_id');

        $this->addFlash('sonata_flash_success', 'El pedido #' . $pedido->getId() . ' ha sido actualizado correctamente.');

        // Redirigimos de vuelta al panel de administración, a la página de edición del pedido
        return $this->redirectToRoute('admin_app_pedido_edit', ['id' => $pedido->getId()]);
    }
}

