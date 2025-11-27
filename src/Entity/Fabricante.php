<?php
// src/Entity/Fabricante.php

namespace App\Entity;

use App\Repository\FabricanteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Sonata\Media;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;

#[ORM\Entity(repositoryClass: FabricanteRepository::class)]
#[ORM\Table(name: 'fabricante')]
class Fabricante implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    /**
     * @Gedmo\Slug(fields={"nombre"})
     */
    #[ORM\Column(name: 'nombre_url', length: 100)]
    private ?string $nombreUrl = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'titulo_seo', length: 100, nullable: true)]
    private ?string $tituloSEO = '';

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $textoArriba = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $textoAbajo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observaciones = null;

    #[ORM\Column]
    private bool $activo = false;

    #[ORM\Column(name: 'aparece_menu')]
    private bool $showMenu = false;

    #[Gedmo\Locale]
    private ?string $locale = null;

    #[ORM\Column(name: 'vista_alternativa', options: ['default' => false])]
    private bool $vistaAlternativa = false;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'imagen', onDelete: 'SET NULL')]
    private ?Media $imagen = null;

    /** @var Collection<int, Modelo> */
    #[ORM\OneToMany(mappedBy: 'fabricante', targetEntity: Modelo::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $modelos;

    /** @var Collection<int, Color> */
    #[ORM\OneToMany(mappedBy: 'fabricante', targetEntity: Color::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $colores;

    /** @var Collection<int, Familia> */
    #[ORM\OneToMany(mappedBy: 'marca', targetEntity: Familia::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $familias;

    public function __construct()
    {
        $this->modelos = new ArrayCollection();
        $this->colores = new ArrayCollection();
        $this->familias = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? 'Nuevo Fabricante';
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    // --- GETTERS Y SETTERS ---

    public function getId(): ?int { return $this->id; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): self { $this->nombre = $nombre; return $this; }
    public function getNombreUrl(): ?string { return $this->nombreUrl; }
    public function setNombreUrl(string $nombreUrl): self { $this->nombreUrl = $nombreUrl; return $this; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): self { $this->descripcion = $descripcion; return $this; }
    public function getTituloSEO(): ?string { return $this->tituloSEO; }
    public function setTituloSEO(?string $tituloSEO): self { $this->tituloSEO = $tituloSEO; return $this; }
    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $observaciones): self { $this->observaciones = $observaciones; return $this; }
    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): self { $this->activo = $activo; return $this; }
    public function isShowMenu(): bool { return $this->showMenu; }
    public function setShowMenu(bool $showMenu): self { $this->showMenu = $showMenu; return $this; }
    public function getImagen(): ?Media { return $this->imagen; }
    public function setImagen(?Media $imagen): self { $this->imagen = $imagen; return $this; }
    public function getTextoArriba(): ?string { return $this->textoArriba; }
    public function setTextoArriba(?string $textoArriba): self { $this->textoArriba = $textoArriba; return $this; }
    public function getTextoAbajo(): ?string { return $this->textoAbajo; }
    public function setTextoAbajo(?string $textoAbajo): self { $this->textoAbajo = $textoAbajo; return $this; }

    /** @return Collection<int, Modelo> */
    public function getModelos(): Collection { return $this->modelos; }
    public function addModelo(Modelo $modelo): self { if (!$this->modelos->contains($modelo)) { $this->modelos->add($modelo); $modelo->setFabricante($this); } return $this; }
    public function removeModelo(Modelo $modelo): self { if ($this->modelos->removeElement($modelo)) { if ($modelo->getFabricante() === $this) { $modelo->setFabricante(null); } } return $this; }

    /** @return Collection<int, Color> */
    public function getColores(): Collection { return $this->colores; }
    public function addColor(Color $color): self { if (!$this->colores->contains($color)) { $this->colores->add($color); $color->setFabricante($this); } return $this; }
    public function removeColor(Color $color): self { if ($this->colores->removeElement($color)) { if ($color->getFabricante() === $this) { $color->setFabricante(null); } } return $this; }

    /** @return Collection<int, Familia> */
    public function getFamilias(): Collection { return $this->familias; }
    public function addFamilia(Familia $familia): self { if (!$this->familias->contains($familia)) { $this->familias->add($familia); $familia->setMarca($this); } return $this; }
    public function removeFamilia(Familia $familia): self { if ($this->familias->removeElement($familia)) { if ($familia->getMarca() === $this) { $familia->setMarca(null); } } return $this; }

    public function isVistaAlternativa(): bool
    {
        return $this->vistaAlternativa;
    }

    public function setVistaAlternativa(bool $vistaAlternativa): self
    {
        $this->vistaAlternativa = $vistaAlternativa;
        return $this;
    }
}