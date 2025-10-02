<?php

namespace App\Service;

use App\Entity\Pedido;
use App\Repository\EmpresaRepository;
use App\Service\FechaEntregaService;
use Knp\Snappy\Pdf;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class PedidoMailerService
{
    public function __construct(
        private MailerInterface   $mailer,
        private Environment       $twig,
        private Pdf               $knpSnappyPdf,
        private EmpresaRepository $empresaRepository, private readonly FechaEntregaService $fechaEntregaService
    ) {}

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
}