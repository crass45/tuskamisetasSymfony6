<?php

// src/Twig/Extension/TiendaExtension.php

namespace App\Twig\Extension;

use App\Entity\Empresa;
use App\Entity\Fabricante;
use App\Entity\Modelo;
use App\Entity\Oferta;
use App\Entity\Sonata\ClassificationCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

// MIGRACIÓN: Ahora se extiende de AbstractExtension e implementa GlobalsInterface
class TiendaExtension extends AbstractExtension implements GlobalsInterface
{
    // MIGRACIÓN: Inyectamos los servicios que necesitamos con el constructor
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack           $requestStack
    )
    {
    }

    public function getGlobals(): array
    {
        // MIGRACIÓN: Obtenemos la petición actual de forma segura desde el RequestStack
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return []; // No hacer nada si no estamos en un contexto de petición web (ej. un comando)
        }

        $vistosRecientementeRefs = [
            $request->cookies->get('vistosRecientemente1'),
            $request->cookies->get('vistosRecientemente2'),
            $request->cookies->get('vistosRecientemente3'),
            $request->cookies->get('vistosRecientemente4'),
        ];

        // MIGRACIÓN: Se usan los repositorios con la nueva sintaxis
        $vistos = $this->em->getRepository(Modelo::class)->findBy(['referencia' => array_filter($vistosRecientementeRefs)]);
        $empresa = $this->em->getRepository(Empresa::class)->findOneBy([], ['id' => 'DESC']);
        $proveedores = $this->em->getRepository(Fabricante::class)->findBy([], ['nombre' => 'ASC']);
        $categorias = $this->em->getRepository(ClassificationCategory::class)->findOneBy(['name' => 'Home', 'enabled' => true]);
        $ofertas = $this->em->getRepository(Oferta::class)->findBy(['activo' => true], ['precio' => 'ASC']);

        return [
            'empresa' => $empresa,
            'vistos' => $vistos,
            'proveedores' => $proveedores,
            'categorias' => $categorias,
            'ofertas' => $ofertas,
        ];
    }

    // MIGRACIÓN: La definición de filtros ahora se hace en un único método
    public function getFilters(): array
    {
        return [
            new TwigFilter('urlSafe', [$this, 'urlSafe']),
            new TwigFilter('base64UrlEncode', [$this, 'base64url_encode']),
            new TwigFilter('retina', [$this, 'dosX']),
            new TwigFilter('normalize_whitespace', [$this, 'normalizeWhitespace']),
            new TwigFilter('format_description', [$this, 'formatDescription'], ['is_safe' => ['html']]),
            // ... puedes añadir aquí el resto de tus filtros si los necesitas
        ];
    }

    // MIGRACIÓN: La definición de funciones ahora se hace en un único método
    public function getFunctions(): array
    {
        return [
            new TwigFunction('fileGetContents', [$this, 'fileGetContents']),
        ];
    }

    // --- Lógica de los filtros y funciones (copiada de tu clase original) ---

    public function urlSafe(string $cadena): string
    {
        // MIGRACIÓN NOTA: La lógica de Utiles::stringURLSafe() debería moverse aquí o a un servicio.
        // Este es un placeholder.
        $text = preg_replace('~[^\pL\d]+~u', '-', $cadena);
        $text = strtolower(trim(iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?? '', '-'));
        return $text ?: 'n-a';
    }

    public function normalizeWhitespace(?string $value): string
    {
        if (null === $value) {
            return '';
        }

        // Reemplaza cualquier secuencia de espacios en blanco (\s+) por un solo espacio ' '
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    public function base64url_encode($data)
    { /* ... tu código ... */
    }

    public function dosX($cadena)
    { /* ... tu código ... */
    }

    public function fileGetContents($file)
    { /* ... tu código ... */
    }

    // --- AÑADIR ESTE MÉTODO AL FINAL DE LA CLASE ---
    /**
     * Convierte descripciones separadas por '·' en una lista HTML.
     */
//    public function formatDescription(?string $content): string
//    {
//        if (empty($content)) {
//            return '';
//        }
//
//        // 1. Detectamos si usa el separador de punto medio '·'
//        if (str_contains($content, '·')) {
//            // Dividimos el texto por el punto
//            $parts = explode('·', $content);
//
//            // Iniciamos una lista desordenada con una clase para poder darle estilo si quieres
//            $html = '<ul class="product-description-list" style="list-style-type: disc; padding-left: 20px;">';
//
//            foreach ($parts as $part) {
//                $part = trim($part); // Quitamos espacios extra al principio y final
//                if (!empty($part)) {
//                    // Convertimos cada fragmento en un elemento de lista
//                    $html .= '<li style="margin-bottom: 5px;">' . $part . '</li>';
//                }
//            }
//
//            $html .= '</ul>';
//
//            return $html;
//        }
//
//        // 2. Si no tiene puntos, asumimos que es HTML normal o texto plano y lo devolvemos tal cual
//        return $content;
//    }

    /**
     * Convierte descripciones separadas por '·' (o &middot;) en una lista HTML limpia.
     */
    public function formatDescription(?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // 1. Decodificamos entidades HTML para convertir &middot; en · y &sup2; en ²
        // Esto es vital porque tu base de datos guarda el punto como código HTML.
        $decodedContent = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Eliminamos etiquetas HTML antiguas (<p>, <br>, etc.) para trabajar con texto limpio
        $plainText = strip_tags($decodedContent);

        // 3. Detectamos el punto medio '·' (ahora sí lo encontrará)
        if (str_contains($plainText, '·')) {
            $parts = explode('·', $plainText);

            $html = '<ul class="product-description-list" style="list-style-type: disc; padding-left: 20px;">';

            foreach ($parts as $part) {
                $part = trim($part); // Quitamos espacios extra

                // Limpieza extra: A veces la palabra "Descripción:" se queda colada al principio
                if (stripos($part, 'Descripción:') === 0 || empty($part)) {
                    continue;
                }

                // Si hay texto, creamos el elemento de lista
                if (!empty($part)) {
                    $html .= '<li style="margin-bottom: 5px;">' . $part . '</li>';
                }
            }

            $html .= '</ul>';

            return $html;
        }

        // 4. Si no detectamos puntos, devolvemos el contenido original tal cual
        return $content;
    }
}

