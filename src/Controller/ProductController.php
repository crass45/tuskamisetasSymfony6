<?php
// src/Controller/ProductController.php

namespace App\Controller;

use App\Entity\Color;
use App\Entity\Empresa;
use App\Entity\Modelo;
use App\Entity\Personalizacion;
use App\Entity\Producto;
use App\Model\Carrito;
use App\Model\Presupuesto;
use App\Model\PresupuestoProducto;
use App\Model\PresupuestoTrabajo;
use App\Repository\ModeloRepository;
use App\Service\FechaEntregaService;
use App\Service\PriceCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;


class ProductController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(
        private FechaEntregaService $deliveryDateService,
        EntityManagerInterface $entityManager,
        private ?PriceCalculatorService $priceCalculator = null // Hacemos el servicio opcional
    )
    {
        $this->em = $entityManager;
    }

    /**
     * Muestra la página de detalle de un producto (Modelo).
     */
    #[Route('/{_locale}/producto/{slug}', name: 'app_product_detail', requirements: ['_locale' => 'es|en|fr'])]
    public function showAction(string $slug, ModeloRepository $modeloRepository): Response
    {
        $modelo = $modeloRepository->findOneBy(['nombreUrl' => $slug]);

        if (!$modelo) {
            throw $this->createNotFoundException('El producto solicitado no existe.');
        }

        if (!$modelo->isActivo()) {
            return $this->render('web/product/discontinued.html.twig', ['modelo' => $modelo]);
        }

        $deliveryDates = $this->deliveryDateService->getDeliveryDatesForModel($modelo);

        return $this->render('web/product/show.html.twig', [
            'modelo' => $modelo,
            'deliveryDates' => $deliveryDates,
        ]);
    }

    /**
     * Esta acción se llama por AJAX para añadir un nuevo bloque de formulario de personalización.
     */
    // --- INICIO DE LA CORRECCIÓN ---
    // 1. Cambiamos la firma para pedir el ID en lugar del objeto
    #[Route('/{_locale}/ajax/product/{id}/add-customization/{nPersonalizaciones}', name: 'app_product_add_customization', requirements: ['_locale' => 'es|en|fr'])]
    public function addCustomizationAction(int $id, int $nPersonalizaciones, SessionInterface $session): Response
    {
        // 2. Hacemos la búsqueda del Modelo explícitamente
        $modelo = $this->em->getRepository(Modelo::class)->find($id);
        if (!$modelo) {
            return new Response('Modelo no encontrado.', 404);
        }
        // --- FIN DE LA CORRECCIÓN ---

        $personalizaciones = $modelo->getTecnicas();
        $personalizacionesCarrito = [];
        $carrito = $session->get('carrito');

        if ($carrito) {
            // Lógica para encontrar las personalizaciones ya existentes en el carrito...
            foreach ($carrito->getItems() as $presupuesto) {
                foreach ($presupuesto->getTrabajos() as $trabajo) {
                    // Usamos el identificador único para evitar duplicados
                    $personalizacionesCarrito[$trabajo->getIdentificadorTrabajo()] = $trabajo;
                }
            }
        }


        return $this->render('web/product/partials/_customization_form_row.html.twig', [
            'tecnicas' => $personalizaciones,
            'nPersonalizaciones' => $nPersonalizaciones,
            'personalizacionesCarrito' => $personalizacionesCarrito,
        ]);
    }

    #[Route('/{_locale}/product/{id}/presupuesto-rapido-modal', name: 'app_product_presupuesto_rapido_modal')]
    public function presupuestoRapidoModal(Modelo $modelo): Response
    {
        // Gracias al ParamConverter, Symfony busca automáticamente el objeto Modelo
        // cuyo 'id' coincide con el de la URL. ¡No necesitamos buscarlo manualmente!

        // Renderizamos la plantilla que contendrá el HTML del modal.
        return $this->render('web/product/_contacto_producto_modal.html.twig', [
            'modelo' => $modelo,
        ]);
    }

    // src/Controller/ProductController.php
