<?php
// src/Controller/PageController.php

namespace App\Controller;

use App\Entity\Empresa;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// MIGRACIÓN: Se añade un prefijo de ruta a nivel de clase.
// Todas las rutas dentro de este controlador empezarán por /es, /en, etc.
#[Route('/{_locale}', requirements: ['_locale' => 'es'])] // Puedes ajustar los idiomas soportados
class PageController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * MIGRACIÓN: Este método ahora obtiene la entidad Empresa de forma más eficiente
     * y maneja la versión normal de la página.
     */
    #[Route('/terminos-de-uso', name: 'app_terms_of_use')]
    public function termsOfUseAction(): Response
    {
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy([], ['id' => 'DESC']);

        if (!$empresa) {
            // Es una buena práctica manejar el caso en que la empresa no se encuentre
            throw $this->createNotFoundException('La configuración de la empresa no se ha encontrado.');
        }

        return $this->render('web/legal/texto_legal.html.twig', [
            'titulo' => "TÉRMINOS DE USO",
            'texto' => $empresa->getTextoLegal(),
            'descripcionSEO' => 'Términos de uso de tuskamisetas original s.l.l'
        ]);
    }

    #[Route('/privacidad', name: 'app_privacy_policy')]
    public function privacyAction(): Response
    {
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy([], ['id' => 'DESC']);

        if (!$empresa) {
            throw $this->createNotFoundException('La configuración de la empresa no se ha encontrado.');
        }

        return $this->render('web/legal/texto_legal.html.twig', [
            'titulo' => "POLÍTICA DE PRIVACIDAD",
            'texto' => $empresa->getTextoPrivacidad(),
            'descripcionSEO' => 'Política de privacidad y aviso legal de tuskamisetas original s.l.l'
        ]);
    }

    #[Route('/politica-cookies', name: 'app_cookies_policy')]
    public function cookiesPolicyAction(): Response
    {
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy([], ['id' => 'DESC']);

        if (!$empresa) {
            throw $this->createNotFoundException('La configuración de la empresa no se ha encontrado.');
        }

        return $this->render('web/legal/texto_legal.html.twig', [
            'titulo' => "POLÍTICA DE COOKIES",
            'texto' => $empresa->getPoliticaCookies(),
            'descripcionSEO' => 'Política de cookies de tuskamisetas original s.l.l'
        ]);
    }

    #[Route('/contacto', name: 'app_contact')]
    public function contactAction(): Response
    {
        // MIGRACIÓN: La acción ahora simplemente renderiza la plantilla de contacto.
        // La lógica del formulario se gestionará en otra acción si es necesario.
        return $this->render('web/page/contacto.html.twig');
    }

    #[Route('/solicita-presupuesto', name: 'app_solicita_presupuesto_page')]
    public function solicitaPresupuestoAction(): Response
    {
        return $this->render('web/page/solicita_presupuesto.html.twig');
    }

    #[Route('/solicita-presupuesto-lead', name: 'app_handle_quote_request', methods: ['POST'])]
    public function handleQuoteRequestAction(Request $request, MailerInterface $mailer): Response
    {
        $emailCliente = $request->request->get('_email');

        if ($emailCliente === "testing@example.com") {
            return $this->redirectToRoute('app_quote_request_success');
        }

        if (!filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
            return $this->redirectToRoute('app_solicita_presupuesto_page');
        }

        $nombreEmpresa = $request->request->get('_empresa');
        $nombreCliente = $request->request->get('_nombre');
        $telefonoCliente = $request->request->get('_telefono');
        $ciudadCliente = $request->request->get('_ciudad');
        $observacionesCliente = $request->request->get('_observaciones');
        $fechaPedido = $request->request->get('_fecha');
        $cantidadPrendas = $request->request->get('_cantidad');

        $mensaje = "Empresa: {$nombreEmpresa}\n";
        $mensaje .= "Nombre: {$nombreCliente}\n";
        $mensaje .= "e-mail: {$emailCliente}\n";
        $mensaje .= "Teléfono: {$telefonoCliente}\n";
        $mensaje .= "Población: {$ciudadCliente}\n";
        $mensaje .= "Observaciones: {$observacionesCliente}\n\n";
        $mensaje .= "Me gustaría obtener un presupuesto de {$cantidadPrendas} para {$fechaPedido}.";

        $email = (new Email())
            ->from('comercial@tuskamisetas.com')
            ->to('comercial@tuskamisetas.com')
            ->replyTo($emailCliente)
            ->subject('Solicitud de presupuesto desde web: ' . $emailCliente)
            ->text($mensaje);

        $mailer->send($email);

        return $this->redirectToRoute('app_quote_request_success');
    }

    #[Route('/presupuesto-confirmado', name: 'app_quote_request_success')]
    public function quoteRequestSuccessAction(): Response
    {
        return $this->render('web/page/quote_request_success.html.twig');
    }
}

