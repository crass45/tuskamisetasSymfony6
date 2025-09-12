<?php

namespace App\Entity;

use App\Repository\PublicacionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublicacionRepository::class)]
#[ORM\Table(name: 'publicaciones')]
#[ORM\HasLifecycleCallbacks] // Habilita los callbacks de Doctrine como PrePersist
class Publicacion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titulo = '';

    #[ORM\Column(length: 255)]
    private ?string $metadescripcion = '';

    #[ORM\Column(name: 'url_imagen_portada', length: 255)]
    private ?string $urlImagenPortada = '';

    #[ORM\Column(name: 'nombre_url', length: 200)]
    private ?string $nombreUrl = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenido = null;

    #[ORM\Column(name: 'texto_portada', type: Types::TEXT)]
    private ?string $textoPortada = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fecha = null;

    #[ORM\Column]
    private bool $activo = false;

    public function __toString(): string
    {
        return $this->titulo ?? 'Nueva Publicación';
    }

    /**
     * NOTA DE MIGRACIÓN:
     * Este método se ejecuta automáticamente antes de guardar la entidad por primera vez.
     * Genera la URL a partir del título, reemplazando la lógica que estaba en setTitulo().
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateNombreUrl(): void
    {
        if (null !== $this->getTitulo()) {
            $this->nombreUrl = $this->slugify($this->getTitulo());
        }
    }

    /**
     * Reemplazo simple para la antigua clase Utiles::stringURLSafe().
     * Para una solución más avanzada, se recomienda usar el componente Symfony Slugger.
     */
    private function slugify(string $text): string
    {
        // Convierte a minúsculas
        $text = strtolower($text);
        // Reemplaza caracteres no latinos
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        // Elimina guiones al principio y al final
        $text = trim($text, '-');
        // Translitera
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Elimina caracteres no deseados
        $text = preg_replace('~[^-\w]+~', '', $text);

        return $text;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitulo(): ?string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): self
    {
        $this->titulo = $titulo;

        return $this;
    }

    public function getMetadescripcion(): ?string
    {
        return $this->metadescripcion;
    }

    public function setMetadescripcion(string $metadescripcion): self
    {
        $this->metadescripcion = $metadescripcion;

        return $this;
    }

    public function getUrlImagenPortada(): ?string
    {
        return $this->urlImagenPortada;
    }

    public function setUrlImagenPortada(string $urlImagenPortada): self
    {
        $this->urlImagenPortada = $urlImagenPortada;

        return $this;
    }

    public function getNombreUrl(): ?string
    {
        return $this->nombreUrl;
    }

    public function setNombreUrl(string $nombreUrl): self
    {
        $this->nombreUrl = $nombreUrl;

        return $this;
    }

    public function getContenido(): ?string
    {
        return $this->contenido;
    }

    public function setContenido(string $contenido): self
    {
        $this->contenido = $contenido;

        return $this;
    }

    public function getTextoPortada(): ?string
    {
        return $this->textoPortada;
    }



    public function setTextoPortada(string $textoPortada): self
    {
        $this->textoPortada = $textoPortada;

        return $this;
    }

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(?\DateTimeInterface $fecha): self
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): self
    {
        $this->activo = $activo;

        return $this;
    }
}