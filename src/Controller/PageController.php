<?php
// src/Controller/PageController.php

namespace App\Controller;

use App\Entity\Empresa;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
// --- AÑADIR ESTAS IMPORTACIONES ---
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
// ----------------------------------
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// MIGRACIÓN: Se añade un prefijo de ruta a nivel de clase.
#[Route('/{_locale}', requirements: ['_locale' => 'es'])]
class PageController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    #[Route('/terminos-de-uso', name: 'app_terms_of_use')]
    public function termsOfUseAction(): Response
    {
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy([], ['id' => 'DESC']);

        if (!$empresa) {
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

    #[Route('/empresa', name: 'app_contact')]
    public function contactAction(): Response
    {
        return $this->render('web/page/contacto.html.twig');
    }

    #[Route('/solicitapresupuesto', name: 'app_solicita_presupuesto_page')]
    public function solicitaPresupuestoAction(): Response
    {
        return $this->render('web/page/solicita_presupuesto.html.twig');
    }

    #[Route('/solicita-presupuesto-lead', name: 'app_handle_quote_request', methods: ['POST'])]
    public function handleQuoteRequestAction(Request $request, MailerInterface $mailer): Response
    {
        $emailCliente = $request->request->get('_email');

        // Protección básica
        if ($emailCliente === "testing@example.com") {
            return $this->redirectToRoute('app_quote_request_success');
        }

        if (!filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
            // Podrías añadir un flash message aquí si quieres
            return $this->redirectToRoute('app_solicita_presupuesto_page');
        }

        $nombreEmpresa = $request->request->get('_empresa');
        $nombreCliente = $request->request->get('_nombre');
        $telefonoCliente = $request->request->get('_telefono');
        $ciudadCliente = $request->request->get('_ciudad');
        $observacionesCliente = $request->request->get('_observaciones');
        $fechaPedido = $request->request->get('_fecha');
        $cantidadPrendas = $request->request->get('_cantidad');

        $mensaje = "SOLICITUD DE PRESUPUESTO WEB\n";
        $mensaje .= "============================\n\n";
        $mensaje .= "Empresa: {$nombreEmpresa}\n";
        $mensaje .= "Nombre: {$nombreCliente}\n";
        $mensaje .= "Email: {$emailCliente}\n";
        $mensaje .= "Teléfono: {$telefonoCliente}\n";
        $mensaje .= "Población: {$ciudadCliente}\n\n";
        $mensaje .= "DETALLES DEL PEDIDO:\n";
        $mensaje .= "Cantidad: {$cantidadPrendas}\n";
        $mensaje .= "Fecha límite: {$fechaPedido}\n";
        $mensaje .= "Observaciones:\n{$observacionesCliente}\n";

        $email = (new Email())
            ->from('noreply@tuskamisetas.com') // Importante usar un remitente del dominio
            ->to('comercial@tuskamisetas.com')
            ->replyTo($emailCliente)
            ->subject('Presupuesto Web: ' . $nombreCliente . ' (' . $nombreEmpresa . ')')
            ->text($mensaje);

        $mailer->send($email);

        return $this->redirectToRoute('app_quote_request_success');
    }

    #[Route('/presupuesto-confirmado', name: 'app_quote_request_success')]
    public function quoteRequestSuccessAction(): Response
    {
        // Asegúrate de tener esta plantilla creada,
        // puedes reutilizar la de solicitud_trabajo_confirmado.html.twig adaptando los textos
        return $this->render('web/solicitud_trabajo_confirmado.html.twig', [
            'titulo' => '¡PRESUPUESTO RECIBIDO!',
            'texto' => 'Gracias por contactar con nosotros. Un comercial revisará tu solicitud y te enviará una valoración en las próximas 24 horas laborables.'
        ]);
    }
}