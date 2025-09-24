<?php
// src/Controller/ProfileController.php

namespace App\Controller;

use App\Entity\Contacto;
use App\Entity\Direccion;
use App\Entity\Pedido;
use App\Form\Type\ContactoType;
use App\Form\Type\DireccionType;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Form\Type\DireccionEnvioType; // Se importa el FormType de envío
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// MIGRACIÓN: Se protege el controlador entero para que solo los usuarios logueados puedan acceder
#[Route('/{_locale}/usuario', requirements: ['_locale' => 'es|en|fr'])]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    private EntityManagerInterface $em;
    private Pdf $snappy;

    public function __construct(EntityManagerInterface $entityManager, Pdf $snappy)
    {
        $this->em = $entityManager;
        $this->snappy = $snappy;
    }

    #[Route('/mi-perfil', name: 'app_profile_edit')]
    public function editProfileAction(Request $request): Response
    {
        // MIGRACIÓN: $this->getUser() devuelve tu entidad App\Entity\Sonata\User
        $user = $this->getUser();
        $contacto = $this->em->getRepository(Contacto::class)->findOneBy(['usuario' => $user]);

        if (!$contacto) {
            $contacto = new Contacto();
            $contacto->setDireccionFacturacion(new Direccion());
            $contacto->setUsuario($user);
        }

        $form = $this->createForm(ContactoType::class, $contacto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contacto);
            $this->em->flush();

            $this->addFlash('success', '¡Tu perfil ha sido actualizado!');

            return $this->redirectToRoute('app_profile_edit', ['_locale' => $request->getLocale()]);
        }

        return $this->render('profile/edit.html.twig', [
            'contacto' => $contacto,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/mis-pedidos', name: 'app_profile_orders')]
    public function listOrdersAction(): Response
    {
        $user = $this->getUser();
        $contacto = $this->em->getRepository(Contacto::class)->findOneBy(['usuario' => $user]);
        $pedidos = [];
        if ($contacto) {
            // MIGRACIÓN: Se usa la propiedad 'contacto' que renombramos en la entidad Pedido
            $pedidos = $this->em->getRepository(Pedido::class)->findBy(['contacto' => $contacto], ['fecha' => 'DESC']);
        }

        return $this->render('profile/list_orders.html.twig', ['pedidos' => $pedidos]);
    }

    #[Route('/pedido/{id}/pdf', name: 'app_profile_order_pdf')]
    public function showOrderPdfAction(Pedido $pedido): Response
    {
        // MIGRACIÓN: Se añade una comprobación de seguridad para que un usuario no pueda ver pedidos de otro
        if ($pedido->getContacto()?->getUsuario() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $html = $this->renderView('plantilla_pdf/presupuesto.html.twig', ['pedido' => $pedido]);

        return new Response(
            $this->snappy->getOutputFromHtml($html),
            200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="presupuesto.pdf"']
        );
    }

    #[Route('/factura/{id}/pdf', name: 'app_profile_invoice_pdf')]
    public function showInvoicePdfAction(Pedido $pedido): Response
    {
        if ($pedido->getContacto()?->getUsuario() !== $this->getUser() || !$pedido->getFactura()) {
            throw $this->createAccessDeniedException();
        }

        $factura = $pedido->getFactura();
        $html = $this->renderView('plantilla_pdf/factura.html.twig', ['pedido' => $pedido]);
        $filename = sprintf('factura-%s.pdf', $factura->getNombre());

        return new Response(
            $this->snappy->getOutputFromHtml($html),
            200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $filename . '"']
        );
    }

    /**
     * MIGRACIÓN: Esta es la acción que antes se llamaba dirEntregaAction
     */
    #[Route('/direccion/{id?}', name: 'app_profile_address')]
    public function addressAction(Request $request, ?Direccion $direccion = null): Response
    {
        if (!$direccion) {
            $direccion = new Direccion();
        }

        // NOTA: Para que esto funcione, necesitamos migrar la clase DireccionType1.php
        $form = $this->createForm(DireccionType::class, $direccion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Lógica para guardar la dirección y asociarla al contacto...
            $this->em->persist($direccion);
            $this->em->flush();
            return $this->redirectToRoute('app_profile_addresses'); // Redirige a la lista de direcciones
        }

        return $this->render('profile/address_form.html.twig', [
            'formEnvio' => $form->createView()
        ]);
    }

    // --- INICIO DE LOS NUEVOS MÉTODOS ---

    /**
     * Muestra y procesa el formulario para AÑADIR o EDITAR una dirección de envío.
     * Se llama desde la plantilla 'edit.html.twig' y se renderiza en un modal.
     */
    #[Route('/address/save/{id?}', name: 'app_profile_save_address')]
    public function saveAddressAction(Request $request, ?Direccion $direccion): Response
    {
        $user = $this->getUser();
        $contacto = $this->em->getRepository(Contacto::class)->findOneBy(['usuario' => $user]);
        $isNew = false;

        if (!$direccion) {
            $direccion = new Direccion();
            $direccion->setIdContacto($contacto);
            $isNew = true;
        }

        // Comprobación de seguridad: asegúrate de que el usuario está editando su propia dirección
        if (!$isNew && $direccion->getIdContacto() !== $contacto) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(DireccionEnvioType::class, $direccion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($direccion);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'Nueva dirección guardada.' : 'Dirección actualizada.');
            return $this->redirectToRoute('app_profile_edit', ['_locale' => $request->getLocale()]);
        }

        // Esta plantilla solo se usa para mostrar el formulario dentro del modal
        return $this->render('profile/partials/_address_form_modal.html.twig', [
            'addressForm' => $form->createView(),
        ]);
    }

    /**
     * Elimina una dirección de envío del perfil del usuario.
     * Si la dirección ha sido usada en pedidos, se desvincula en lugar de borrarse.
     */
    #[Route('/address/delete/{id}', name: 'app_profile_delete_address', methods: ['POST'])]
    public function deleteAddressAction(Request $request, Direccion $direccion): Response
    {
        // Comprobación de seguridad
        if ($direccion->getIdContacto()?->getUsuario() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Validación del token CSRF
        if ($this->isCsrfTokenValid('delete'.$direccion->getId(), $request->request->get('_token'))) {
            // Se comprueba si la dirección está asociada a algún pedido existente.
            $pedidosAsociados = $this->em->getRepository(Pedido::class)->findBy(['direccion' => $direccion]);

            if (count($pedidosAsociados) > 0) {
                // Si está asociada, la desvinculamos del contacto en lugar de borrarla.
                $direccion->setIdContacto(null);
                $this->em->persist($direccion);
                $this->addFlash('notice', 'La dirección ha sido archivada. Ya no aparecerá en tu lista, pero se conservará en tus pedidos anteriores.');
            } else {
                // Si no está asociada a ningún pedido, la eliminamos de forma segura.
                $this->em->remove($direccion);
                $this->addFlash('notice', 'La dirección ha sido eliminada correctamente.');
            }

            $this->em->flush();
        }

        return $this->redirectToRoute('app_profile_edit', ['_locale' => $request->getLocale()]);
    }

    // --- FIN DE LOS NUEVOS MÉTODOS ---
}
