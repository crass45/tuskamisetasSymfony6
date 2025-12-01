<?php

namespace App\Entity;

use App\Repository\ModeloRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use App\Entity\Sonata\Media;
use App\Entity\Sonata\ClassificationCategory;
use App\Entity\Sonata\User;
use Gedmo\Translatable\Translatable;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: ModeloRepository::class)]
#[ORM\Table(name: 'modelo')]
#[ORM\Index(columns: ['referencia', 'nombre', 'importancia', 'precio_min_adulto', 'precio_min'], name: 'modelo_search_index')]
class Modelo implements Translatable
{
    #[Gedmo\Locale]
    private ?string $locale = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = '';

    /**
     * @Gedmo\Slug(fields={"fabricante.nombre", "nombre"})
     */
    #[ORM\Column(name: 'nombre_url', length: 200)]
    private ?string $nombreUrl = null;

    #[ORM\Column(length: 45)]
    private ?string $referencia = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updated = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $largeRef = null;

    #[ORM\Column(name: 'supplier_article_name', length: 45, nullable: true)]
    private ?string $supplierArticleName = null;

    #[ORM\Column(name: 'url_ficha_tecnica', length: 200, nullable: true)]
    private ?string $urlFichaTecnica = null;

    #[ORM\Column(name: 'url_image', length: 200, nullable: true)]
    private ?string $urlImage = null;

    #[ORM\Column(name: 'certificados', type: Types::TEXT, nullable: true)]
    private $certificados;

    #[ORM\Column(name: 'is_for_children', nullable: true)]
    private ?bool $isForChildren = null;

    #[ORM\Column(name: 'imagen_descargada')]
    private bool $imagenDescargada = false;

    #[ORM\Column(name: 'articulo_publicitario')]
    private bool $articuloPublicitario = false;

    #[ORM\Column(name: 'is_novelty', nullable: true)]
    private ?bool $isNovelty = null;

    #[ORM\Column]
    private bool $activo = false;

    #[ORM\Column]
    private bool $destacado = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $composicion = null;

    #[ORM\Column(nullable: true)]
    private ?int $gramaje = null;

    #[ORM\Column(nullable: true)]
    private ?int $pack = null;

    #[ORM\Column(nullable: true)]
    private ?int $box = null;

    #[ORM\Column(nullable: true)]
    private ?int $importancia = 0;

    #[ORM\Column(name: 'descuento_especial', type: 'decimal', precision: 5, scale: 3)]
    private ?string $descuentoEspecial = '0.000';

    #[ORM\Column(name: 'acumula_total', nullable: true)]
    private ?bool $acumulaTotal = false;

    #[ORM\Column(name: 'obligado_vende_en_pack')]
    private bool $obligadaVentaEnPack = false;

    #[ORM\Column(name: 'precio_min_adulto', type: 'decimal', precision: 12, scale: 3)]
    private ?string $precioMinAdulto = '0.000';

    #[ORM\Column(name: 'precio_min', type: 'decimal', precision: 12, scale: 3)]
    private ?string $precioMin = '0.000';

    #[ORM\Column(name: 'url_360', length: 200, nullable: true)]
    private ?string $url360 = null;

    #[ORM\Column(name: 'descripcion_tuskamisetas', type: Types::TEXT, nullable: true)]
    private ?string $descripcionTusKamisetas = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observaciones = null;

    #[ORM\Column(name: 'details_images', type: Types::TEXT, nullable: true)]
    private ?string $detailsImages = null;

    #[ORM\Column(name: 'child_image', type: Types::TEXT, nullable: true)]
    private ?string $childImage = null;

    #[ORM\Column(name: 'other_images', type: Types::TEXT, nullable: true)]
    private ?string $otherImages = null;

