<?php
// src/Controller/CatalogController.php

namespace App\Controller;

use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Sonata\ClassificationCategory;
use App\Model\Filtros;
use App\Repository\FabricanteRepository;
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
     * Muestra la página con el listado de todas las marcas que tienen productos.
     */
    #[Route('/{_locale}/marcas', name: 'app_marcas_list', requirements: ['_locale' => 'es|en|fr'])]
    public function marcasAction(FabricanteRepository $fabricanteRepository): Response
    {
        // Se utiliza el nuevo método para obtener solo fabricantes con productos activos
        $fabricantes = $fabricanteRepository->findActiveWithProducts();

        return $this->render('web/catalog/marcas.html.twig', [
            'fabricantes' => $fabricantes,
            'context' => 'Marcas'
        ]);
    }

    // ===================================================================
    // NUEVA ACCIÓN: Procesa el envío del formulario de búsqueda principal
    // ===================================================================
    /**
     * Esta acción se encarga de recibir el POST del formulario de búsqueda
     * y redirigir a la página de resultados.
     */
    #[Route('/{_locale}/buscar/submit', name: 'app_search_handle', methods: ['POST'], requirements: ['_locale' => 'es|en|fr'])]
    public function handleSearchAction(Request $request): Response
    {
        // Usamos 'q' como nombre estándar para el parámetro de búsqueda
        $searchTerm = $request->request->get('q', '');

        // Redirigimos a la nueva ruta de resultados de búsqueda
        return $this->redirectToRoute('app_search_results', [
            '_locale' => $request->getLocale(),
            'q' => $searchTerm
        ]);
    }

    // ===================================================================
    // NUEVA ACCIÓN: Muestra la página de resultados de búsqueda
    // ===================================================================
    /**
     * Esta acción reemplaza a tu antiguo 'buscaAction'. Muestra la página
     * de resultados y permite aplicar más filtros sobre la búsqueda.
     */
    #[Route('/{_locale}/buscar', name: 'app_search_results', requirements: ['_locale' => 'es|en|fr'])]
    public function searchResultsAction(Request $request, ModeloRepository $modeloRepository): Response
    {
        // 1. Creamos un objeto Filtros a partir de TODOS los parámetros de la URL
        $filtros = Filtros::createFromRequest($request, $this->em);

        $page = $request->query->getInt('page', 1);

        // 2. Le pasamos el objeto de filtros al repositorio para obtener los productos
        $paginator = $modeloRepository->findByFiltros($filtros, $page);

        // 3. Obtenemos los filtros disponibles para los resultados encontrados
        $filtrosDisponibles = $modeloRepository->findAvailableFilters($filtros);

        $busqueda = $filtros->getBusqueda();
        $nombrePanel = sprintf('Búsqueda: "%s"', $busqueda);

        return $this->render('web/catalog/category_show.html.twig', [
            'modelos' => $paginator,
            'filtros' => $filtros,
            'filtrosDisponibles' => $filtrosDisponibles,
            'paginaActual' => $page,
            'npaginas' => ceil($paginator->count() / ModeloRepository::PAGINATOR_PER_PAGE),
            'cantidadArticulos' => $paginator->count(),
            'busqueda' => $busqueda,
            'nombrePanel' => $nombrePanel,
            'context' => null, // No hay un contexto (categoría/marca) en una búsqueda
            'fabDisp' => $filtrosDisponibles['fabricantes'],
            'coloresFiltros' => $filtrosDisponibles['colores'],
            'atributos' => $filtrosDisponibles['atributos'],
            'titulo' => "Resultados para '" . $busqueda . "'",
            'descripcion' => "Resultados de la búsqueda para '" . $busqueda . "' en nuestra tienda.",
        ]);
    }

    /**
     * Esta es tu acción actual para mostrar categorías, marcas y familias.
     * No la modificamos para no romper la funcionalidad que ya tienes.
     */
    #[Route('/{_locale}/{slug}', name: 'app_catalog_resolver', requirements: ['_locale' => 'es|en|fr'], priority: -1)]
    public function showListingAction(Request $request, ModeloRepository $modeloRepository, string $slug): Response
    {
        $filtros = Filtros::createFromRequest($request, $this->em);

        $contextObject = null;
        $template = 'web/catalog/category_show.html.twig';
        $isBrandPage = false;
        $families = null;
        if ($category = $this->em->getRepository(ClassificationCategory::class)->findOneBy(['slug' => $slug])) {
            $filtros->setCategory($category);
            $contextObject = $category;
        } elseif ($brand = $this->em->getRepository(Fabricante::class)->findOneBy(['nombreUrl' => $slug])) {
            $filtros->setFabricante($brand);
            $contextObject = $brand;
            $isBrandPage = true;
            $families = $brand->getFamilias();
        } elseif ($family = $this->em->getRepository(Familia::class)->findOneBy(['nombreUrl' => $slug])) {
            $filtros->setFamilia($family);
            $contextObject = $family;
            $isBrandPage = true;
            $families = $family->getMarca()->getFamilias();
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
            'busqueda' => $filtros->getBusqueda(),
            'is_brand_page' => $isBrandPage,
            'families' => $families,
//            'fabDisp' => $filtrosDisponibles['fabricantes'],
//            'coloresFiltros' => $filtrosDisponibles['colores'],
//            'atributos' => $filtrosDisponibles['atributos'],
//            'titulo' => $contextObject->getTituloSEO() ?? $contextObject->__toString(),
//            'descripcion' => $contextObject->getDescripcion() ?? '',
        ]);
    }
}