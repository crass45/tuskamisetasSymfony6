<?php

namespace App\Controller;

use App\Entity\BannerHome;
use App\Entity\GastosEnvio;
use App\Entity\Modelo;
use App\Entity\Sonata\ClassificationCategory;
use App\Model\Carrito;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * Esta acción maneja la raíz del sitio web ('/').
     * Redirige al usuario a la página de inicio con el prefijo de idioma.
     */
    #[Route('/', name: 'app_root_redirect')]
    public function rootRedirectAction(Request $request): Response
    {
        return $this->redirectToRoute('app_home', ['_locale' => $request->getLocale()]);
    }

    /**
     * Esta acción muestra la página de inicio.
     * Carga los banners, categorías y productos destacados.
     * También inicializa el carrito en la sesión si no existe.
     *
     * @param SessionInterface $session
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route('/{_locale}', name: 'app_home', requirements: ['_locale' => 'es|en|fr'], priority: 1)] // Adapta los idiomas a los que necesites
    public function homeAction(SessionInterface $session, EntityManagerInterface $em): Response
    {
        // MIGRACIÓN: Se elimina la sesión de filtros al entrar en la home.
        $session->remove('filtros');

        $carrito = $session->get('carrito');

        if ($carrito === null) {
            $carrito = new Carrito();
            $gastosEnvioIniciales = $em->getRepository(GastosEnvio::class)->findOneBy(['codigoPostal' => '30']);

            if ($gastosEnvioIniciales) {
                // MIGRACIÓN: Corregido para usar solo el precio reducido como en tu lógica.
                $carrito->getPrecioGastos($gastosEnvioIniciales->getPrecioReducido());
            } else {
                $carrito->setPrecioGastos(0);
            }

            $session->set('carrito', $carrito);
        }

        $banners = $em->getRepository(BannerHome::class)->findBy(['activo' => true], ['orden' => 'ASC']);
        $categoriasHome = $em->getRepository(ClassificationCategory::class)->findBy(['aparece_home' => true], ['position' => 'ASC']);

        // MIGRACIÓN: Ahora se usa el método personalizado del repositorio, que es más eficiente.
        $productosDestacados = $em->getRepository(Modelo::class)->findDestacadosParaHome();

        return $this->render('web/home.html.twig', [
            'banners'        => $banners,
            'carrito'        => $carrito,
            'categoriasHome' => $categoriasHome,
            'destacados'     => $productosDestacados
        ]);
    }
}

