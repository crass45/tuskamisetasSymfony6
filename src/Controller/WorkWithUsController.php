<?php

namespace App\Controller;

use App\Form\Type\EmpresaTrabajaType;
use App\Form\Type\OperarioTrabajaType;
use App\Model\ContactoTrabaja;
use App\Model\EmpresaTrabaja;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email; // Importante para adjuntar archivos
use Symfony\Component\Routing\Annotation\Route;

class WorkWithUsController extends AbstractController
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    #[Route('/{_locale}/trabaja-con-nosotros', name: 'app_work_with_us', requirements: ['_locale' => 'es|en|fr'])]
    public function workWithUsAction(Request $request, int $empresaCheck = 0): Response
    {
        // ==========================================
        // 1. FORMULARIO PARA PARTICULARES (OPERARIO)
        // ==========================================
        $contacto = new ContactoTrabaja();
        $formContacto = $this->createForm(OperarioTrabajaType::class, $contacto);
        $formContacto->handleRequest($request);

        if ($formContacto->isSubmitted() && $formContacto->isValid()) {
            /** @var ContactoTrabaja $data */
            $data = $formContacto->getData();

            // Construcción del cuerpo del mensaje (TODOS LOS CAMPOS)
            $body = "SOLICITUD DE EMPLEO (PARTICULAR)\n";
            $body .= "================================\n\n";

            $body .= "DATOS PERSONALES:\n";
            $body .= "- Nombre: " . $data->getNombre() . "\n";
            $body .= "- Edad: " . $data->getEdad() . "\n";
            $body .= "- Teléfono: " . $data->getTelefono() . "\n";
            $body .= "- Email: " . $data->getEmail() . "\n";
            $body .= "- Provincia: " . $data->getProvincia() . "\n";
            $body .= "- País: " . $data->getPais() . "\n\n";

            $body .= "PERFIL PROFESIONAL:\n";
            $body .= "- Experiencia: " . $data->getExperiencia() . "\n";
            $body .= "- Empresas anteriores: " . $data->getEmpresas() . "\n\n";

            // Puestos deseados (Checkboxes)
            $puestos = [];
            if ($data->isOperario()) $puestos[] = 'Operario';
            if ($data->isComercial()) $puestos[] = 'Comercial';
            if ($data->isDesign()) $puestos[] = 'Diseñador Gráfico';

            $body .= "- Puesto solicitado: " . (empty($puestos) ? 'No especificado' : implode(', ', $puestos)) . "\n\n";

            $body .= "OBSERVACIONES:\n";
            $body .= ($data->getObservaciones() ?? 'Sin observaciones') . "\n";

            // Creación del Email
            $email = (new Email())
                ->from('noreply@tuskamisetas.com') // Remitente del sistema
                ->to('informatica@tuskamisetas.com') // Destinatario real (cámbialo por el tuyo)
                ->replyTo($data->getEmail())       // Para responder directamente al candidato
                ->subject('CV Recibido: ' . $data->getNombre())
                ->text($body);

            // --- ADJUNTAR CV (PDF/WORD) ---
            // Obtenemos el archivo subido desde el formulario
            $cvFile = $formContacto->get('cv')->getData();
            if ($cvFile) {
                // Lo adjuntamos al email usando su ruta temporal y nombre original
                $email->attachFromPath(
                    $cvFile->getPathname(),
                    $data->getNombre() . '_CV.' . $cvFile->guessExtension(),
                    $cvFile->getMimeType()
                );
            }

            $this->mailer->send($email);

            // Mensaje Flash de éxito
            $this->addFlash('success', 'Tu solicitud ha sido enviada correctamente.');
            return $this->render('web/solicitud_trabajo_confirmado.html.twig');
        }

        // ==========================================
        // 2. FORMULARIO PARA EMPRESAS (PROVEEDORES)
        // ==========================================
        $empresaTrabaja = new EmpresaTrabaja();
        $formEmpresa = $this->createForm(EmpresaTrabajaType::class, $empresaTrabaja);
        $formEmpresa->handleRequest($request);

        if ($formEmpresa->isSubmitted() && $formEmpresa->isValid()) {
            /** @var EmpresaTrabaja $data */
            $data = $formEmpresa->getData();

            // Construcción del cuerpo del mensaje
            $body = "OFERTA DE SERVICIOS (EMPRESA)\n";
            $body .= "=============================\n\n";

            $body .= "DATOS DE LA EMPRESA:\n";
            $body .= "- Nombre: " . $data->getNombre() . "\n";
            $body .= "- Teléfono: " . $data->getTelefono() . "\n";
            $body .= "- Email: " . $data->getEmail() . "\n";
            $body .= "- Provincia: " . $data->getProvincia() . "\n";
            $body .= "- País: " . $data->getPais() . "\n\n";

            $body .= "DETALLES DEL SERVICIO:\n";
            $body .= "- Experiencia: " . $data->getExperiencia() . "\n\n";

            // Servicios ofrecidos (Checkboxes)
            $servicios = [];
            if ($data->isSerigrafia()) $servicios[] = 'Serigrafía';
            if ($data->isTampografia()) $servicios[] = 'Tampografía';
            if ($data->isBordado()) $servicios[] = 'Bordado';
            if ($data->isDgt()) $servicios[] = 'Impresión Digital (DTG)';
            if ($data->isLaser()) $servicios[] = 'Láser';
            if ($data->isOtro()) $servicios[] = 'Otros';

            $body .= "- Servicios ofrecidos: " . (empty($servicios) ? 'No especificado' : implode(', ', $servicios)) . "\n\n";

            $body .= "OBSERVACIONES:\n";
            $body .= ($data->getObservaciones() ?? 'Sin observaciones') . "\n";

            $email = (new Email())
                ->from('noreply@tuskamisetas.com')
                ->to('informatica@tuskamisetas.com')
                ->replyTo($data->getEmail())
                ->subject('Propuesta de colaboración: ' . $data->getNombre())
                ->text($body);

            $this->mailer->send($email);

            $this->addFlash('success', 'Tu propuesta ha sido enviada correctamente.');
            return $this->render('web/solicitud_trabajo_confirmado.html.twig');
        }

        return $this->render('web/trabaja_con_nosotros.html.twig', [
            'form' => $formContacto->createView(),
            'formEmp' => $formEmpresa->createView(),
            'empresaCheck' => $empresaCheck
        ]);
    }
}