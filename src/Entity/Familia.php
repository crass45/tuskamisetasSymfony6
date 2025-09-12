<?php
// src/Entity/Familia.php

namespace App\Entity;

use App\Entity\Sonata\ClassificationCategory;
use App\Repository\FamiliaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: FamiliaRepository::class)]
#[ORM\Table(name: 'familia')]
class Familia
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $id = null;

    /**
     * @Gedmo\Slug(fields={"nombre"})
     */
    #[ORM\Column(name: 'nombre_url', length: 100)]
    private ?string $nombreUrl = null;

    #[ORM\Column(name: 'orden_menu')]
    private int $ordenMenu = 0;

    #[ORM\Column(name: 'url_image', length: 200, nullable: true)]
    private ?string $urlImage = null;

    #[ORM\Column(name: 'is_promocional')]
    private bool $promocional = false;

    #[Gedmo\Locale]
    private ?string $locale = null;

    // --- CAMPOS TRADUCIBLES ---
    #[Gedmo\Translatable]
    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'titulo_seo', length: 100, nullable: true)]
    private ?string $tituloSEO = null;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'texto_arriba', type: Types::TEXT, nullable: true)]
    private ?string $textoArriba = null;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'texto_abajo', type: Types::TEXT, nullable: true)]
    private ?string $textoAbajo = null;

    // --- RELACIONES ---

    #[ORM\ManyToOne(inversedBy: 'familias')]
    #[ORM\JoinColumn(name: 'proveedor')]
    private ?Proveedor $proveedor = null;

    #[ORM\ManyToOne(inversedBy: 'familias')]
    #[ORM\JoinColumn(name: 'marca')]
    private ?Fabricante $marca = null;

    /**
     * @var Collection<int, Modelo>
     */
    #[ORM\OneToMany(mappedBy: 'familia', targetEntity: Modelo::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $modelosOneToMany;

    /**
     * @var Collection<int, Modelo>
     */
    #[ORM\ManyToMany(targetEntity: Modelo::class, inversedBy: 'familias', cascade: ['persist'])]
    private Collection $modelosManyToMany;

    public function __construct()
    {
        $this->modelosOneToMany = new ArrayCollection();
        $this->modelosManyToMany = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? $this->id ?? 'Nueva Familia';
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    // --- GETTERS Y SETTERS ---

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }
    public function getNombreUrl(): ?string { return $this->nombreUrl; }
    public function setNombreUrl(string $nombreUrl): self { $this->nombreUrl = $nombreUrl; return $this; }
    public function getOrdenMenu(): int { return $this->ordenMenu; }
    public function setOrdenMenu(int $ordenMenu): self { $this->ordenMenu = $ordenMenu; return $this; }
    public function getUrlImage(): ?string { return $this->urlImage; }
    public function setUrlImage(?string $urlImage): self { $this->urlImage = $urlImage; return $this; }
    public function isPromocional(): bool { return $this->promocional; }
    public function setPromocional(bool $promocional): self { $this->promocional = $promocional; return $this; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(?string $nombre): self { $this->nombre = $nombre; return $this; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): self { $this->descripcion = $descripcion; return $this; }
    public function getTituloSEO(): ?string { return $this->tituloSEO; }
    public function setTituloSEO(?string $tituloSEO): self { $this->tituloSEO = $tituloSEO; return $this; }
    public function getTextoArriba(): ?string { return $this->textoArriba; }
    public function setTextoArriba(?string $textoArriba): self { $this->textoArriba = $textoArriba; return $this; }
    public function getTextoAbajo(): ?string { return $this->textoAbajo; }
    public function setTextoAbajo(?string $textoAbajo): self { $this->textoAbajo = $textoAbajo; return $this; }
    public function getProveedor(): ?Proveedor { return $this->proveedor; }
    public function setProveedor(?Proveedor $proveedor): self { $this->proveedor = $proveedor; return $this; }
    public function getMarca(): ?Fabricante { return $this->marca; }
    public function setMarca(?Fabricante $marca): self { $this->marca = $marca; return $this; }

    /** @return Collection<int, Modelo> */
    public function getModelosOneToMany(): Collection { return $this->modelosOneToMany; }
    public function addModelosOneToMany(Modelo $modelo): self { if (!$this->modelosOneToMany->contains($modelo)) { $this->modelosOneToMany->add($modelo); $modelo->setFamilia($this); } return $this; }
    public function removeModelosOneToMany(Modelo $modelo): self { if ($this->modelosOneToMany->removeElement($modelo)) { if ($modelo->getFamilia() === $this) { $modelo->setFamilia(null); } } return $this; }

    /** @return Collection<int, Modelo> */
    public function getModelosManyToMany(): Collection { return $this->modelosManyToMany; }
    public function addModelosManyToMany(Modelo $modelo): self { if (!$this->modelosManyToMany->contains($modelo)) { $this->modelosManyToMany->add($modelo); } return $this; }
    public function removeModelosManyToMany(Modelo $modelo): self { $this->modelosManyToMany->removeElement($modelo); return $this; }

    // En src/Entity/Familia.php, dentro de la clase

    #[ORM\ManyToOne(inversedBy: 'categoryHasFamilias')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    private ?ClassificationCategory $category = null;

    public function getCategory(): ?ClassificationCategory { return $this->category; }
    public function setCategory(?ClassificationCategory $category): self { $this->category = $category; return $this; }
}