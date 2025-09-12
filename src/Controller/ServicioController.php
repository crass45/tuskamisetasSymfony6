<?php
// src/Controller/ServiceController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/servicio', requirements: ['_locale' => 'es|en|fr'])] // Prefijo para todas las rutas
class ServicioController extends AbstractController
{
    #[Route('/serigrafia', name: 'app_service_serigrafia')]
    public function serigrafiaAction(): Response
    {
        return $this->render('web/servicio/serigrafia.html.twig');
    }

    #[Route('/sublimacion', name: 'app_service_sublimacion')]
    public function sublimacionAction(): Response
    {
        return $this->render('web/servicio/sublimacion.html.twig');
    }

    #[Route('/dtg', name: 'app_service_dtg')]
    public function dtgAction(): Response
    {
        return $this->render('web/servicio/dtg.html.twig');
    }

    #[Route('/dtf', name: 'app_service_dtf')]
    public function dtfAction(): Response
    {
        return $this->render('web/servicio/dtf.html.twig');
    }

    #[Route('/bordados', name: 'app_service_bordados')]
    public function bordadosAction(): Response
    {
        return $this->render('web/servicio/bordado.html.twig');
    }

    #[Route('/vinilo', name: 'app_service_vinilo')]
    public function viniloAction(): Response
    {
        return $this->render('web/servicio/vinilo.html.twig');
    }

    #[Route('/full-print', name: 'app_service_fullprint')]
    public function fullPrintAction(): Response
    {
        return $this->render('web/servicio/fullPrint.html.twig');
    }

    #[Route('/tampografia', name: 'app_service_tampografia')]
    public function tampografiaAction(): Response
    {
        return $this->render('web/servicio/tampografia.html.twig');
    }

    #[Route('/etiquetas', name: 'app_service_etiquetas')]
    public function etiquetasAction(): Response
    {
        return $this->render('web/servicio/etiquetas.html.twig');
    }

    #[Route('/packaging', name: 'app_service_packaging')]
    public function packagingAction(): Response
    {
        return $this->render('web/servicio/packaging.html.twig');
    }
}
