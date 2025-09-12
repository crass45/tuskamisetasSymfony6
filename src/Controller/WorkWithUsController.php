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
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

// MIGRACIÓN: Se crea un controlador especializado para la funcionalidad de "Trabaja con Nosotros".
class WorkWithUsController extends AbstractController
{
    private MailerInterface $mailer;

    // MIGRACIÓN: El servicio Mailer se inyecta en el constructor.
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    #[Route('/trabaja-con-nosotros/{empresaCheck}', name: 'app_work_with_us', defaults: ['empresaCheck' => 0])]
    public function workWithUsAction(Request $request, int $empresaCheck = 0): Response
    {
        // --- Formulario para Contacto (Operario) ---
        $contacto = new ContactoTrabaja();
        $formContacto = $this->createForm(OperarioTrabajaType::class, $contacto);
        $formContacto->handleRequest($request);

        if ($formContacto->isSubmitted() && $formContacto->isValid()) {
            // MIGRACIÓN: Se obtienen los datos del objeto rellenado por el formulario.
            $data = $formContacto->getData();

            $body = "Se ha recibido una nueva solicitud de empleo como trabajador.\n\n";
            $body .= "Nombre: " . $data->getNombre() . "\n";
            $body .= "Teléfono: " . $data->getTelefono() . "\n";
            $body .= "E-mail: " . $data->getEmail() . "\n";
            // ... (resto de campos del cuerpo del email)

            // MIGRACIÓN: Se usa la nueva clase Email y el servicio mailer inyectado.
            $email = (new Email())
                ->from('comercial@tuskamisetas.com')
                ->to('tuskamisetas@gmail.com')
                ->subject('Nueva solicitud de empleo')
                ->text($body);
            $this->mailer->send($email);

            return $this->render('web/solicitud_trabajo_confirmado.html.twig');
        }

        // --- Formulario para Empresa ---
        $empresaTrabaja = new EmpresaTrabaja();
        $formEmpresa = $this->createForm(EmpresaTrabajaType::class, $empresaTrabaja);
        $formEmpresa->handleRequest($request);

        if ($formEmpresa->isSubmitted() && $formEmpresa->isValid()) {
            $data = $formEmpresa->getData();

            $body = "Se ha recibido una nueva solicitud de prestación de servicios externos.\n\n";
            $body .= "Nombre: " . $data->getNombre() . "\n";
            // ... (resto de campos del cuerpo del email)

            $email = (new Email())
                ->from('comercial@tuskamisetas.com')
                ->to('tuskamisetas@gmail.com')
                ->subject('Nueva solicitud de servicios externos')
                ->text($body);
            $this->mailer->send($email);

            return $this->render('web/solicitud_trabajo_confirmado.html.twig');
        }

        // MIGRACIÓN: Las rutas de las plantillas ahora apuntan a la carpeta /templates.
        return $this->render('web/trabaja_con_nosotros.html.twig', [
            'form' => $formContacto->createView(),
            'formEmp' => $formEmpresa->createView(),
            'empresaCheck' => $empresaCheck
        ]);
    }
}
