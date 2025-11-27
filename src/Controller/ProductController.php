<?php
// src/Controller/ProductController.php

namespace App\Controller;

use App\Entity\Color;
use App\Entity\Empresa;
use App\Entity\Inventario;
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
    /**
     * Gestión de Inventario (Entradas/Salidas) para un modelo.
     */
    #[Route('/{_locale}/usuario/inventario/{id}/{operacion}', name: 'app_product_inventory', requirements: ['_locale' => 'es|en|fr'])]
    public function inventoryAction(Modelo $modelo, int $operacion, Request $request): Response
    {
        // 1. Obtenemos todos los productos del modelo (optimizando la consulta)
        // En lugar de ir color por color, traemos todos los productos activos de este modelo
        $productos = $this->em->getRepository(Producto::class)->createQueryBuilder('p')
            ->leftJoin('p.color', 'c')
            ->leftJoin('p.inventario', 'inv') // <--- AÑADIR ESTO (Eager Loading)
            ->addSelect('inv')                // <--- AÑADIR ESTO
            ->where('p.modelo = :modelo')
            ->setParameter('modelo', $modelo)
            ->andWhere('p.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('c.nombre', 'ASC')
            ->getQuery()
            ->getResult();

        // 2. Definimos el array de tallas para la ordenación (Copiado de tu lógica antigua)
        $tallasOrdenadas = [
            '1/2 AÑOS', '3/4 AÑOS', '5/6 AÑOS', '7/8 AÑOS', '9/10 AÑOS', '11/12 AÑOS', '13/14 AÑOS',
            '1 AÑOS', '2 AÑOS', '3 AÑOS', '4 AÑOS', '6 AÑOS', '8 AÑOS', '11 AÑOS', '12 AÑOS',
            '13 AÑOS', '14 AÑOS', '15 AÑOS', '16 AÑOS',
            '1/2', '3/4', '5/6', '7/8', '9/10', '11/12', '13/14',
            '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16',
            'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'XXXXL', 'XXXXXL', 'XXXXXXL',
            '2XL', '3XL', '4XL', '5XL', '6XL'
        ];
        // Añadimos números del 0 al 100
        for ($i = 0; $i < 100; $i++) {
            $tallasOrdenadas[] = (string)$i;
        }

        // Mapa de pesos para ordenación rápida
        $tallasPeso = array_flip($tallasOrdenadas);

        // 3. Ordenamos los productos: Primero por Color, luego por Talla
        usort($productos, function (Producto $a, Producto $b) use ($tallasPeso) {
            // A. Comparar Color
            $colorA = $a->getColor() ? $a->getColor()->getNombre() : 'ZZZ'; // Sin color al final
            $colorB = $b->getColor() ? $b->getColor()->getNombre() : 'ZZZ';

            $colorCompare = strcasecmp($colorA, $colorB);
            if ($colorCompare !== 0) {
                return $colorCompare;
            }

            // B. Comparar Talla (si el color es igual)
            $tallaA = strtoupper($a->getTalla());
            $tallaB = strtoupper($b->getTalla());

            $posA = $tallasPeso[$tallaA] ?? 9999;
            $posB = $tallasPeso[$tallaB] ?? 9999;

            return $posA <=> $posB;
        });

        // 4. Renderizamos la vista
        // Recogemos mensajes de éxito/error si vienen por parámetros GET (query params)
        $success = $request->query->get('success');
        $error = $request->query->get('error');

        return $this->render('web/product/inventario.html.twig', [
            'productos' => $productos,
            'modelo' => $modelo,
            'operacion' => $operacion, // 1 = Entrada, 2 = Salida (suposición basada en tu código)
            'success' => $success,
            'error' => $error
        ]);
    }

    /**
     * Procesa el guardado del inventario (CORREGIDO).
     */
    #[Route('/{_locale}/usuario/inventario/{id}/save/{operacion}', name: 'app_product_inventory_save', methods: ['POST'], requirements: ['_locale' => 'es|en|fr'])]
    public function inventorySaveAction(Modelo $modelo, int $operacion, Request $request): Response
    {
        $caja = (int) $request->request->get('caja');
        $observaciones = $request->request->get('observaciones');
        $cantidades = $request->request->all('cantidad');

        if (empty($cantidades) || !is_array($cantidades)) {
            $this->addFlash('error', 'No se han enviado datos válidos.');
            return $this->redirectToRoute('app_product_inventory', ['id' => $modelo->getId(), 'operacion' => $operacion]);
        }

        $movimientos = 0;
        $repoInventario = $this->em->getRepository(Inventario::class);

        foreach ($cantidades as $productoId => $cantidad) {
            $cantidad = (int)$cantidad;

            if ($cantidad > 0) {
                $producto = $this->em->getRepository(Producto::class)->find($productoId);

                if ($producto) {
                    // 1. Buscamos si ya existe inventario en esa caja
                    $inventario = $repoInventario->findOneBy([
                        'producto' => $producto,
                        'caja' => $caja
                    ]);

                    // 2. Lógica según Operación
                    if ($operacion == 1) {
                        // --- ENTRADA ---
                        if ($inventario) {
                            // Si ya existe, SUMAMOS
                            $inventario->addCantidad($cantidad);
                            // Concatenamos observaciones si hay nuevas
                            if ($observaciones) {
                                $prevObs = $inventario->getObservaciones() ?? '';
                                $inventario->setObservaciones(trim($prevObs . ' | ' . $observaciones, ' | '));
                            }
                        } else {
                            // Si no existe, CREAMOS
                            $inventario = new Inventario();
                            $inventario->setProducto($producto);
                            $inventario->setCaja($caja);
                            $inventario->setCantidad($cantidad);
                            $inventario->setObservaciones($observaciones);
                            $this->em->persist($inventario);
                        }

                        // Actualizamos stock global
//                        $producto->setStock($producto->getStock() + $cantidad);

                    } else {
                        // --- SALIDA ---
                        if ($inventario) {
                            // Si existe, RESTAMOS usando tu método lessCantidad
                            $inventario->lessCantidad($cantidad);

                            // Opcional: Si quieres actualizar observaciones en salida también
                            if ($observaciones) {
                                $prevObs = $inventario->getObservaciones() ?? '';
                                $inventario->setObservaciones(trim($prevObs . ' | OUT: ' . $observaciones, ' | '));
                            }

                            // Actualizamos stock global (solo si había stock en caja)
//                            $producto->setStock($producto->getStock() - $cantidad);
                        } else {
                            // Intentan sacar de una caja vacía.
                            // Opción A: Ignorar.
                            // Opción B: Restar del global aunque no haya en caja (Descomenta si quieres esto)
                            // $producto->setStock($producto->getStock() - $cantidad);
                        }
                    }

                    $this->em->persist($producto);
                    $movimientos++;
                }
            }
        }

        if ($movimientos > 0) {
            $this->em->flush();
            $mensaje = ($operacion == 1) ? "Entrada de stock realizada correctamente." : "Salida de stock realizada correctamente.";

            return $this->redirectToRoute('app_product_inventory', [
                'id' => $modelo->getId(),
                'operacion' => $operacion,
                'success' => $mensaje
            ]);
        }

        return $this->redirectToRoute('app_product_inventory', [
            'id' => $modelo->getId(),
            'operacion' => $operacion,
            'error' => 'No se realizaron movimientos.'
        ]);
    }
}

