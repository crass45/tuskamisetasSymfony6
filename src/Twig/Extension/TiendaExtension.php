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

    public function base64url_encode($data)
    { /* ... tu código ... */
    }

    public function dosX($cadena)
    { /* ... tu código ... */
    }

    public function fileGetContents($file)
    { /* ... tu código ... */
    }
}

