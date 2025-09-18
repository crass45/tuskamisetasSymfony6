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
    /**
     * Esta es la acción principal del login. Es el endpoint que procesa el envío del formulario.
     * Al definir esta ruta, se soluciona el error.
     */
    #[Route(path: '/{_locale}/login', name: 'app_login', requirements: ['_locale' => 'es|en|fr'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si el usuario ya ha iniciado sesión, redirigirlo a la home
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home', ['_locale' => $this->get('request_stack')->getCurrentRequest()->getLocale()]);
        }

        // Obtener el error de login si lo hay
        $error = $authenticationUtils->getLastAuthenticationError();
        // Último nombre de usuario introducido
        $lastUsername = $authenticationUtils->getLastUsername();

        // Esta plantilla se usaría si tuvieras una página de login completa,
        // pero la ruta es necesaria para que el formulario del modal funcione.
        return $this->render('security/login_page.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * Esta acción se usa para cerrar la sesión.
     */
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Esta acción puede estar vacía. El firewall de seguridad la interceptará.
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Muestra la página para solicitar el reseteo de contraseña.
     */
    #[Route('/{_locale}/resetting-password-request', name: 'app_forgot_password_request', requirements: ['_locale' => 'es|en|fr'])]
    public function requestPasswordResetAction(): Response
    {
        // TODO: Implementar el formulario y la lógica para enviar el email de reseteo.
        // Por ahora, solo renderizamos la plantilla placeholder que ya creamos.
        return $this->render('security/reset_password.html.twig');
    }

    /**
     * Muestra y procesa el formulario de registro.
     */
    #[Route('/{_locale}/register', name: 'app_register', requirements: ['_locale' => 'es|en|fr'])]
    public function registerAction(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        $user = new User();
        $form = $this->createForm(RegistroFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Codificar la contraseña
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', '¡Registro completado! Ahora puedes iniciar sesión.');

            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        return $this->render('security/register.html.twig', [
            'registration_form' => $form->createView(),
        ]);
    }
}
