<?php
// src/Controller/CatalogController.php

namespace App\Controller;

use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Modelo;
use App\Entity\Sonata\ClassificationCategory;
use App\Model\Filtros;
use App\Repository\ModeloRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}', requirements: ['_locale' => 'es|en|fr'])]
class CatalogController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    #[Route('/categorias', name: 'app_category_list')]
    public function categoriesAction(): Response
    {
        // MIGRACIÓN: Ahora el controlador obtiene las categorías de la base de datos
        // y se las pasa a la plantilla.
        $categories = $this->em->getRepository(ClassificationCategory::class)->findBy(['enabled' => true], ['position' => 'ASC']);

        return $this->render('web/catalog/categorias.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/marcas', name: 'app_brand_list')]
    public function brandsAction(): Response
    {
        // MIGRACIÓN: Ahora el controlador obtiene los fabricantes (marcas) de la base de datos
        // y se los pasa a la plantilla.
        $brands = $this->em->getRepository(Fabricante::class)->findBy(['activo' => true], ['nombre' => 'ASC']);

        return $this->render('web/catalog/marcas.html.twig', [
            'brands' => $brands,
        ]);
    }

    #[Route('/ofertas', name: 'app_offer_list')]
    public function offersAction(): Response
    {
        // MIGRACIÓN: Ahora el controlador obtiene las ofertas de la base de datos
        // y se las pasa a la plantilla.
        $offers = $this->em->getRepository(Oferta::class)->findBy(['activo' => true], ['nombre' => 'ASC']);

        return $this->render('web/catalog/ofertas.html.twig', [
            'ofertas' => $offers,
        ]);
    }

    /**
     * Esta única acción ahora maneja TODAS las páginas de listado:
     * categorías, marcas, familias y búsquedas.
     */
    #[Route('/{slug?}', name: 'app_catalog_resolver', requirements: ['_locale' => 'es|en|fr'], defaults: ['slug' => null])]
    public function listingAction(Request $request, ModeloRepository $modeloRepository, ?string $slug = null): Response
    {
        // 1. Creamos un objeto Filtros a partir de la URL (ej. ?colores[]=rojo&orden=Precio+ASC)
        $filtros = Filtros::createFromRequest($request);

        $contextObject = null;
        $template = 'web/catalog/listing.html.twig'; // Plantilla por defecto
        $titulo ="Pagina sin Titulo";
        $descripcion ="Pagina sin Titulo";;

        // 2. Resolvemos el slug para saber en qué página estamos y enriquecer el objeto de filtros
        if ($slug) {
            $category = $this->em->getRepository(ClassificationCategory::class)->findOneBy(['slug' => $slug]);
            if ($category) {
                $filtros->setCategory($category);
                $contextObject = $category;
                var_dump("Es Categoria");
                $template = 'web/catalog/category_show.html.twig';
                $titulo = $category->getTituloSEOTrans();
                $descripcion = $category->getDescripcion();
            }

            $brand = $this->em->getRepository(Fabricante::class)->findOneBy(['nombreUrl' => $slug]);
            if ($brand) {
                $filtros->setFabricante($brand);
                $contextObject = $brand;
                var_dump("Es Marca");
                $template = 'web/catalog/brand_show.html.twig';
                $titulo = $brand->getTituloSEO();
                $descripcion = $brand->getDescripcion();
            }

            $family = $this->em->getRepository(Familia::class)->findOneBy(['nombreUrl' => $slug]);
            if ($family) {
                $filtros->setFamilia($family);
                $contextObject = $family;
                var_dump("Es Familia");
                $template = 'web/catalog/family_show.html.twig';
                $titulo= $family->getTituloSEO();
                $descripcion = $family->getDescripcion();
            }
        }

        // Si hay una búsqueda en la URL, la añadimos al objeto de filtros
        if ($request->query->has('q')) {
            $filtros->setBusqueda($request->query->get('q'));
        }

        // 3. Le pedimos al Repositorio que nos dé los productos paginados
        $page = $request->query->getInt('page', 1);
        $paginator = $modeloRepository->findByFiltros($filtros, $request->query->getInt('page', 1));

        // 4. Obtenemos los filtros disponibles para la vista (colores, atributos, etc.)
        // $filtrosDisponibles = $modeloRepository->findAvailableFiltersForPaginator($paginator);

        return $this->render($template, [
            'descripcion' => $descripcion,
            'titulo' => $titulo,
            'modelos' => $paginator,
            'filtros' => $filtros,
            'context' => $contextObject,
            'cantidadArticulos' => $paginator->count(),
            'npaginas' => ceil($paginator->count() / ModeloRepository::PAGINATOR_PER_PAGE),
            'paginaActual' => $page,
        ]);
    }
}