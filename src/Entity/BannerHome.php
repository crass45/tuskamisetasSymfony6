<?php

namespace App\Entity;

use App\Repository\BannerHomeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Sonata\Media; // Asegúrate de que esta ruta es correcta

#[ORM\Entity]
#[ORM\Table(name: 'banner_homme')] // NOTA: El nombre de la tabla parece tener una errata ('homme' en lugar de 'home'). He conservado el original.
class BannerHome
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $orden = 0;

    #[ORM\Column(length: 100)]
    private ?string $titulo = '';

    #[ORM\Column(length: 100)]
    private ?string $subtitulo = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $texto = null;

    #[ORM\Column(length: 100)]
    private ?string $url = '';

    #[ORM\Column]
    private bool $activo = false;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'imagen')]
    private ?Media $imagen = null;

    public function __toString(): string
    {
        return $this->titulo ?? 'Nuevo Banner';
    }

    // --- Getters y Setters ---

    public function getId(): ?int { return $this->id; }
    public function getOrden(): int { return $this->orden; }
    public function setOrden(int $orden): self { $this->orden = $orden; return $this; }
    public function getTitulo(): ?string { return $this->titulo; }
    public function setTitulo(string $titulo): self { $this->titulo = $titulo; return $this; }
    public function getSubtitulo(): ?string { return $this->subtitulo; }
    public function setSubtitulo(string $subtitulo): self { $this->subtitulo = $subtitulo; return $this; }
    public function getTexto(): ?string { return $this->texto; }
    public function setTexto(string $texto): self { $this->texto = $texto; return $this; }
    public function getUrl(): ?string { return $this->url; }
    public function setUrl(string $url): self { $this->url = $url; return $this; }
    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): self { $this->activo = $activo; return $this; }
    public function getImagen(): ?Media { return $this->imagen; }
    public function setImagen(?Media $imagen): self { $this->imagen = $imagen; return $this; }
}