// ...

    #[Route('/product/presupuesto-rapido-submit', name: 'app_product_presupuesto_rapido_submit', methods: ['POST'])]
    public function presupuestoRapidoSubmit(
        Request $request,
        ModeloRepository $modeloRepository,
        MailerInterface $mailer
    ): Response {
        $modeloId = $request->request->get('modeloId');
        $modelo = $modeloRepository->find($modeloId);
        if (!$modelo) { /* ... manejo de error ... */ }

        // Recogemos todos los datos del formulario, incluyendo los nuevos
        $formData = [
            'nombre' => $request->request->get('nombre'),
            'email' => $request->request->get('email'),
            'telefono' => $request->request->get('telefono'),
            'ciudad' => $request->request->get('ciudad'),
            'cantidad' => $request->request->get('cantidad'), // Nuevo
            'fecha_limite' => $request->request->get('fecha_limite'), // Nuevo
            'colores_tallas' => $request->request->get('colores_tallas'), // Nuevo
            'observaciones' => $request->request->get('observaciones'),
            'googleClientId' => $request->request->get('googleClientId'),
            'modelo' => $modelo,
        ];

        $email = (new TemplatedEmail())
            ->from('noreply@tuskamisetas.com')
            ->to('comercial@tuskamisetas.com')
            ->replyTo($formData['email'])
            ->subject('Solicitud de Información Rápida para: ' . $modelo->getNombre())
            ->htmlTemplate('emails/solicitud_info_producto.html.twig')
            ->context(['data' => $formData, 'emailTitulo'=>'Solicitud de presupuesto rapido','emailSubtitulo'=>'']);

        $mailer->send($email);

        $this->addFlash('success', '¡Gracias! Hemos recibido tu solicitud. Nos pondremos en contacto contigo en breve.');
        return $this->redirectToRoute('app_product_detail', ['slug' => $modelo->getNombreUrl()]);
    }

    /**
     * Esta acción se llama por AJAX para obtener las tallas de un color específico.
     */
    #[Route('/{_locale}/ajax/producto/{modeloId}/get-sizes/{colorId}', name: 'app_product_get_sizes', requirements: ['_locale' => 'es|en|fr'])]
    public function getSizesAction(int $modeloId, string $colorId): Response
    {
        $modelo = $this->em->getRepository(Modelo::class)->find($modeloId);
        if (!$modelo) {
            return new Response('Modelo no encontrado.', 404);
        }

        $color = $this->em->getRepository(Color::class)->find($colorId);
        if (!$color) {
            return new Response('Color no encontrado.', 404);
        }

        $productosTalla = $this->em->getRepository(Producto::class)->findByModelAndColor($modelo, $color);


        return $this->render('web/product/partials/_size_selection.html.twig', [
            'productosTalla' => $productosTalla,
            'color' => $color,
            'modelo' => $modelo,
        ]);
    }

    /**
     * ¡VERSIÓN FINAL Y CORREGIDA!
     * Se llama por AJAX para calcular el precio del presupuesto sin modificar la sesión.
     */
    #[Route('/{_locale}/ajax/producto/update-price', name: 'app_product_update_price', methods: ['POST'], requirements: ['_locale' => 'es|en|fr'])]
    public function updatePriceAction(Request $request, SessionInterface $session): Response
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new Response('Datos inválidos.', 400);
        }

        // 1. Cargamos el carrito REAL de la sesión para obtener el contexto. NO LO MODIFICAREMOS.
        $carritoReal = clone $session->get('carrito', new Carrito());
        $cantidadEnCarrito = $carritoReal->getCantidadTotalProductos();

        // 2. Creamos un Presupuesto NUEVO y LIMPIO que representará los items de la página.
        $presupuestoActual = new Presupuesto();
        $cantidadTotal = 0;
        $ultimoProducto = null; // Para GTag

        // 2a. Añadimos los productos al nuevo presupuesto.
        foreach ($data['productos'] ?? [] as $prodData) {
            if (!empty($prodData['referencia']) && !empty($prodData['cantidad'])) {
                $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $prodData['referencia']]);
                if ($producto) {
                    $ultimoProducto = $producto;
                    $cantidadTotal += (int)$prodData['cantidad'];
                    $presupuestoProducto = new PresupuestoProducto();
                    $presupuestoProducto->setProducto($producto);
                    $presupuestoProducto->setCantidad((int)$prodData['cantidad']);
                    $presupuestoActual->addProducto($presupuestoProducto);
                }
            }
        }

        if ($cantidadTotal === 0) {
            return new Response('No hay productos disponibles.', 400);
        }

        // 2b. Añadimos los trabajos de personalización al nuevo presupuesto.
        foreach ($data['trabajos'] ?? [] as $trabajoData) {
            $presupuestoTrabajo = new PresupuestoTrabajo();


            if (!empty($trabajoData['reutilizado'])) {
                $trabajoOriginal = clone $carritoReal->getTrabajoPorIdentificador($trabajoData['identificador']);
                if ($trabajoOriginal) {
                    $presupuestoTrabajo = $trabajoOriginal;

                }
            } else {
                $trabajo = $this->em->getRepository(Personalizacion::class)->findOneBy(['codigo' => $trabajoData['codigo']]);
                if ($trabajo) {
                    $identificadorTrabajo = 'personalizacion_'.uniqid();
                    $presupuestoTrabajo->setTrabajo($trabajo);
                    $presupuestoTrabajo->setIdentificadorTrabajo($identificadorTrabajo);
                    $presupuestoTrabajo->setCantidad((int)($trabajoData['cantidad'] ?? 1));
                    $presupuestoTrabajo->setUbicacion($trabajoData['ubicacion'] ?? '');
                    $presupuestoTrabajo->setObservaciones($trabajoData['observaciones'] ?? '');
                    $presupuestoTrabajo->setUrlImage($trabajoData['archivo'] ?? '');
                }
            }
            $presupuestoActual->addTrabajo($presupuestoTrabajo);
        }

        // 2c. Añadimos Doblado y Embolsado
        if (!empty($data['doblado'])) {
            $trabajoDB = $this->em->getRepository(Personalizacion::class)->findOneBy(['codigo' => 'DB']);
            if ($trabajoDB) {
                $presupuestoTrabajo = new PresupuestoTrabajo();
                $presupuestoTrabajo->setTrabajo($trabajoDB);
                $presupuestoTrabajo->setIdentificadorTrabajo('doblado-embolsado');
                $presupuestoTrabajo->setCantidad($cantidadTotal);
                $presupuestoActual->addTrabajo($presupuestoTrabajo);
            }
        }

        // 3. Guardamos el presupuesto limpio en sesión.
        $session->set('presupuesto', $presupuestoActual);

        // 4. Creamos un Carrito TEMPORAL para el cálculo (SIN CLONE).
        $carritoParaCalculo = clone $carritoReal;

        $carritoParaCalculo->addItem($presupuestoActual, $this->getUser());

        // 5. Llamamos al servicio con el contexto completo y seguro.
        $resultados = $this->priceCalculator->calculateFullPresupuesto($carritoParaCalculo);

        // 6. Extraemos el desglose del último grupo (el que estamos calculando).
        $resultadoGrupoActual = end($resultados['desglose_grupos']);

        // 7. Renderizamos la plantilla con los datos del servicio.
        return $this->render('web/product/partials/_price_summary.html.twig', [
            'resultados_grupo' => $resultadoGrupoActual,
            'ivaGeneral' => $resultados['iva_aplicado'],
            'cantidadEnCarrito' => $cantidadEnCarrito,
            'presupuesto' => $presupuestoActual,
            'productosGTAG_ref' => $ultimoProducto ? $ultimoProducto->getModelo()->getReferencia() : '',
            'productosGTAGNombre' => $ultimoProducto ? $ultimoProducto->getModelo()->getNombre() : '',
            'productosGTAGBrand' => $ultimoProducto && $ultimoProducto->getModelo()->getFabricante() ? $ultimoProducto->getModelo()->getFabricante()->getNombre() : '',
        ]);
    }
}

