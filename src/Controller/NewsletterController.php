<?php
// src/Controller/NewsletterController.php

namespace App\Controller;

use App\Entity\ListaCorreo;
use App\Entity\Newsletter;
use App\Form\Type\NewsletterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/newsletter')] // Prefijo para todas las acciones de este controlador
class NewsletterController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * ACCIÓN 1: Muestra la página completa de la newsletter con un formulario.
     */
    #[Route('/', name: 'app_newsletter_page')]
    public function newsletterPageAction(Request $request): Response
    {
        $newsletter = new Newsletter();
        $form = $this->createForm(NewsletterType::class, $newsletter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->em->persist($newsletter);
                $this->em->flush();
            } catch (\Exception) {
                return $this->render('web/newsletter/error.html.twig');
            }
            return $this->render('web/newsletter/success.html.twig');
        }

        return $this->render('web/newsletter/form.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * ACCIÓN 2: Procesa la suscripción desde un formulario simple (ej. en el footer).
     */
    #[Route('/subscribe', name: 'app_newsletter_subscribe_simple', methods: ['POST'])]
    public function subscribeSimpleAction(Request $request): Response
    {
        try {
            $email = $request->request->get('newsletteremail');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Email no válido.');
            }

            // Usamos la entidad ListaCorreo como en tu código original para esta acción
            $listaCorreo = new ListaCorreo();
            $listaCorreo->setEmail($email);

            $this->em->persist($listaCorreo);
            $this->em->flush();

        } catch (\Exception) {
            return $this->render('web/newsletter/error.html.twig');
        }

        return $this->render('web/newsletter/success.html.twig');
    }

    /**
     * ACCIÓN 3: Procesa la desuscripción desde un enlace.
     */
    #[Route('/unsubscribe/{email}', name: 'app_newsletter_unsubscribe')]
    public function unsubscribeAction(string $email): Response
    {
        $listaCorreo = $this->em->getRepository(ListaCorreo::class)->findOneBy(['email' => $email]);

        if ($listaCorreo !== null) {
            $this->em->remove($listaCorreo);
            $this->em->flush();

            // Éxito en la desuscripción
            return $this->render('web/newsletter/success.html.twig');
        }

        // El email no se encontró
        return $this->render('web/newsletter/error.html.twig');
    }
}
