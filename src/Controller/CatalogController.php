<?php
// src/Controller/CatalogController.php

namespace App\Controller;

use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Sonata\ClassificationCategory;
use App\Model\Filtros;
use App\Repository\ModeloRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CatalogController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * Esta única acción ahora maneja TODAS las páginas de listado.
     */
    #[Route('/{_locale}/{slug}', name: 'app_catalog_resolver', requirements: ['_locale' => 'es|en|fr'], priority: -1)]
    public function showListingAction(Request $request, ModeloRepository $modeloRepository, string $slug): Response
    {
        $filtros = Filtros::createFromRequest($request,$this->em);

        $contextObject = null;
        $template = 'web/catalog/category_show.html.twig';

        // Resolvemos el slug para establecer el filtro principal
        if ($category = $this->em->getRepository(ClassificationCategory::class)->findOneBy(['slug' => $slug])) {
            $filtros->setCategory($category);
            $contextObject = $category;
        } elseif ($brand = $this->em->getRepository(Fabricante::class)->findOneBy(['nombreUrl' => $slug])) {
            $filtros->setFabricante($brand);
            $contextObject = $brand;
        } elseif ($family = $this->em->getRepository(Familia::class)->findOneBy(['nombreUrl' => $slug])) {
            $filtros->setFamilia($family);
            $contextObject = $family;
        } else {
            throw $this->createNotFoundException('La página solicitada no existe.');
        }

        $page = $request->query->getInt('page', 1);
        $paginator = $modeloRepository->findByFiltros($filtros, $page);
        $filtrosDisponibles = $modeloRepository->findAvailableFilters($filtros);

        return $this->render($template, [
            'modelos' => $paginator,
            'filtros' => $filtros,
            'context' => $contextObject,
            'filtrosDisponibles' => $filtrosDisponibles,
            'paginaActual' => $page,
            'npaginas' => ceil($paginator->count() / ModeloRepository::PAGINATOR_PER_PAGE),
            'cantidadArticulos' => $paginator->count(),
            'nombrePanel' => $contextObject->__toString(),
        ]);
    }
}