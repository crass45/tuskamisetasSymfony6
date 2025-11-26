<?php

namespace App\Controller;

use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Modelo;
use App\Entity\Sonata\ClassificationCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class SitemapController extends AbstractController
{
    private EntityManagerInterface $em;
    private RouterInterface $router;

    public function __construct(EntityManagerInterface $em, RouterInterface $router)
    {
        $this->em = $em;
        $this->router = $router;
    }

    /**
     * Define los idiomas activos.
     */
    private function getLang(): array
    {
        // Según tu código anterior, de momento solo 'es'. Descomenta 'en' o 'fr' si los activas.
        return ['es'/*, 'en', 'fr'*/];
    }

    private function getDistinctLang(string $currentLang): array
    {
        return array_filter($this->getLang(), fn($lang) => $lang !== $currentLang);
    }

    /**
     * Genera la estructura de URLs para el sitemap con sus alternativos.
     */
    private function generaUrl(string $route, array $params = [], string $changefreq = 'weekly', string $priority = '1.0', array $imagenes = []): array
    {
        $urls = [];
        foreach ($this->getLang() as $lang) {
            $params['_locale'] = $lang; // Aseguramos el locale

            // Generamos URL absoluta para el idioma actual
            try {
                $loc = $this->router->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
            } catch (\Exception $e) {
                // Si la ruta no existe (ej. servicios aun no migrados), saltamos
                continue;
            }

            $links = [];
            // Generamos alternativos (hreflang)
            foreach ($this->getDistinctLang($lang) as $miLang) {
                $altParams = $params;
                $altParams['_locale'] = $miLang;
                try {
                    $links[] = [
                        'lang' => $miLang,
                        'href' => $this->router->generate($route, $altParams, UrlGeneratorInterface::ABSOLUTE_URL)
                    ];
                } catch (\Exception $e) { continue; }
            }

            $urls[] = [
                'loc' => $loc,
                'changefreq' => $changefreq,
                'priority' => $priority,
                'links' => $links,
                'images' => $imagenes
            ];
        }
        return $urls;
    }

    #[Route('/googleMerchant.xml', name: 'app_sitemap_merchant', defaults: ['_format' => 'xml'])]
    public function productMerchantAction(): Response
    {
        // OJO: Asegúrate de tener suficiente memoria si son muchos productos
        $modelos = $this->em->getRepository(Modelo::class)->findBy(['activo' => true]);

        return $this->render('sitemap/googleMerchantProducts.xml.twig', [
            'productos' => $modelos
        ]);
    }

    #[Route('/sitemap-static.xml', name: 'app_sitemap_static', defaults: ['_format' => 'xml'])]
    public function sitemapStaticAction(Request $request): Response
    {
        $urls = [];

        // 1. Home
        $urls = array_merge($urls, $this->generaUrl('app_home', [], 'daily', '1.0'));

        // 2. Páginas estáticas / Servicios
        // NOTA: He puesto nombres de ruta supuestos (app_servicio_...) basados en tu código anterior.
        // Si usas un PageController genérico, ajusta esto.
        $servicios = [
            'app_marcas_list', // Esta existe en CatalogController
             'app_service_serigrafia', // Asegúrate de que estas rutas existan en tu Routes.yaml o Controllers
             'app_service_dtg',
             'app_service_dtf',
             'app_service_bordados',
            'app_service_vinilo',
            'app_service_fullprint',
            'app_service_tampografia',
            'app_service_etiquetas',
            'app_service_packaging'
        ];

        foreach ($servicios as $ruta) {
            $urls = array_merge($urls, $this->generaUrl($ruta));
        }

        return $this->render('sitemap/sitemap.xml.twig', ['urls' => $urls]);
    }

    #[Route('/sitemap-categorias.xml', name: 'app_sitemap_categorias', defaults: ['_format' => 'xml'])]
    public function sitemapCategoriasAction(): Response
    {
        $urls = [];
        // Asumiendo que 'Home' es la categoría raíz en Sonata Classification
        $rootCategory = $this->em->getRepository(ClassificationCategory::class)->findOneBy(['name' => 'Home']); // O usa 'context' => 'default'

        if ($rootCategory) {
            // Función recursiva para recorrer árbol de categorías si fuera necesario
            // De momento usamos tu lógica plana de hijos directos
            foreach ($rootCategory->getChildren() as $categoria) {
                if (!$categoria->getEnabled()) continue;

                $imagenes = [];
                if ($categoria->getImagen()) {
                    // Aquí necesitarías un helper para la URL de media, pongo placeholder
                    // $imagenes[] = ['loc' => $helper->getUrl($categoria->getMedia()), ...];
                }

                // IMPORTANTE: Usamos la ruta 'app_catalog_resolver' definida en CatalogController
                $urls = array_merge($urls, $this->generaUrl('app_catalog_resolver', ['slug' => $categoria->getSlug()], 'weekly', '0.8', $imagenes));

                // Subcategorías
                foreach ($categoria->getChildren() as $subCategoria) {
                    if (!$subCategoria->getEnabled()) continue;
                    $urls = array_merge($urls, $this->generaUrl('app_catalog_resolver', ['slug' => $subCategoria->getSlug()], 'weekly', '0.8'));
                }
            }
        }

        return $this->render('sitemap/sitemap.xml.twig', ['urls' => $urls]);
    }

    #[Route('/sitemap-familias.xml', name: 'app_sitemap_familias', defaults: ['_format' => 'xml'])]
    public function sitemapFamiliasAction(): Response
    {
        $urls = [];
        $familias = $this->em->getRepository(Familia::class)->findAll();

        foreach ($familias as $familia) {
            // Usamos la ruta resolver con el slug de la familia (asumiendo que nombreUrl es el slug único)
            $urls = array_merge($urls, $this->generaUrl('app_catalog_resolver', ['slug' => $familia->getNombreUrl()], 'weekly', '0.7'));
        }

        return $this->render('sitemap/sitemap.xml.twig', ['urls' => $urls]);
    }

    #[Route('/sitemap-marcas.xml', name: 'app_sitemap_marcas', defaults: ['_format' => 'xml'])]
    public function sitemapMarcasAction(): Response
    {
        $urls = [];
        // Usamos el método personalizado que vi en tu CatalogController para sacar solo activos
        $fabricantes = $this->em->getRepository(Fabricante::class)->findActiveWithProducts();

        foreach ($fabricantes as $marca) {
            // Usamos la ruta resolver con el slug de la marca
            $urls = array_merge($urls, $this->generaUrl('app_catalog_resolver', ['slug' => $marca->getNombreUrl()], 'weekly', '0.7'));
        }

        return $this->render('sitemap/sitemap.xml.twig', ['urls' => $urls]);
    }

    #[Route('/sitemap-modelo.xml', name: 'app_sitemap_modelos', defaults: ['_format' => 'xml'])]
    public function sitemapModeloAction(): Response
    {
        $urls = [];
        // Traemos solo los activos
        $modelos = $this->em->getRepository(Modelo::class)->findBy(['activo' => true]);

        foreach ($modelos as $modelo) {
            if ($modelo->getPrecioMin() <= 0) continue;

            $imagenes = [];
            // Lógica de imágenes (Simplificada, requeriría integrar con SonataMedia provider para sacar la URL real)
            /* if ($modelo->getImagen()) {
                 $imagenes[] = ['loc' => '...path_to_image...', 'title' => $modelo->getNombre()];
            }
            */

            // Usamos la ruta 'app_product_detail' definida en ProductController
            $urls = array_merge($urls, $this->generaUrl('app_product_detail', ['slug' => $modelo->getNombreUrl()], 'daily', '0.9', $imagenes));
        }

        return $this->render('sitemap/sitemap.xml.twig', ['urls' => $urls]);
    }

    /**
     * Índice del sitemap (Opcional, para agrupar todos)
     */
    #[Route('/sitemap.xml', name: 'app_sitemap_index', defaults: ['_format' => 'xml'])]
    public function sitemapIndex(): Response
    {
        $urls = [
            $this->router->generate('app_sitemap_static', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->router->generate('app_sitemap_categorias', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->router->generate('app_sitemap_familias', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->router->generate('app_sitemap_marcas', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->router->generate('app_sitemap_modelos', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        return $this->render('sitemap/sitemap_index.xml.twig', ['urls' => $urls]);
    }
}