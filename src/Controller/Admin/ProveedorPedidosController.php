<?php
// src/Controller/Admin/SupplierOrderController.php

namespace App\Controller\Admin;

use App\Entity\Estado;
use App\Entity\Inventario;
use App\Entity\Pedido;
use App\Model\Carrito;
use App\Model\Presupuesto;
use App\Model\PresupuestoProducto;
use App\Repository\PedidoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// MIGRACIÓN: Se protege todo el controlador para que solo los administradores puedan acceder
#[Route('/admin/pedidos')]
#[IsGranted('ROLE_ADMIN')]
class ProveedorPedidosController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    #[Route('/pedir-a-proveedor', name: 'app_admin_supplier_order_request')]
    public function requestOrderAction(SessionInterface $session): Response
    {
        /** @var PedidoRepository $pedidoRepo */
        $pedidoRepo = $this->em->getRepository(Pedido::class);

        // MIGRACIÓN: La consulta compleja ahora vive en el repositorio, manteniendo el controlador limpio.
        $pedidos = $pedidoRepo->findOrdersForSupplierRequest();

        $proveedores = [];
        $modelos = [];
        $arraypedidos = [];
        $cadenaPedidos = '';
        $cadenaIdPedidos = '';

        $carrito = new Carrito();

        foreach ($pedidos as $pedido) {
            $arraypedidos[] = $pedido;
            $cadenaPedidos .= $pedido->getId() . ';';
            $cadenaIdPedidos .= $pedido->getNombre() . "<br>";

            foreach ($pedido->getLineas() as $linea) {
                if ($linea->getCantidad() > 0 && $linea->getProducto()) {
                    $modelo = $linea->getProducto()->getModelo();
                    $proveedor = $modelo?->getProveedor();

                    // Usamos spl_object_hash para comprobar si el objeto ya está en el array
                    if ($modelo && !isset($modelos[spl_object_hash($modelo)])) {
                        $modelos[spl_object_hash($modelo)] = $modelo;
                    }
                    if ($proveedor && !isset($proveedores[spl_object_hash($proveedor)])) {
                        $proveedores[spl_object_hash($proveedor)] = $proveedor;
                    }

                    // La lógica para construir el 'carrito' se mantiene
                    $presupuestoProducto = new PresupuestoProducto();
                    $presupuestoProducto->setId($linea->getProducto()->getId());
                    $presupuestoProducto->setCantidad($linea->getCantidad());
                    // Asumimos que setProducto ya no necesita el usuario para este contexto
                    $presupuestoProducto->setProducto($linea->getProducto(), $linea->getCantidad(), null);

                    $carritoPresupuesto = new Presupuesto();
                    $carritoPresupuesto->addProducto($presupuestoProducto, null);
                    $carrito->addItem($carritoPresupuesto);
                }
            }
        }

        $carrito->setObservaciones($cadenaIdPedidos);
        $session->set('pedirProveedor', $carrito);

        return $this->render('admin/pedir_a_proveedor.html.twig', [
            'carrito' => $carrito,
            'modelos' => array_values($modelos),
            'fabricantes' => array_values($proveedores), // Cambiado a fabricantes para consistencia
            'pedidos' => $cadenaPedidos,
            'arraypedidos' => $arraypedidos
        ]);
    }

    // --- MÉTODO PARA EL PDF (MODIFICADO) --- ahora enviamos email con el pdf a joseluis@tuskamisetas.com
    #[Route('/pedir-a-proveedor/pdf', name: 'app_admin_supplier_order_pdf')]
    public function pdfAction(SessionInterface $session): Response
    {
        $carrito = $session->get('pedirProveedor');
        if (!$carrito) {
            $this->addFlash('error', 'No se ha encontrado el carrito para generar el PDF.');
            return $this->redirectToRoute('app_admin_supplier_order_request');
        }

        $proveedores = [];
        $modelos = [];
        foreach ($carrito->getItems() as $presupuesto) {
            foreach ($presupuesto->getProductos() as $producto) {
                $modelo = $producto->getProducto()?->getModelo();
                if ($modelo) {
                    if (!isset($modelos[spl_object_hash($modelo)])) $modelos[spl_object_hash($modelo)] = $modelo;
                    if ($modelo->getProveedor() && !isset($proveedores[spl_object_hash($modelo->getProveedor())])) $proveedores[spl_object_hash($modelo->getProveedor())] = $modelo->getProveedor();
                }
            }
        }

        $html = $this->renderView('admin/supplier_order/pdf_request_form.html.twig', [
            'carrito' => $carrito,
            'fabricantes' => array_values($proveedores),
            'modelos' => array_values($modelos)
        ]);

        $pdfContent = $this->snappy->getOutputFromHtml($html);

        // --- INICIO DE LA LÓGICA MODIFICADA ---
        $fechaActual = new \DateTime();
        $fechaFormateada = $fechaActual->format('d-m-Y');

        $nombreFichero = sprintf('listado-%s.pdf', $fechaFormateada);
        $asuntoEmail = sprintf('Nuevo Pedido a Proveedor Generado - %s', $fechaFormateada);

        $email = (new Email())
            ->from('comercial@tuskamisetas.com') // Dirección del remitente
            ->to('joseluis@tuskamisetas.com')     // Tu dirección de correo
            ->subject($asuntoEmail)
            ->text('Se adjunta el PDF con el resumen del pedido a proveedor.');

        // Adjuntamos el PDF que acabamos de generar con el nuevo nombre
        $email->attach($pdfContent, $nombreFichero, 'application/pdf');

        // Enviamos el correo
        $this->mailer->send($email);

        $this->addFlash('sonata_flash_success', 'El PDF del pedido a proveedor ha sido enviado por email.');
        // --- FIN DE LA LÓGICA MODIFICADA ---

        // Devolvemos la respuesta para mostrar el PDF en el navegador, como antes
        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('inline; filename="%s"', $nombreFichero)
            ]
        );
    }

    // --- NUEVO MÉTODO PARA CONFIRMAR ---
    #[Route('/pedir-a-proveedor/confirmar', name: 'app_admin_supplier_order_confirm', methods: ['POST'])]
    public function confirmOrderAction(Request $request): Response
    {
        // MIGRACIÓN: Usamos el objeto Request en lugar de la variable superglobal $_POST
        $submittedData = $request->request;
        $pedidoIdsString = $submittedData->get('pedidos', '');

        if (empty($pedidoIdsString)) {
            $this->addFlash('error', 'No se han proporcionado pedidos para confirmar.');
            return $this->redirectToRoute('sonata_admin_dashboard');
        }

        $pedidoIds = explode(';', rtrim($pedidoIdsString, ';'));
        $estadoPedido = $this->em->getRepository(Estado::class)->find(5); // Asumiendo que el ID 5 es 'Pedido a Proveedor'

        if (!$estadoPedido) {
            $this->addFlash('error', 'No se ha encontrado el estado "Pedido a Proveedor" (ID 5).');
            return $this->redirectToRoute('sonata_admin_dashboard');
        }

        // Actualizar stock de inventario
        foreach ($submittedData->all() as $key => $value) {
            if ($key !== 'pedidos' && is_numeric($key) && is_numeric($value) && $value > 0) {
                $productoId = (int)$key;
                $cantidadARestar = (int)$value;

                // NOTA: Esta lógica asume que se resta stock del primer registro de inventario encontrado
                // para un producto. Puede necesitar un ajuste si manejas múltiples cajas.
                $inventarioItems = $this->em->getRepository(Inventario::class)->findBy(['producto' => $productoId]);
                if (!empty($inventarioItems)) {
                    $inventarioItems[0]->lessCantidad($cantidadARestar);
                }
            }
        }

        // Actualizar estado de los pedidos
        $pedidos = $this->em->getRepository(Pedido::class)->findBy(['id' => $pedidoIds]);
        foreach ($pedidos as $pedido) {
            $pedido->setEstado($estadoPedido);
        }

        $this->em->flush();

        $this->addFlash('sonata_flash_success', 'El pedido a proveedor ha sido procesado correctamente.');

        // MIGRACIÓN: Se redirige usando el nombre de la ruta del dashboard de Sonata.
        return $this->redirectToRoute('sonata_admin_dashboard');
    }

}
