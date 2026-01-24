<?php

namespace App\Controller;

use App\Entity\Sonata\User;
use App\Form\Type\ChangePasswordFormType;
use App\Form\Type\ResetPasswordRequestFormType;
use App\Form\Type\RegistroFormType; // Asegúrate de usar tu formulario correcto
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
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
     * Método auxiliar para calcular a dónde redirigir después de login/registro.
     */
    private function getSmartTargetPath(Request $request): ?string
    {
        // 1. ¿Ya viene forzado por la URL (ej: ?_target_path=...)?
        $targetPath = $request->get('_target_path');

        // 2. Si no, miramos el Referer (de dónde venía)
        if (!$targetPath && $request->isMethod('GET')) {
            $referer = $request->headers->get('referer');
            if ($referer && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
                $path = substr($referer, strlen($request->getSchemeAndHttpHost()));
                // Evitamos bucles con páginas de autenticación
                if (!preg_match('#/(login|register|reset|logout|modal-login)#', $path)) {
                    $targetPath = $path;
                }
            }
        }

        // 3. Lógica Especial: Si venía de Checkout, mandarlo al Carrito
        if ($targetPath && str_contains($targetPath, 'checkout')) {
            return $this->generateUrl('app_cart_show', ['_locale' => $request->getLocale()]);
        }

        return $targetPath;
    }

    #[Route('/modal-login', name: 'app_modal_login')]
    public function modalLoginAction(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        // Calculamos el destino inteligente
        $targetPath = $this->getSmartTargetPath($request);

        return $this->render('security/modal_login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'targetPath' => $targetPath, // Pasamos la variable a la plantilla
        ]);
    }

    #[Route('/_modal-profile', name: 'app_modal_profile')]
    #[IsGranted('ROLE_USER')]
    public function modalShowAction(): Response
    {
        return $this->render('profile/_modal_show.html.twig');
    }

    #[Route(path: '/{_locale}/login', name: 'app_login', requirements: ['_locale' => 'es|en|fr'])]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            // FIX: Pasamos todos los parámetros (gclid, utm, etc) a la home
            $params = array_merge(
                $request->query->all(),
                ['_locale' => $request->getLocale()]
            );
            return $this->redirectToRoute('app_home', $params);
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        // Calculamos el destino inteligente
        $targetPath = $this->getSmartTargetPath($request);

        return $this->render('security/login_page.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'targetPath' => $targetPath, // Pasamos la variable a la plantilla
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/{_locale}/resetting-password-request', name: 'app_forgot_password_request', requirements: ['_locale' => 'es|en|fr'])]
    public function requestPasswordResetAction(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $emailAddress = $form->get('email')->getData();
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $emailAddress]);

            if ($user) {
                try {
                    $resetToken = $this->resetPasswordHelper->generateResetToken($user);
                    $email = (new TemplatedEmail())
                        ->from(new Address('comercial@tuskamisetas.com', 'TusKamisetas.com'))
                        ->to($user->getEmail())
                        ->subject('Restablecer contraseña')
                        ->htmlTemplate('emails/reset_password.html.twig')
                        ->context(['resetToken' => $resetToken]);

                    $mailer->send($email);
                } catch (ResetPasswordExceptionInterface $e) {
                    // Silencioso por seguridad
                }
            }
            $this->addFlash('success', 'Si el correo existe, recibirás instrucciones.');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        return $this->render('security/reset_password.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/{_locale}/reset-password/{token}', name: 'app_reset_password', requirements: ['_locale' => 'es|en|fr'])]
    public function resetAction(Request $request, UserPasswordHasherInterface $passwordHasher, string $token = null): Response
    {
        if ($token) {
            $this->storeTokenInSession($token);
            return $this->redirectToRoute('app_reset_password', ['_locale' => $request->getLocale()]);
        }
        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No se ha encontrado token.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('error', sprintf('Error validando solicitud: %s', $e->getReason()));
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);
            $encodedPassword = $passwordHasher->hashPassword($user, $form->get('plainPassword')->getData());
            $user->setPassword($encodedPassword);
            $this->em->flush();
            $this->cleanSessionAfterReset();

            $this->addFlash('success', '¡Contraseña actualizada!');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        return $this->render('security/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    #[Route('/{_locale}/register', name: 'app_register', requirements: ['_locale' => 'es|en|fr'])]
    public function registerAction(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher
    ): Response
    {
        // Usamos el mismo método inteligente
        $targetPath = $this->getSmartTargetPath($request);

        $user = new User();
        $form = $this->createForm(RegistroFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUsername($user->getEmail());
            $user->setEmail($user->getEmail());
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $user->setEnabled(true);
            $user->setRoles(['ROLE_USER']);

            $em->persist($user);
            $em->flush();

            // Auto-Login
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $tokenStorage->setToken($token);
            $request->getSession()->set('_security_main', serialize($token));
            $event = new InteractiveLoginEvent($request, $token);
            $eventDispatcher->dispatch($event, SecurityEvents::INTERACTIVE_LOGIN);

            $this->addFlash('success', '¡Registro completado!');

            // Redirección inteligente
            if ($targetPath) {
                return $this->redirect($targetPath);
            }
            return $this->redirectToRoute('app_home', ['_locale' => $request->getLocale()]);
        }

        return $this->render('security/register.html.twig', [
            'registration_form' => $form->createView(),
            'targetPath' => $targetPath,
        ]);
    }
}