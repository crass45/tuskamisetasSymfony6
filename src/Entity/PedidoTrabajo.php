<?php

namespace App\Entity;

use App\Entity\Sonata\Media;
use App\Repository\PedidoTrabajoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PedidoTrabajoRepository::class)]
#[ORM\Table(name: 'pedido_trabajo')]
class PedidoTrabajo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 300)]
    private ?string $codigo = null;

    #[ORM\Column(length: 300, nullable: true)]
    private ?string $nombre = null;

    #[ORM\ManyToOne(targetEntity: Contacto::class, inversedBy: 'trabajos')]
    #[ORM\JoinColumn(name: 'id_usuario', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Contacto $contacto = null;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'montaje', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Media $montaje = null;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'arte_fin', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Media $arteFin = null;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'imagen_original', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Media $imagenOriginal = null;

    #[ORM\ManyToOne(targetEntity: Personalizacion::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'personalizacion', referencedColumnName: 'codigo', onDelete: 'SET NULL')]
    private ?Personalizacion $personalizacion = null;

    #[ORM\Column(name: 'url_imagen', length: 300, nullable: true)]
    private ?string $urlImagen = null;

    #[ORM\Column(name: 'n_colores')]
    private ?int $nColores = null;

    public function __toString(): string
    {
        $identificador = $this->nombre ?? (string) $this->id;
        $personalizacionCodigo = $this->personalizacion?->getCodigo() ?? '';
        $personalizacionNombre = $this->personalizacion?->getNombre() ?? '';

        $cadena = sprintf('%s - %s %s', $identificador, $personalizacionCodigo, $personalizacionNombre);

        if ($personalizacionCodigo === 'A1') {
            $cadena .= sprintf(' a %d colores', $this->nColores);
        }

        return trim($cadena);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): self
    {
        $this->codigo = $codigo;
        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): self
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getContacto(): ?Contacto
    {
        return $this->contacto;
    }

    public function setContacto(?Contacto $contacto): self
    {
        $this->contacto = $contacto;
        return $this;
    }

    public function getMontaje(): ?Media
    {
        return $this->montaje;
    }

    public function setMontaje(?Media $montaje): self
    {
        $this->montaje = $montaje;
        return $this;
    }

    public function getArteFin(): ?Media
    {
        return $this->arteFin;
    }



    public function setArteFin(?Media $arteFin): self
    {
        $this->arteFin = $arteFin;
        return $this;
    }

    public function getImagenOriginal(): ?Media
    {
        return $this->imagenOriginal;
    }

    public function setImagenOriginal(?Media $imagenOriginal): self
    {
        $this->imagenOriginal = $imagenOriginal;
        return $this;
    }

    public function getPersonalizacion(): ?Personalizacion
    {
        return $this->personalizacion;
    }

    public function setPersonalizacion(?Personalizacion $personalizacion): self
    {
        $this->personalizacion = $personalizacion;
        return $this;
    }

    public function getUrlImagen(): ?string
    {
        return $this->urlImagen;
    }

    public function setUrlImagen(?string $urlImagen): self
    {
        $this->urlImagen = $urlImagen;
        return $this;
    }

    public function getNColores(): ?int
    {
        return $this->nColores;
    }

    public function setNColores(int $nColores): self
    {
        $this->nColores = $nColores;
        return $this;
    }
}