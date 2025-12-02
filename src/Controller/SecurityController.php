<?php
// src/Controller/SecurityController.php

namespace App\Controller;

use App\Entity\Sonata\User;
use App\Form\Type\ChangePasswordFormType;
use App\Form\Type\RegistroFormType;
use App\Form\Type\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface; // <-- Se asegura de que se usa la interfaz correcta de Symfony
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class SecurityController extends AbstractController
{

    use ResetPasswordControllerTrait;

    private EntityManagerInterface $em;
    private ResetPasswordHelperInterface $resetPasswordHelper;

    public function __construct(
        EntityManagerInterface $entityManager,
        ResetPasswordHelperInterface $resetPasswordHelper
    ) {
        $this->em = $entityManager;
        $this->resetPasswordHelper = $resetPasswordHelper;
    }
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
     * Esta acción renderiza el panel de bienvenida que se muestra
     * en el modal cuando el usuario ya ha iniciado sesión.
     */
    #[Route('/_modal-profile', name: 'app_modal_profile')]
    #[IsGranted('ROLE_USER')]
    public function modalShowAction(): Response
    {
        // Esta acción no necesita lógica, solo renderizar la plantilla.
        // Symfony se encarga de pasar el objeto 'app.user' a la plantilla.
        return $this->render('profile/_modal_show.html.twig');
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
     * Muestra y procesa el formulario para solicitar un reseteo de contraseña.
     */
    #[Route('/{_locale}/resetting-password-request', name: 'app_forgot_password_request', requirements: ['_locale' => 'es|en|fr'])]
    public function requestPasswordResetAction(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $emailAddress = $form->get('email')->getData();
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $emailAddress]);

            // Si se encuentra el usuario, se genera el token y se envía el email
            if ($user) {
                try {
                    $resetToken = $this->resetPasswordHelper->generateResetToken($user);

                    $email = (new TemplatedEmail())
                        ->from(new Address('comercial@tuskamisetas.com', 'TusKamisetas.com'))
                        ->to($user->getEmail())
                        ->subject('Tu solicitud para restablecer la contraseña')
                        ->htmlTemplate('emails/reset_password.html.twig')
                        ->context(['resetToken' => $resetToken]);

                    $mailer->send($email);

                } catch (ResetPasswordExceptionInterface $e) {
                    // No revelamos si el usuario no fue encontrado para mayor seguridad
                    $this->addFlash('success', 'Se ha enviado un correo con las instrucciones si la dirección existe en nuestra base de datos. FALSO'.$e->getReason());
                    return $this->redirectToRoute('app_forgot_password_request');
                }
            }

            $this->addFlash('success', 'Se ha enviado un correo con las instrucciones si la dirección existe en nuestra base de datos.');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        return $this->render('security/reset_password.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    /**
     * Valida el token del email y muestra el formulario para cambiar la contraseña.
     */
    #[Route('/{_locale}/reset-password/{token}', name: 'app_reset_password', requirements: ['_locale' => 'es|en|fr'])]
    public function resetAction(Request $request, UserPasswordHasherInterface $passwordHasher, string $token = null): Response
    {
        if ($token) {
            // Guardamos el token en la sesión y redirigimos para limpiar la URL.
            $this->storeTokenInSession($token);
            return $this->redirectToRoute('app_reset_password', ['_locale' => $request->getLocale()]);
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No se ha encontrado ningún token de reseteo en la URL o en la sesión.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('error', sprintf(
                'Ha ocurrido un problema al validar tu solicitud - %s',
                $e->getReason()
            ));
            return $this->redirectToRoute('app_forgot_password_request');
        }

        // El token es válido. Permitimos al usuario cambiar su contraseña.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // El token se elimina automáticamente al usarse con éxito
            $this->resetPasswordHelper->removeResetRequest($token);

            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->em->flush();

            // Limpiamos la sesión después de cambiar la contraseña.
            $this->cleanSessionAfterReset();

            $this->addFlash('success', '¡Contraseña actualizada! Ya puedes iniciar sesión.');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        return $this->render('security/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
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
            $user->setEnabled(true);

            $user->setUsername($form->get('email')->getData());
            $user->setEmail($form->get('email')->getData());
            $user->setRoles(['ROLE_USER']);
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
