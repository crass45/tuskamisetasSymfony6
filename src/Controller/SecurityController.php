<?php
// src/Controller/SecurityController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * MIGRACIÓN: Esta acción se encarga de renderizar el formulario de login.
     * La lógica de procesar el login ahora la gestiona el firewall de Symfony.
     * Esta acción se usa para el modal de login.
     */
    #[Route('/modal-login', name: 'app_modal_login')]
    public function modalLoginAction(AuthenticationUtils $authenticationUtils): Response
    {
        // Obtiene el error de login si lo hay (desde el intento anterior)
        $error = $authenticationUtils->getLastAuthenticationError();

        // Obtiene el último nombre de usuario introducido
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/modal_login.html.twig', ['loginAction',
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
}
