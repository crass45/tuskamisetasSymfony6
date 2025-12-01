<?php
// src/Controller/NewsletterController.php

namespace App\Controller;

use App\Entity\ListaCorreo;
use App\Form\Type\NewsletterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// CAMBIO CLAVE: Añadido el prefijo de idioma
#[Route('/{_locale}/newsletter', requirements: ['_locale' => 'es|en|fr'])]
class NewsletterController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * ACCIÓN 1: Página principal con formulario completo.
     */
    #[Route('/', name: 'app_newsletter_page')]
    public function newsletterPageAction(Request $request): Response
    {
        $suscripcion = new ListaCorreo();
        $form = $this->createForm(NewsletterType::class, $suscripcion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Verificamos si ya existe para no duplicar
            $existe = $this->em->getRepository(ListaCorreo::class)->findOneBy(['email' => $suscripcion->getEmail()]);

            if (!$existe) {
                try {
                    $this->em->persist($suscripcion);
                    $this->em->flush();
                } catch (\Exception $e) {
                    return $this->render('web/newsletter/error.html.twig');
                }
            }
            // Si ya existe o se acaba de guardar, mostramos éxito
            return $this->render('web/newsletter/success.html.twig');
        }

        return $this->render('web/newsletter/form.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * ACCIÓN 2: Suscripción rápida (Footer).
     */
    #[Route('/subscribe', name: 'app_newsletter_subscribe_simple', methods: ['POST'])]
    public function subscribeSimpleAction(Request $request): Response
    {
        try {
            $email = $request->request->get('newsletteremail');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->render('web/newsletter/error.html.twig');
            }

            // Verificamos si ya existe
            $existe = $this->em->getRepository(ListaCorreo::class)->findOneBy(['email' => $email]);

            if (!$existe) {
                $listaCorreo = new ListaCorreo();
                $listaCorreo->setEmail($email);

                $this->em->persist($listaCorreo);
                $this->em->flush();
            }

            // Si llegamos aquí es éxito (guardado o ya existente)

        } catch (\Exception $e) {
            return $this->render('web/newsletter/error.html.twig');
        }

        return $this->render('web/newsletter/success.html.twig');
    }

    /**
     * ACCIÓN 3: Desuscripción.
     */
    #[Route('/unsubscribe/{email}', name: 'app_newsletter_unsubscribe')]
    public function unsubscribeAction(string $email): Response
    {
        $listaCorreo = $this->em->getRepository(ListaCorreo::class)->findOneBy(['email' => $email]);

        if ($listaCorreo !== null) {
            $this->em->remove($listaCorreo);
            $this->em->flush();

            return $this->render('web/newsletter/success.html.twig');
        }

        return $this->render('web/newsletter/error.html.twig');
    }
}