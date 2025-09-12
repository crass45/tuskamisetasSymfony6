<?php
//// src/Controller/CarritoController.php
//
//namespace App\Controller;
//
//use App\Entity\GastosEnvio;
//use App\Entity\ZonaEnvio;
//use App\Model\Carrito;
//use App\Model\Presupuesto;
//use Doctrine\ORM\EntityManagerInterface;
//use Knp\Snappy\Pdf; // <-- Importamos el servicio de PDF
//use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
//use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\HttpFoundation\Response;
//use Symfony\Component\HttpFoundation\Session\SessionInterface;
//use Symfony\Component\Routing\Annotation\Route;
//
//// MIGRACIÓN: Se renombra la clase y se actualiza el prefijo de la ruta
//#[Route('/{_locale}/carrito', requirements: ['_locale' => 'es|en|fr'])]
//class CarritoController extends AbstractController
//{
//    private EntityManagerInterface $em;
//    private SessionInterface $session;
//    private Pdf $snappy; // <-- Se añade la propiedad para el servicio de PDF
//
//    // MIGRACIÓN: Se inyectan todos los servicios necesarios
//    public function __construct(EntityManagerInterface $entityManager, SessionInterface $session, Pdf $snappy)
//    {
//        $this->em = $entityManager;
//        $this->session = $session;
//        $this->snappy = $snappy;
//    }
//
//    private function getOrCreateCart(): Carrito
//    {
//        $carrito = $this->session->get('carrito');
//        if (!$carrito instanceof Carrito) {
//            $carrito = new Carrito();
//            $precioGastos = $this->em->getRepository(GastosEnvio::class)->findOneBy(['codigoPostal' => '30']);
//            if ($precioGastos) {
//                $carrito->setPrecioGastos((float) $precioGastos->getPrecioReducido());
//                $carrito->setPrecioGastosReducidos((float) $precioGastos->getPrecioReducido());
//            }
//            $this->session->set('carrito', $carrito);
//        }
//        return $carrito;
//    }
//
//    #[Route('/', name: 'app_cart_show')]
//    public function showCartAction(): Response
//    {
//        $carrito = $this->getOrCreateCart();
//        $zonasEnvio = $this->em->getRepository(ZonaEnvio::class)->findAll();
//
//        return $this->render('web/cart/show.html.twig', [
//            'carrito' => $carrito,
//            'zonasEnvio' => $zonasEnvio,
//            'zonaSeleccionada' => 0
//        ]);
//    }
//
//    #[Route('/add', name: 'app_cart_add', methods: ['POST'])]
//    public function addAction(): Response
//    {
//        $carrito = $this->getOrCreateCart();
//        $presupuestoActual = $this->session->get('presupuesto');
//
//        if ($presupuestoActual instanceof Presupuesto) {
//            $carrito->addItem($presupuestoActual, $this->getUser());
//            $this->session->set('carrito', $carrito);
//        }
//
//        return $this->redirectToRoute('app_cart_show');
//    }
//
//    #[Route('/remove/{productoId}/{cantidad}/{trabajos}', name: 'app_cart_remove_item')]
//    public function removeItemAction(int $productoId, int $cantidad, string $trabajos = ''): Response
//    {
//        $carrito = $this->getOrCreateCart();
//        foreach ($carrito->getItems() as $presupuesto) {
//            $presupuesto->eliminaProducto($productoId, $cantidad, $trabajos, $this->getUser());
//            if ($presupuesto->getCantidadProductos() === 0) {
//                $carrito->eliminaItem($presupuesto);
//            }
//        }
//        return $this->redirectToRoute('app_cart_show');
//    }
//
//    // ... (acciones para up/down item siguen un patrón similar)
//
//    #[Route('/clear', name: 'app_cart_clear')]
//    public function clearAction(): Response
//    {
//        $this->session->remove('carrito');
//        return $this->redirectToRoute('app_cart_show');
//    }
//
//    #[Route('/update-summary', name: 'app_cart_update_summary', methods: ['POST'])]
//    public function updateSummaryAction(Request $request): Response
//    {
//        $carrito = $this->getOrCreateCart();
//        $servicioExpres = $request->request->get('servicioExpres') === 'true';
//        $tienda = $request->request->get('tienda') === '1';
//        $zonaEnvioId = $request->request->getInt('zonaEnvio', 1);
//
//        $carrito->setServicioExpres($servicioExpres);
//        $carrito->setTipoEnvio($tienda ? 3 : 1);
//
//        $zonaEnvio = $this->em->getRepository(ZonaEnvio::class)->find($zonaEnvioId);
//
//        return $this->render('web/cart/_summary.html.twig', [
//            'carrito' => $carrito,
//            'zonaEnvioSeleccionada' => $zonaEnvioId,
//            'precioGastos' => $carrito->getGastosEnvio($this->getUser(), $zonaEnvio),
//        ]);
//    }
//
//    // --- MÉTODO DEL PDF AÑADIDO ---
//    #[Route('/descargar-pdf', name: 'app_cart_download_pdf')]
//    public function downloadCartPdfAction(): Response
//    {
//        $carrito = $this->session->get('carrito');
//        if (!$carrito instanceof Carrito) {
//            $this->addFlash('warning', 'Tu carrito está vacío.');
//            return $this->redirectToRoute('app_cart_show');
//        }
//
//        $html = $this->renderView('pdf/cart_quote.html.twig', ['carrito' => $carrito]);
//
//        return new Response(
//            $this->snappy->getOutputFromHtml($html),
//            200,
//            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="presupuesto.pdf"']
//        );
//    }
//}
