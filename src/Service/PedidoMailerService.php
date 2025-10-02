<?php

namespace App\Service;

use App\Entity\Pedido;
use App\Repository\EmpresaRepository;
use App\Service\FechaEntregaService;
use Knp\Snappy\Pdf;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class PedidoMailerService
{
    public function __construct(
        private MailerInterface   $mailer,
        private Environment       $twig,
        private Pdf               $knpSnappyPdf,
        private EmpresaRepository $empresaRepository,
        private FechaEntregaService $fechaEntregaService,
        private UrlGeneratorInterface $urlGenerator
    ) {}


    /**
     * Envía el correo de confirmación inicial cuando se crea un pedido.
     */
    public function sendNewOrderConfirmationEmail(Pedido $pedido): void
    {
        $groupedLines = $this->groupLinesByCustomization($pedido);
        $empresa = $this->empresaRepository->findOneBy([], ['id' => 'DESC']);

        $email = (new TemplatedEmail())
            ->from('comercial@tuskamisetas.com')
            ->to($pedido->getContacto()->getEmail())
            ->subject('Hemos recibido tu solicitud de presupuesto #' . $pedido->getNombre())
            ->htmlTemplate('emails/correoPedido.html.twig')
            ->context([
                'pedido' => $pedido,
                'grouped_lines' => $groupedLines,
                'empresa' => $empresa,
                'emailTitulo' => 'Solicitud de Presupuesto Recibida',
                'emailSubtitulo' => '',
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envía un correo de notificación al equipo comercial sobre un nuevo pedido.
     */
    public function sendAdminNotificationEmail(Pedido $pedido): void
    {
        $adminUrl = $this->urlGenerator->generate('admin_app_pedido_edit', [
            'id' => $pedido->getId()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from('comercial@tuskamisetas.com')
            ->to('comercial@tuskamisetas.com')
            ->bcc('info@tuskamisetas.com')
            ->subject('Nuevo Pedido en la Web: #' . $pedido->getNombre())
            ->htmlTemplate('emails/correoPedidoAdmin.html.twig')
            ->context([
                'pedido' => $pedido,
                'admin_url' => $adminUrl,
                'emailTitulo' => '¡Nuevo pedido en la tienda!',
                'emailSubtitulo' => '',
            ]);

        $this->mailer->send($email);
    }

    public function sendEmailForStatus(Pedido $pedido): bool
    {
        $estado = $pedido->getEstado();
        $user = $pedido->getContacto();
        $empresa = $this->empresaRepository->findOneBy([], ['id' => 'DESC']);

        if (!$estado || !$user || !$estado->getEnviaCorreo()) {
            return false;
        }

        $template = $this->getTemplateForStatus($pedido);
        if (!$template) {
            return false;
        }

        $asunto = 'Pedido ' . $pedido->getNombre();
        $email = (new TemplatedEmail())
            ->from('comercial@tuskamisetas.com')
            ->to($user->getEmail())
            ->subject($asunto)
            ->htmlTemplate($template['path']);

        // Adjuntar factura si es del tipo Enviacorreo 3
        if ($estado->getEnviaCorreo() == 3 && $pedido->getFactura()) {
            $htmlFactura = $this->twig->render('plantilla_pdf/factura.html.twig', ['pedido' => $pedido]);
            $this->knpSnappyPdf->setOption('footer-html', $this->twig->render('plantilla_pdf/footer.html.twig'));
            $pdfData = $this->knpSnappyPdf->getOutputFromHtml($htmlFactura);

            $email->attach($pdfData, 'factura-' . $pedido->getFactura()->getNumeroFactura() . '.pdf', 'application/pdf');
        }

        // Aquí puedes añadir más lógica para pasar variables específicas a cada plantilla si es necesario
        // === LÓGICA DE CÁLCULO DE FECHAS (MOVIMOS LA LÓGICA AQUÍ) ===
        $templateContext = [
            'pedido' => $pedido,
            'empresa' => $empresa,
            'emailTitulo' => $template['title'],
            'emailSubtitulo' => $template['subtitulo'],
        ];

        // Si es el email de "Pedido Revisado", calculamos y añadimos las fechas de entrega
        if ($estado->getEnviaCorreo() == 2) {
            $diasAdicionales = $pedido->getDiasAdicionales() ?? 0;

//            $diasNormalMin = ($pedido->compruebaTrabajos() ? $empresa->getMinimoDiasConImpresion() : $empresa->getMinimoDiasSinImprimir()) + $diasAdicionales;
//            $diasNormalMax = ($pedido->compruebaTrabajos() ? $empresa->getMaximoDiasConImpresion() : $empresa->getMaximoDiasSinImprimir()) + $diasAdicionales;
//            $diasExpress = ($empresa->getMinimoDiasSinImprimir() ?? 10) + 3 + $diasAdicionales;

            $fechaEntregaPedido= $this->fechaEntregaService->getFechasEntregaPedido($pedido,new \DateTime());


            $templateContext['fechaEntregaNormalMin'] = $fechaEntregaPedido['min'];
            $templateContext['fechaEntregaNormalMax'] = $fechaEntregaPedido['max'];
            $templateContext['fechaEntregaExpress'] = $fechaEntregaPedido['express'];
            $templateContext['grouped_lines'] = $this->groupLinesByCustomization($pedido);
        }

        $email->context($templateContext);

        $this->mailer->send($email);

        return true;
    }

    private function getTemplateForStatus(Pedido $pedido): ?array
    {

        $status = $pedido->getEstado()->getEnviaCorreo();

        // Lógica especial para el estado "Revisado" (ID 2)
        if ($status == 2) {
            if ($pedido->compruebaTrabajos()) {
                // Si tiene personalización, usa la plantilla con montaje
                return ['path' => 'emails/pedido_revisado.html.twig', 'title' => '¡Pedido Revisado!','subtitulo'=>''];
            } else {
                // Si NO tiene personalización, usa la nueva plantilla
                return ['path' => 'emails/pedido_revisado_sin_impresion.html.twig', 'title' => '¡Pedido Revisado!','subtitulo'=>''];
            }
        }
        $templates = [
            // El número es el valor de 'enviaCorreo' en tu entidad Estado
//            2 => ['path' => 'emails/pedido_revisado.html.twig', 'title' => '¡Pedido Revisado!', 'subtitulo' => ''],
            3 => ['path' => 'emails/pedido_enviado.html.twig', 'title' => '¡Pedido Enviado!','subtitulo' => ''],
            4 => ['path' => 'emails/pedido_incidencias.html.twig', 'title' => '¡Pedido Con Incidencias!','subtitulo' => ''],
            5 => ['path' => 'emails/pedido_falta_logo.html.twig', 'title' => '¡Falta Logo!','subtitulo' => ''],
            6 => ['path' => 'emails/pedido_preparado.html.twig', 'title' => '¡Pedido Preparado!','subtitulo' => ''],
        ];

        return $templates[$status] ?? null;
    }

    /**
     * Agrupa las líneas del pedido por su personalización para la visualización en el correo.
     * (Este método ha sido movido desde OrderService)
     */
    private function groupLinesByCustomization(Pedido $pedido): array
    {
        $groupedLines = [];
        foreach ($pedido->getLineas() as $linea) {
            // Usamos una clave única para agrupar (incluso si no hay personalización)
            $key = $linea->getPersonalizacion() ?? 'sin_personalizacion';
            $groupedLines[$key][] = $linea;
        }
        return $groupedLines;
    }
}