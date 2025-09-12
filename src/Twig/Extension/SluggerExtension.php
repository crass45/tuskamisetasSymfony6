<?php
// src/Twig/Extension/SluggerExtension.php

namespace App\Twig\Extension;

use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SluggerExtension extends AbstractExtension
{
    private SluggerInterface $slugger;

    // Inyectamos el servicio Slugger de Symfony, que es la herramienta estándar para esto.
    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    public function getFilters(): array
    {
        // Aquí definimos nuestro nuevo filtro, que se llamará 'slugify'.
        return [
            new TwigFilter('slugify', [$this, 'slugify']),
        ];
    }

    /**
     * Convierte una cadena de texto en una URL amigable (slug).
     * Ejemplo: "Camisetas Técnicas" -> "camisetas-tecnicas"
     */
    public function slugify(string $string): string
    {
        return $this->slugger->slug($string)->lower()->toString();
    }
}