    // --- CAMPOS TRADUCIBLES ---
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'titulo_seo', length: 100, nullable: true)]
    private ?string $tituloSEO = null;

    #[Gedmo\Translatable]
    #[ORM\Column(name: 'descripcion_seo', type: Types::TEXT, nullable: true)]
    private ?string $descripcionSEO = null;

    // --- RELACIONES ---

    #[ORM\ManyToOne(inversedBy: 'modelos')]
    #[ORM\JoinColumn(name: 'proveedor', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Proveedor $proveedor = null;

    #[ORM\ManyToOne(inversedBy: 'modelos')]
    #[ORM\JoinColumn(name: 'fabricante', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Fabricante $fabricante = null;

    #[ORM\ManyToOne(inversedBy: 'modelos')]
    #[ORM\JoinColumn(name: 'gender', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Genero $gender = null;

    #[ORM\ManyToOne(targetEntity: Tarifa::class)]
    #[ORM\JoinColumn(name: 'id_tarifa')]
    private ?Tarifa $tarifa = null;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'imagen', onDelete: 'SET NULL')]
    private ?Media $imagen = null;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'drawing', onDelete: 'SET NULL')]
    private ?Media $drawing = null;

    #[ORM\OneToOne(targetEntity: Media::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'ficha_tecnica', onDelete: 'SET NULL')]
    private ?Media $fichaTecnica = null;

    #[ORM\ManyToOne(targetEntity: Familia::class, inversedBy: 'modelosOneToMany')]
    #[ORM\JoinColumn(name: 'familia', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Familia $familia = null;

    /** @var Collection<int, Producto> */
    #[ORM\OneToMany(mappedBy: 'modelo', targetEntity: Producto::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $productos;

    /** @var Collection<int, Familia> */
    #[ORM\ManyToMany(targetEntity: Familia::class, mappedBy: 'modelosManyToMany')]
    private Collection $familias;

    /** @var Collection<int, Modelo> */
    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'modelo_modeloRelacionado')]
    #[ORM\JoinColumn(name: 'modelo_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'relacionado_id', referencedColumnName: 'id')]
    private Collection $modelosRelacionados;

    /** @var Collection<int, ModeloAtributo> */
    #[ORM\ManyToMany(targetEntity: ModeloAtributo::class, inversedBy: 'modelos')]
    #[ORM\JoinTable(name: 'modelo_modeloatributos')]
    private Collection $atributos;

    /** @var Collection<int, ModeloHasTecnicasEstampado> */
    #[ORM\OneToMany(mappedBy: 'modelo', targetEntity: ModeloHasTecnicasEstampado::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EAGER')]
    private Collection $tecnicas;

    /** @var Collection<int, ClassificationCategory> */
    #[ORM\ManyToMany(targetEntity: ClassificationCategory::class)]
    #[ORM\JoinTable(name: 'modelo_categories')]
    private Collection $category;

    public function __construct()
    {
        $this->productos = new ArrayCollection();
        $this->familias = new ArrayCollection();
        $this->modelosRelacionados = new ArrayCollection();
        $this->atributos = new ArrayCollection();
        $this->tecnicas = new ArrayCollection();
        $this->category = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? 'Nuevo Modelo';
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateNombreUrl(): void
    {
        if ($this->nombre) {
            $fabricanteNombre = $this->fabricante?->getNombre() ?? '';
            $this->nombreUrl = $this->slugify($fabricanteNombre . '_' . $this->nombre);
        }
    }

    private function slugify(string $text): string
    {
        $slugger = new AsciiSlugger();
        return $slugger->slug($text)->lower()->toString();
    }

    // ===================================================================
    // NOTA DE ARQUITECTURA: Lógica de Negocio
    // Se recomienda encarecidamente mover esta lógica a un SERVICIO DEDICADO.
    // ===================================================================

    public function getColores(): Collection
    {
        $colores = new ArrayCollection();
        foreach ($this->productos as $producto) {
            if ($producto->isActivo() && $producto->getColor() && !$colores->contains($producto->getColor())) {
                $colores->add($producto->getColor());
            }
        }
        return $colores;
    }

    public function getColoresProductos(): Collection
    {
        $coloresUnicos = new ArrayCollection();
        $productosFiltrados = new ArrayCollection();
        foreach ($this->productos as $producto) {
            if ($producto->isActivo() && $producto->getColor() && !$coloresUnicos->contains($producto->getColor())) {
                $coloresUnicos->add($producto->getColor());
                $productosFiltrados->add($producto);
            }
        }
        return $productosFiltrados;
    }

    public function getTallas(): array
    {
        $tallas = [];
        foreach ($this->productos as $producto) {
            if ($producto->isActivo() && $producto->getTalla() && !in_array($producto->getTalla(), $tallas, true)) {
                $tallas[] = $producto->getTalla();
            }
        }
        // La ordenación debe hacerse fuera si es necesaria, usort no devuelve el array.
        // usort($tallas, [self::class, 'cmpTallas']);
        return $tallas;
    }

    private static function cmpTallas(string $a, string $b): int
    {
        return strnatcmp($a, $b);
    }

    public function getIncremento(int $cantidad, ?User $usuario): float
    {
        $tarifa = $this->getTarif();
        if (!$tarifa) return 0.0;

        $valor = 0.0;
        $descuentoAplicar = 0.0;

        if ($usuario) {
            foreach ($usuario->getGroups() as $grupo) {
                foreach ($grupo->getDescuentos() as $descuento) {
                    if ($descuento->getTarifaAnterior()?->getId() === $tarifa->getId()) {
                        if ($descuento->getTarifa()) {
                            $tarifa = $descuento->getTarifa();
                        }
                        $descuentoAplicar = (float) $descuento->getDescuento();
                    }
                }
            }
        }

        $precioSeleccionado = null;
        foreach ($tarifa->getPrecios() as $precio) {
            if ($cantidad >= $precio->getCantidad()) {
                $precioSeleccionado = $precio;
                break;
            }
        }

        if ($precioSeleccionado) {
            $valor = (float) $precioSeleccionado->getPrecio();
        }

        return $valor - ($valor * $descuentoAplicar / 100);
    }

    public function getTarif(): ?Tarifa
    {
        return $this->tarifa ?? $this->proveedor?->getTarifa();
    }

    // ===================================================================
    // Getters y Setters Estándar (Conjunto Completo)
    // ===================================================================

    public function getId(): ?int { return $this->id; }
    public function getReferencia(): ?string { return $this->referencia; }
    public function setReferencia(string $referencia): self { $this->referencia = $referencia; return $this; }
    public function getUpdated(): ?\DateTimeInterface { return $this->updated; }
    public function setUpdated(?\DateTimeInterface $updated): self { $this->updated = $updated; return $this; }
    public function getLargeRef(): ?string { return $this->largeRef; }
    public function setLargeRef(?string $largeRef): self { $this->largeRef = $largeRef; return $this; }
    public function getSupplierArticleName(): ?string { return $this->supplierArticleName; }
    public function setSupplierArticleName(?string $supplierArticleName): self { $this->supplierArticleName = $supplierArticleName; return $this; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): self { $this->nombre = $nombre; return $this; }
    public function getNombreUrl(): ?string { return $this->nombreUrl; }
    public function setNombreUrl(string $nombreUrl): self { $this->nombreUrl = $nombreUrl; return $this; }
    public function getUrlFichaTecnica(): ?string { return $this->urlFichaTecnica; }
    public function setUrlFichaTecnica(?string $urlFichaTecnica): self { $this->urlFichaTecnica = $urlFichaTecnica; return $this; }
    public function getUrlImage(): ?string { return $this->urlImage; }
    public function setUrlImage(?string $urlImage): self { $this->urlImage = $urlImage; return $this; }
    public function isIsForChildren(): ?bool { return $this->isForChildren; }
    public function setIsForChildren(?bool $isForChildren): self { $this->isForChildren = $isForChildren; return $this; }
    public function isImagenDescargada(): bool { return $this->imagenDescargada; }
    public function setImagenDescargada(bool $imagenDescargada): self { $this->imagenDescargada = $imagenDescargada; return $this; }
    public function isArticuloPublicitario(): bool { return $this->articuloPublicitario; }
    public function setArticuloPublicitario(bool $articuloPublicitario): self { $this->articuloPublicitario = $articuloPublicitario; return $this; }
    public function isIsNovelty(): ?bool { return $this->isNovelty; }
    public function setIsNovelty(?bool $isNovelty): self { $this->isNovelty = $isNovelty; return $this; }
    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): self { $this->activo = $activo; return $this; }
    public function isDestacado(): bool { return $this->destacado; }
    public function setDestacado(bool $destacado): self { $this->destacado = $destacado; return $this; }
    public function getComposicion(): ?string { return $this->composicion; }
    public function setComposicion(?string $composicion): self { $this->composicion = $composicion; return $this; }
    public function getGramaje(): ?int { return $this->gramaje; }
    public function setGramaje(?int $gramaje): self { $this->gramaje = $gramaje; return $this; }
    public function getPack(): ?int { return $this->pack; }
    public function setPack(?int $pack): self { $this->pack = $pack; return $this; }
    public function getBox(): ?int { return $this->box; }
    public function setBox(?int $box): self { $this->box = $box; return $this; }
    public function getImportancia(): ?int { return $this->importancia; }
    public function setImportancia(?int $importancia): self { $this->importancia = $importancia; return $this; }
    public function getDescuentoEspecial(): ?string { return $this->descuentoEspecial; }
    public function setDescuentoEspecial(string $descuentoEspecial): self { $this->descuentoEspecial = $descuentoEspecial; return $this; }
    public function isAcumulaTotal(): ?bool { return $this->acumulaTotal; }
    public function setAcumulaTotal(?bool $acumulaTotal): self { $this->acumulaTotal = $acumulaTotal; return $this; }
    public function isObligadaVentaEnPack(): bool { return $this->obligadaVentaEnPack; }
    public function setObligadaVentaEnPack(bool $obligadaVentaEnPack): self { $this->obligadaVentaEnPack = $obligadaVentaEnPack; return $this; }
    public function getPrecioMinAdulto(): ?string { return $this->precioMinAdulto; }
    public function setPrecioMinAdulto(string $precioMinAdulto): self { $this->precioMinAdulto = $precioMinAdulto; return $this; }
    public function getPrecioMin(): ?string { return $this->precioMin; }
    public function setPrecioMin(string $precioMin): self { $this->precioMin = $precioMin; return $this; }
    public function getUrl360(): ?string { return $this->url360; }
    public function setUrl360(?string $url360): self { $this->url360 = $url360; return $this; }
    public function getDescripcionTusKamisetas(): ?string { return $this->descripcionTusKamisetas; }
    public function setDescripcionTusKamisetas(?string $descripcionTusKamisetas): self { $this->descripcionTusKamisetas = $descripcionTusKamisetas; return $this; }
    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $observaciones): self { $this->observaciones = $observaciones; return $this; }
    public function getDetailsImages(): ?string { return $this->detailsImages; }
    public function setDetailsImages(?string $detailsImages): self { $this->detailsImages = $detailsImages; return $this; }
    public function getChildImage(): ?string { return $this->childImage; }
    public function setChildImage(?string $childImage): self { $this->childImage = $childImage; return $this; }
    public function getOtherImages(): ?string { return $this->otherImages; }
    public function setOtherImages(?string $otherImages): self { $this->otherImages = $otherImages; return $this; }
    public function getProveedor(): ?Proveedor { return $this->proveedor; }
    public function setProveedor(?Proveedor $proveedor): self { $this->proveedor = $proveedor; return $this; }
    public function getFabricante(): ?Fabricante { return $this->fabricante; }
    public function setFabricante(?Fabricante $fabricante): self { $this->fabricante = $fabricante; return $this; }
    public function getGender(): ?Genero { return $this->gender; }
    public function setGender(?Genero $gender): self { $this->gender = $gender; return $this; }
    public function getTarifa(): ?Tarifa { return $this->tarifa; }
    public function setTarifa(?Tarifa $tarifa): self { $this->tarifa = $tarifa; return $this; }
    public function getImagen(): ?Media { return $this->imagen; }
    public function setImagen(?Media $imagen): self { $this->imagen = $imagen; return $this; }
    public function getDrawing(): ?Media { return $this->drawing; }
    public function setDrawing(?Media $drawing): self { $this->drawing = $drawing; return $this; }
    public function getFichaTecnica(): ?Media { return $this->fichaTecnica; }
    public function setFichaTecnica(?Media $fichaTecnica): self { $this->fichaTecnica = $fichaTecnica; return $this; }

    /** @return Collection<int, Producto> */
    public function getProductos(): Collection { return $this->productos; }
    public function addProducto(Producto $producto): self { if (!$this->productos->contains($producto)) { $this->productos->add($producto); $producto->setModelo($this); } return $this; }
    public function removeProducto(Producto $producto): self { if ($this->productos->removeElement($producto)) { if ($producto->getModelo() === $this) { $producto->setModelo(null); } } return $this; }

    /** @return Collection<int, Familia> */
    public function getFamilias(): Collection { return $this->familias; }
    public function addFamilia(Familia $familia): self { if (!$this->familias->contains($familia)) { $this->familias->add($familia); $familia->addModelo($this); } return $this; }
    public function removeFamilia(Familia $familia): self { if ($this->familias->removeElement($familia)) { $familia->removeModelo($this); } return $this; }

    /** @return Collection<int, Modelo> */
    public function getModelosRelacionados(): Collection { return $this->modelosRelacionados; }
    public function addModelosRelacionado(self $modelo): self { if (!$this->modelosRelacionados->contains($modelo)) { $this->modelosRelacionados->add($modelo); } return $this; }
    public function removeModelosRelacionado(self $modelo): self { $this->modelosRelacionados->removeElement($modelo); return $this; }

    /** @return Collection<int, ModeloAtributo> */
    public function getAtributos(): Collection { return $this->atributos; }
    public function addAtributo(ModeloAtributo $atributo): self { if (!$this->atributos->contains($atributo)) { $this->atributos->add($atributo); } return $this; }
    public function removeAtributo(ModeloAtributo $atributo): self { $this->atributos->removeElement($atributo); return $this; }

    /** @return Collection<int, ModeloHasTecnicasEstampado> */
    public function getTecnicas(): Collection { return $this->tecnicas; }
    public function addTecnica(ModeloHasTecnicasEstampado $tecnica): self { if (!$this->tecnicas->contains($tecnica)) { $this->tecnicas->add($tecnica); $tecnica->setModelo($this); } return $this; }
    public function removeTecnica(ModeloHasTecnicasEstampado $tecnica): self { if ($this->tecnicas->removeElement($tecnica)) { if ($tecnica->getModelo() === $this) { $tecnica->setModelo(null); } } return $this; }

    /** @return Collection<int, ClassificationCategory> */
    public function getCategory(): Collection { return $this->category; }
    public function addCategory(ClassificationCategory $category): self { if (!$this->category->contains($category)) { $this->category->add($category); } return $this; }
    public function removeCategory(ClassificationCategory $category): self { $this->category->removeElement($category); return $this; }

    /**
     * @return string|null
     */
    public function getTituloSEO(): ?string
    {
        return $this->tituloSEO;
    }

    /**
     * @return string|null
     */
    public function getDescripcionSEO(): ?string
    {
        return $this->descripcionSEO;
    }

    /**
     * @return string|null
     */
    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    /**
     * @param string|null $tituloSEO
     */
    public function setTituloSEO(?string $tituloSEO): void
    {
        $this->tituloSEO = $tituloSEO;
    }

    /**
     * @param string|null $descripcionSEO
     */
    public function setDescripcionSEO(?string $descripcionSEO): void
    {
        $this->descripcionSEO = $descripcionSEO;
    }

    /**
     * @param string|null $descripcion
     */
    public function setDescripcion(?string $descripcion): void
    {
        $this->descripcion = $descripcion;
    }


    /**
     * @return Familia|null
     */
    public function getFamilia(): ?Familia
    {
        return $this->familia;
    }

    /**
     * @param Familia|null $familia
     */
    public function setFamilia(?Familia $familia): void
    {
        $this->familia = $familia;
    }
    // ===================================================================
    // MÉTODO getPrecioUnidad CORREGIDO CON TU LÓGICA ORIGINAL
    // ===================================================================
    /**
     * Calcula el precio "Desde..." encontrando el precio mínimo entre todas sus
     * variaciones de producto activas.
     */
    public function getPrecioUnidad(?User $usuario = null): float
    {
        $precioMin = 10000.0; // Empezamos con un valor alto

        // MIGRACIÓN: Se usa la propiedad 'productos' en lugar de 'modeloHasProductos'
        foreach ($this->productos as $producto) {
            if ($producto->isActivo()) {
                $precioProducto = $producto->getPrecioMin($usuario);
                if ($precioProducto < $precioMin) {
                    $precioMin = $precioProducto;
                }
            }
        }

        return ($precioMin < 10000.0) ? $precioMin : (float) $this->getPrecioMin();
    }

    private function testColor($producto)
    {
        if (substr(strtoupper($producto->getColor()->getNombre()), 0, 5) != "BLANC" && substr(strtoupper($producto->getColor()->getNombre()), 0, 5) != "WHITE" && substr(strtoupper($producto->getColor()->getNombre()), 0, 5) != "NATUR") {
            return true;
        }
        return false;
    }

    public function hasColorNino()
    {
        foreach ($this->productos as $producto) {
            if ($producto->isActivo()) {
                if ($producto->getColor() == null) {
                    return false;
                }
                if ($this->testColor($producto)) {
                    if ($producto->isTallaNino()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function hasColor()
    {

        foreach ($this->productos as $producto) {
            if ($producto->isActivo()) {
                if ($this->testColor($producto)) {
                    if (!$producto->isTallaNino()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function getTallasP()
    {
        $tallas = new ArrayCollection();
        foreach ($this->productos as $producto) {
            if ($producto->isActivo()) {
                if (!$tallas->contains($producto->getTalla())) {
                    $tallas[$producto->getTalla()] = $producto;
                }
            }
        }

        return $tallas;
    }

    public function variaPrecioEntreTallas($usuario)
    {
        $productoPrimero = null;
        foreach ($this->getTallasP() as $producto) {
            if ($productoPrimero == null) {
                $productoPrimero = $producto;
                continue;
            }
            if (($productoPrimero->isTallaNino() && $producto->isTallaNino()) || (!$productoPrimero->isTallaNino() && !$producto->isTallaNino())) {
                if ($productoPrimero->getPrecioMin($usuario) != $producto->getPrecioMin($usuario)) {
                    return true;
                }
            }
        }
        return false;
    }


    private function testBlanco($producto)
    {
        if (strpos(strtoupper($producto->getColor()->getNombre()), 'LANCO') || strpos(strtoupper($producto->getColor()->getNombre()), 'HITE') || strpos(strtoupper($producto->getColor()->getNombre()), 'ATURAL')) {
            if (substr(strtoupper($producto->getColor()->getNombre()), 0, 5) == "BLANC" || substr(strtoupper($producto->getColor()->getNombre()), 0, 5) == "WHITE" || substr(strtoupper($producto->getColor()->getNombre()), 0, 5) == "NATUR") {
                return true;
            }
        }
        return false;
    }
    public function hasBlanco()
    {

        foreach ($this->productos as $producto) {
            if ($producto->isActivo()) {
                if ($this->testBlanco($producto)) {
                    if (!$producto->isTallaNino() and $producto->getTalla()) {
                        return true;

                    }
                }
            }
        }

        return false;
    }
    public function hasBlancoNino()
    {

        foreach ($this->productos as $producto) {
            if ($producto->isActivo()) {
                if ($producto->getColor() == null) {
                    return false;
                }
                if ($this->testBlanco($producto)) {
                    if ($producto->isTallaNino()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    public function getPrecioCantidadBlancasNino($cantidad, $usu = null)
    {
        $precioMin = 10000;
        foreach ($this->productos as $producto) {
            if ($producto->isActivo()) {
                if ($this->testBlanco($producto)) {
                    if ($producto->isTallaNino()) {
                        $prc = $producto->getPrecio($cantidad, $cantidad, $usu);
                        if ($prc < $precioMin) {
                            $precioMin = $prc;
                        }
//                    return $producto->getPrecio($cantidad, $cantidad);
                    }
                }
            }
        }
        if ($precioMin < 10000) {
            return $precioMin;
        }

//        var_dump("Estamos devolviendo mal");
        return $this->getPrecioCantidadColorNino($cantidad, $usu);
        return 0;
    }

    public function getPrecioCantidadColorNino($cantidad, $user)
    {
        $precioMin = 10000;
        foreach ($this->productos as $producto) {
            if ($producto->isActivo()) {
                if (!$this->testBlanco($producto)) {
                    if ($producto->isTallaNino()) {
                        $prc = $producto->getPrecio($cantidad, $cantidad, $user);
                        if ($prc < $precioMin) {
                            $precioMin = $prc;
                        }
//                    return $producto->getPrecio($cantidad, $cantidad);
                    }
                }
            }
        }
//        var_dump("Estamos devolviendo mal");
        if ($precioMin < 10000) {
            return $precioMin;
        }
        return 0;
    }

    public function getPrecioCantidadColor($cantidad, $user)
    {
        $precioMinNegro = 10000;
        $precioNegro = 0;
        $precioMin = 10000;
        foreach ($this->productos as $producto) {
            if ($producto->isActivo()) {
                if (!$this->testBlanco($producto)) {
                    if (!$producto->isTallaNino()) {
                        $prc = $producto->getPrecio($cantidad, $cantidad, $user);
                        if ($prc < $precioMin) {
                            $precioMin = $prc;
                        }
                        if (strpos(strtoupper($producto->getColor()->getNombre()), 'EGR') || strpos(strtoupper($producto->getColor()->getNombre()), 'LACK')) {
                            if ($prc < $precioMinNegro) {
                                $precioMinNegro = $prc;
                                $precioNegro = $precioMinNegro;
                            }
                        }
//                    return $producto->getPrecio($cantidad, $cantidad);
                    }
                }
            }
        }
        if ($precioNegro > 0) {
            return $precioNegro;
        }

        if ($precioMin < 10000) {
            return $precioMin;
        }
//        var_dump("Estamos devolviendo mal");
        return 0;
    }


    public function getPrecioCantidadBlancas($cantidad, $user = null)
    {

        $precioMin = 10000;
        foreach ($this->productos as $producto) {
            if ($producto->isActivo()) {
                if ($this->testBlanco($producto)) {
                    if (!$producto->isTallaNino() and $producto->getTalla()) {
                        $prc = $producto->getPrecio($cantidad, $cantidad, $user);
                        if ($prc < $precioMin) {
                            $precioMin = $prc;
                        }
//                    return $producto->getPrecio($cantidad, $cantidad);
                    }
                }
            }
        }

        if ($precioMin < 10000) {
            return $precioMin;
        }
        return $this->getPrecioCantidadColor($cantidad, $user);

//        $devolver = $this->getPrecioCantidadBlancasNino($cantidad);
//
//        return $devolver;
//        var_dump("Estamos devolviendo mal");
        return 0;
    }

    public function getTallasString(): string
    {
        $tallasOrdenadas = [
            // BEBÉ (Meses)
            '0-3', '3M', '03/06M', '3 MESES', '3-6', '6M', '6 MESES', '_0/6', '6-12', '06/12M', '9M', '9 MESES',
            '12M', '12 MESES', '_6/12', '12-18', '12;18', '12/18M', '18M', '18 MESES', '_12/18', '18-24',
            '18/23M', '_18/24', '24M',
            // NIÑO (Años)
            '1/2','2/3','3/4','4/5','5/6','6/7','7/8','8/9','9/10','10/11','11/12',
            '1 AÑO', '90', '92 (1-2)', 'XS (90/1-2)', '1-2', '1/2 AÑOS', '1/2 (86-92)',
            '2 AÑOS', '2T (86/92/XS)', '2-3', '2-3 yrs', '02A',
            '3 AÑOS', '3T (98/S)', '3-4', '3/4 AÑOS', '3;4', '3/4 (98/104)', 'XS (3-4)', 'XS (3/4/104)',
            '4 AÑOS', '4T (104/M)', '4-5', '4/5', '4/6', '4/6 (110-120cm)', '04A', 'S (104/3-4)',
            '5-6', '5/6 AÑOS', '5/6 (110/116)', 'S (5/6)', 'S (116)', 'S (5-6, 116)', 'M (116/5-6)',
            '6 AÑOS', '6', '6-7', '6/7', '6-8', '6/8Y', 'S (6-7)',
            '7-8', '7/8 AÑOS', '7;8', '7-8 (122/128)', 'M (7-8)', 'M (128)', 'M (7-8, 128)', 'L (128/7-8)',
            '8 AÑOS', '8', '8-9', '8/9', '8-10', '8/10', 'M (8-9)',
            '9-10', '9/10 AÑOS', '9;11', '9-11', '9-10 (140)', 'L (9-10, 140)', 'XL (140/9-10)', 'L (9-10, 132)',
            '10 AÑOS', '10', '10-11', '10-12', '10/12', '10/12Y', 'L (10-11)',
            '11-12', '11/12 AÑOS', '11-13 (152)', '12-14', 'XL (11-12, 140)', '2XL (152/11-12)',
            '12 AÑOS', '12', '12-13', '13-14', '13/14 AÑOS', 'L (12-13)', 'XL (12-14)',
            '14 AÑOS', '14', '14-15', '14-16', '14A',
            '16 AÑOS', '16', '15/16A',
            'UNICA NIÑO', 'KID (31/34)',
            // ADULTO (Estándar)
            '3XS/2XS', '3XS', '2XS', '2XS (6)', 'XXS', 'XXS (6)',
            'XS', 'XS (8)', 'XS (3-4/104)', 'XS (34)', '8 (XS/34)',
            'S', 'S (10)', 'S (36)', '10 (S/36)',
            'M', 'M (12)', 'M (12/38)', '12 /M/38)',
            'L', 'L (14)', 'L (14/40)', '14 (L/40)',
            'XL', 'XL (16)', 'XL (16/42)', '16 (XL/42)',
            'XXL', '2XL', '2XL (18)', '2XL (18/44)',
            'XXXL', '3XL', '3XL (20)', '3XL (20/46)',
            'XXXXL', '4XL', '4XL (22)', '4XL (22/48)',
            'XXXXXL', '5XL', '5XL (24)',
            '6XL', '6XL (26)',
            // ADULTO (Combinadas)
            'XS/S', 'XS-S', 'XS/S (8/10)', 'S/M', 'S-M',
            'M/L', 'M-L', 'M/L (12/14)',
            'L/XL', 'L-XL', 'LXL',
            'XL/XXL', 'XL-XXL', 'XL/2XL', 'XL/2XL (16/18)',
            '2XL/3XL', 'XXL/XXXL',
            '3XL/4XL',
            // ADULTO (Numéricas)
            '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47', '48', '50', '52', '54', '56', '58', '60', '62', '64',
            // TALLA ÚNICA
            'UNICA', 'ONE SIZE', 'UNIQUE', 'ONE-SIZE', 'ST',
            // OTRAS
            'JR', 'SR', 'JUNIOR', 'ADULTO',
        ];

        $tallasDelModelo = $this->getTallasArray(); // Usamos el array de tallas del modelo
        $tallasOrderMap = array_flip($tallasOrdenadas);

        usort($tallasDelModelo, function ($a, $b) use ($tallasOrderMap) {
            $posA = $tallasOrderMap[$a] ?? 9999;
            $posB = $tallasOrderMap[$b] ?? 9999;
            return $posA <=> $posB;
        });

        return implode(', ', $tallasDelModelo);
    }


    /**
     * Helper que devuelve las tallas como un array de strings.
     * (Este método puede que ya lo tengas o sea similar a getTallasString).
     */
    public function getTallasArray(): array
    {
        $tallas = [];
        foreach ($this->productos as $producto) {
            if($producto->isActivo()) {
                if ($producto->getTalla() && !in_array($producto->getTalla(), $tallas)) {
                    $tallas[] = $producto->getTalla();
                }
            }
        }
        return $tallas;
    }

    /**
     * @return mixed
     */
    public function getCertificados()
    {
        return $this->certificados;
    }

    /**
     * @param mixed $certificados
     */
    public function setCertificados($certificados): void
    {
        $this->certificados = $certificados;
    }


}