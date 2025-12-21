<?php
// src/Entity/Sonata/Classification/Category.php

namespace App\Entity\Sonata;

use App\Entity\Familia;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Sonata\ClassificationBundle\Entity\BaseCategory;

#[ORM\Entity]
#[ORM\Table(name: 'classification__category')]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')] // <--- AÑADE ESTO
class ClassificationCategory extends BaseCategory implements Translatable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }


    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'imagen', onDelete: 'SET NULL')]
    private ?Media $imagen = null;
    #[Gedmo\Locale]
    private ?string $locale = null;

    // --- CAMPOS PERSONALIZADOS AÑADIDOS ---

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $precio_min = null;

    #[ORM\Column(name: 'aparece_home')]
    private bool $aparece_home = false;

    #[ORM\Column(name: 'visible_menu')]
    private bool $visible_menu = false;

    /**
     * @var Collection<int, Familia>
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Familia::class)]
    private Collection $categoryHasFamilias;

    // --- CAMPOS TRADUCIBLES (GEDMO) ---

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'titulo_seo_trans', length: 70, nullable: true)]
    private ?string $tituloSEOTrans = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'texto_arriba', type: 'text', nullable: true)]
    private ?string $textoArriba = null;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'texto_abajo', type: 'text', nullable: true)]
    private ?string $textoAbajo = null;

    public function __construct()
    {
        parent::__construct();
        $this->categoryHasFamilias = new ArrayCollection();
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    // --- Getters y Setters para los nuevos campos ---

    public function getPrecioMin(): ?string { return $this->precio_min; }
    public function setPrecioMin(?string $precio_min): self { $this->precio_min = $precio_min; return $this; }
    public function isApareceHome(): bool { return $this->aparece_home; }
    public function setApareceHome(bool $aparece_home): self { $this->aparece_home = $aparece_home; return $this; }
    public function isVisibleMenu(): bool { return $this->visible_menu; }
    public function setVisibleMenu(bool $visible_menu): self { $this->visible_menu = $visible_menu; return $this; }
    public function getTituloSEOTrans(): ?string { return $this->tituloSEOTrans; }
    public function setTituloSEOTrans(?string $tituloSEOTrans): self { $this->tituloSEOTrans = $tituloSEOTrans; return $this; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): self { $this->descripcion = $descripcion; return $this; }
    public function getTextoArriba(): ?string { return $this->textoArriba; }
    public function setTextoArriba(?string $textoArriba): self { $this->textoArriba = $textoArriba; return $this; }
    public function getTextoAbajo(): ?string { return $this->textoAbajo; }
    public function setTextoAbajo(?string $textoAbajo): self { $this->textoAbajo = $textoAbajo; return $this; }

    /**
     * @return Media|null
     */
    public function getImagen(): ?Media
    {
        return $this->imagen;
    }

    /**
     * @param Media|null $imagen
     */
    public function setImagen(?Media $imagen): void
    {
        $this->imagen = $imagen;
    }

    /** @return Collection<int, Familia> */
    public function getCategoryHasFamilias(): Collection { return $this->categoryHasFamilias; }
    public function addCategoryHasFamilia(Familia $familia): self { if (!$this->categoryHasFamilias->contains($familia)) { $this->categoryHasFamilias->add($familia); $familia->setCategory($this); } return $this; }
    public function removeCategoryHasFamilia(Familia $familia): self { if ($this->categoryHasFamilias->removeElement($familia)) { if ($familia->getCategory() === $this) { $familia->setCategory(null); } } return $this; }

    /**
     * @return string|null
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getTituloSEO(): ?string{
        return $this->tituloSEOTrans;
    }
}
