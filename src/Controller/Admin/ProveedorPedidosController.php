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
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/pedidos')]
#[IsGranted('ROLE_ADMIN')]
class ProveedorPedidosController extends AbstractController
{
    private EntityManagerInterface $em;
    private Pdf $snappy;
    private MailerInterface $mailer;

    public function __construct(
        EntityManagerInterface $entityManager,
        Pdf                    $snappy,
        MailerInterface        $mailer
    ) {
        $this->em = $entityManager;
        $this->snappy = $snappy;
        $this->mailer = $mailer;
    }

    #[Route('/pedir-a-proveedor', name: 'app_admin_supplier_order_request')]
    public function requestOrderAction(SessionInterface $session): Response
    {
        // ... (Este método no necesita cambios)
        $pedidoRepo = $this->em->getRepository(Pedido::class);
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
                    if ($modelo && !isset($modelos[spl_object_hash($modelo)])) {
                        $modelos[spl_object_hash($modelo)] = $modelo;
                    }
                    if ($proveedor && !isset($proveedores[spl_object_hash($proveedor)])) {
                        $proveedores[spl_object_hash($proveedor)] = $proveedor;
                    }
                    $presupuestoProducto = new PresupuestoProducto();
                    $presupuestoProducto->setId($linea->getProducto()->getId());
                    $presupuestoProducto->setCantidad($linea->getCantidad());
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
            'fabricantes' => array_values($proveedores),
            'pedidos' => $cadenaPedidos,
            'arraypedidos' => $arraypedidos
        ]);
    }

    #[Route('/pedir-a-proveedor/pdf', name: 'app_admin_supplier_order_pdf')]
    public function pdfAction(SessionInterface $session): Response
    {
        $carrito = $session->get('pedirProveedor');
        if (!$carrito) {
            $this->addFlash('error', 'No se ha encontrado el carrito para generar el PDF.');
            return $this->redirectToRoute('app_admin_supplier_order_request');
        }

        // --- CAMBIO: Llamamos al nuevo método privado pero con envio a false---
        $pdfData = $this->generarYEnviarPdfProveedor($carrito, "", false);
        return new Response(
            $pdfData['pdfContent'],
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('inline; filename="%s"', $pdfData['nombreFichero'])
            ]
        );
    }

    // --- CAMBIO: Se añade SessionInterface para poder leer el carrito ---
    #[Route('/pedir-a-proveedor/confirmar', name: 'app_admin_supplier_order_confirm', methods: ['POST'])]
    public function confirmOrderAction(Request $request, SessionInterface $session): Response
    {
        // --- INICIO: Nueva lógica para enviar el email de confirmación ---
        $carrito = $session->get('pedirProveedor');
        if ($carrito) {
            $fechaFormateada = (new \DateTime())->format('d-m-Y');
            $asuntoEmail = sprintf('Pedido a Proveedor CONFIRMADO - %s', $fechaFormateada);
            $this->generarYEnviarPdfProveedor($carrito, $asuntoEmail);
            // Mensaje informativo para el admin
            $this->addFlash('sonata_flash_info', 'Se ha enviado un email de confirmación con el PDF del pedido.');
        } else {
            // Advertencia si no se puede enviar el email
            $this->addFlash('sonata_flash_warning', 'No se pudo enviar el email de confirmación porque no se encontró el carrito en la sesión.');
        }
        // --- FIN: Nueva lógica ---

        $submittedData = $request->request;
        $pedidoIdsString = $submittedData->get('pedidos', '');

        if (empty($pedidoIdsString)) {
            $this->addFlash('error', 'No se han proporcionado pedidos para confirmar.');
            return $this->redirectToRoute('sonata_admin_dashboard');
        }

        $pedidoIds = explode(';', rtrim($pedidoIdsString, ';'));
        $estadoPedido = $this->em->getRepository(Estado::class)->find(5);

        if (!$estadoPedido) {
            $this->addFlash('error', 'No se ha encontrado el estado "Pedido a Proveedor" (ID 5).');
            return $this->redirectToRoute('sonata_admin_dashboard');
        }

        foreach ($submittedData->all() as $key => $value) {
            if ($key !== 'pedidos' && is_numeric($key) && is_numeric($value) && $value > 0) {
                $productoId = (int)$key;
                $cantidadARestar = (int)$value;
                $inventarioItems = $this->em->getRepository(Inventario::class)->findBy(['producto' => $productoId]);
                if (!empty($inventarioItems)) {
                    $inventarioItems[0]->lessCantidad($cantidadARestar);
                }
            }
        }

        $pedidos = $this->em->getRepository(Pedido::class)->findBy(['id' => $pedidoIds]);
        foreach ($pedidos as $pedido) {
            $pedido->setEstado($estadoPedido);
        }

        $this->em->flush();

        $this->addFlash('sonata_flash_success', 'El pedido a proveedor ha sido procesado correctamente.');
        return $this->redirectToRoute('sonata_admin_dashboard');
    }

    /**
     * --- NUEVO MÉTODO PRIVADO ---
     * Genera el PDF a partir de un objeto Carrito y lo envía por email.
     * @return array con 'pdfContent' y 'nombreFichero'
     */
    private function generarYEnviarPdfProveedor(Carrito $carrito, string $asuntoEmail, ?bool $enviar = true): array
    {
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

        $html = $this->renderView('admin/pdf_pedir_a_proveedor.html.twig', [
            'carrito' => $carrito,
            'fabricantes' => array_values($proveedores),
            'modelos' => array_values($modelos)
        ]);

        $pdfContent = $this->snappy->getOutputFromHtml($html);
        $nombreFichero = sprintf('listado-%s.pdf', (new \DateTime())->format('d-m-Y'));

        if($enviar) {
            $email = (new Email())
                ->from('comercial@tuskamisetas.com')
                ->to('joseluis@tuskamisetas.com')
                ->addCc('informatica@tuskamisetas.com')
                ->subject($asuntoEmail)
                ->text('Se adjunta el PDF con el resumen del pedido a proveedor.')
                ->attach($pdfContent, $nombreFichero, 'application/pdf');

            $this->mailer->send($email);
        }
        return [
            'pdfContent' => $pdfContent,
            'nombreFichero' => $nombreFichero,
        ];
    }
}