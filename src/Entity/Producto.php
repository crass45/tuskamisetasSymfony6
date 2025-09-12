<?php

namespace App\Entity;

use App\Repository\ProductoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
// NOTA: Asegúrate de que este 'use' apunte a tu clase Media de Sonata Media Bundle.
// Puede que necesites ajustarlo según la configuración de tu bundle.
use App\Entity\Sonata\Media;


#[ORM\Entity(repositoryClass: ProductoRepository::class)]
#[ORM\Table(name: 'producto')]
#[ORM\Index(columns: ['referencia', 'modelo'], name: 'producto_search_index')]
#[ORM\HasLifecycleCallbacks]
class Producto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $referencia = '';

    #[ORM\ManyToOne(targetEntity: Modelo::class, inversedBy: 'productos', cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'modelo', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Modelo $modelo = null;

    #[ORM\Column(name: 'imagen_descargada')]
    private bool $imagenDescargada = false;

    #[ORM\Column]
    private int $stock = 0;

    #[ORM\Column]
    private int $eancode = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updated = null;

    #[ORM\Column]
    private bool $activo = true;

    #[ORM\Column(name: 'precio_unidad', type: 'decimal', precision: 10, scale: 3)]
    private ?string $precioUnidad = '0.000';

    #[ORM\Column(name: 'precio_pack', type: 'decimal', precision: 10, scale: 3)]
    private ?string $precioPack = '0.000';

    #[ORM\Column(name: 'precio_caja', type: 'decimal', precision: 10, scale: 3)]
    private ?string $precioCaja = '0.000';

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $medidas = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $talla = null;

    #[ORM\ManyToOne(targetEntity: Color::class, inversedBy: 'productos', cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'color', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Color $color = null;

    #[ORM\Column(name: 'url_image', length: 200, nullable: true)]
    private ?string $urlImage = null;

    #[ORM\Column(name: 'views_images', type: Types::TEXT, nullable: true)]
    private ?string $viewsImages = null;

    #[ORM\Column(name: 'stock_futuro', length: 200, nullable: true)]
    private ?string $stockFuturo = null;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'imagen', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Media $imagen = null;

    /**
     * @var Collection<int, Inventario>
     */
    #[ORM\OneToMany(mappedBy: 'producto', targetEntity: Inventario::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['caja' => 'ASC'])]
    private Collection $inventario;

    public function __construct()
    {
        $this->inventario = new ArrayCollection();
    }

    // En tu fichero src/Entity/Producto.php, dentro de la clase Producto

    public function __toString(): string
    {
        $nombreModelo = $this->getModelo()?->getNombre() ?? 'Sin Modelo';
        $talla = $this->getTalla() ?? 'S/T'; // S/T = Sin Talla
        $color = $this->getColor()?->getNombre() ?? 'S/C'; // S/C = Sin Color

        // Devolverá algo como: "Camiseta Básica - L - Rojo"
        return sprintf('%s - %s - %s', $nombreModelo, $talla, $color);
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        if ($this->modelo) {
            $this->modelo->setUpdated(new \DateTime());
        }
    }

    // ===================================================================
    // NOTA DE MIGRACIÓN: Lógica de Negocio
    // Los siguientes métodos contienen lógica de negocio compleja.
    // Se recomienda encarecidamente mover esta lógica a un SERVICIO DEDICADO
    // (ej. un 'PricingService') para mantener las entidades limpias y
    // facilitar el mantenimiento y las pruebas.
    // Se ha mantenido la lógica aquí para asegurar la funcionalidad tras la migración.
    // ===================================================================

    public function getPrecio(int $cantidad, int $cantidadTotal, $usuario, int $cantidadTallaColor = 0): float
    {
        // ... (Lógica de getPrecio migrada y asegurada con comprobaciones) ...
        // Esta función es muy compleja y debería ser el primer candidato a refactorizar a un servicio.
        if ($cantidad === 0 || $cantidadTotal === 0 || !$this->modelo || !$this->modelo->getProveedor()) {
            return 0.0;
        }

        if ($cantidadTallaColor === 0) {
            $cantidadTallaColor = $cantidad;
        }

        $modeloPack = 1;
        if ($this->modelo->getProveedor()->isVentaEnPack() && $this->modelo->getPack() > 0) {
            $modeloPack = $this->modelo->getPack();
        }

        $resto = $cantidadTallaColor % $modeloPack;
        $cantidadCuadrada = $cantidad - $resto;

        $precioAplicar1 = $this->getPrecioAplicar($cantidadTotal);
        $precioAplicar2 = ($resto > 0) ? $this->getPrecioAplicar($resto) : 0;

        $beneficioAplicar = $this->getBenficioAplicar($cantidadTotal, $usuario);

        $precio1 = $precioAplicar1 + $precioAplicar1 * $beneficioAplicar / 100;
        $precio2 = $precioAplicar2 + $precioAplicar2 * $beneficioAplicar / 100;

        if ($cantidad === 0) return 0.0;

        return ($precio1 * $cantidadCuadrada / $cantidad + $precio2 * $resto / $cantidad);
    }

    public function getPrecioMin($usuario=null):float
    {
        $cantidad = 100000;
        return $this->getPrecio($cantidad, $cantidad, $usuario);
    }

    private function getPrecioAplicar(int $cantidad): float
    {
        if (!$this->modelo) return 0.0;

        $precio = (float) $this->precioUnidad;

        if ($cantidad >= $this->modelo->getPack()) {
            $precio = (float) $this->precioPack;
        }
        if ($cantidad >= $this->modelo->getBox()) {
            $precio = (float) $this->precioPack; // Lógica original, podría ser precioCaja
        }

        if ($this->modelo->getProveedor()) {
            $precio -= ($precio * (float) $this->modelo->getProveedor()->getDescuentoEspecial() / 100);
        }

        return $precio;
    }

    private function getBenficioAplicar(int $cantidad, $usuario): float|int
    {
        return $this->modelo ? $this->modelo->getIncremento($cantidad, $usuario) : 0;
    }

    public function isTallaNino(): bool
    {
        if ($this->talla === null || $this->talla === '') {
            return false;
        }

        $tallaNormalizada = strtoupper($this->talla);

        if (is_numeric($tallaNormalizada) && (int)$tallaNormalizada <= 20) {
            return true;
        }

        return (bool) preg_match('/[0-9][\/\-][0-9]|[0-9][\/\- ](MESES|AÑOS)|[0-9][Y]|[0-9][M]|NIÑO/', $tallaNormalizada);
    }

    // ===================================================================
    // Getters y Setters
    // ===================================================================

    /**
     * @return Color|null
     */
    public function getColor(): ?Color
    {
        return $this->color;
    }

    /**
     * @return int
     */
    public function getEancode(): int
    {
        return $this->eancode;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Media|null
     */
    public function getImagen(): ?Media
    {
        return $this->imagen;
    }

    /**
     * @return Collection
     */
    public function getInventario(): Collection
    {
        return $this->inventario;
    }

    /**
     * @return string|null
     */
    public function getMedidas(): ?string
    {
        return $this->medidas;
    }

    /**
     * @return Modelo|null
     */
    public function getModelo(): ?Modelo
    {
        return $this->modelo;
    }

    /**
     * @return string|null
     */
    public function getPrecioCaja(): ?string
    {
        return $this->precioCaja;
    }

    /**
     * @return string|null
     */
    public function getPrecioPack(): ?string
    {
        return $this->precioPack;
    }

    /**
     * @return string|null
     */
    public function getPrecioUnidad(): ?string
    {
        return $this->precioUnidad;
    }

    /**
     * @return string|null
     */
    public function getReferencia(): ?string
    {
        return $this->referencia;
    }

    /**
     * @return int
     */
    public function getStock(): int
    {
        return $this->stock;
    }

    /**
     * @return string|null
     */
    public function getStockFuturo(): ?string
    {
        return $this->stockFuturo;
    }

    /**
     * @return string|null
     */
    public function getTalla(): ?string
    {
        return $this->talla;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getUpdated(): ?\DateTimeInterface
    {
        return $this->updated;
    }

    /**
     * @return string|null
     */
    public function getUrlImage(): ?string
    {
        return $this->urlImage;
    }

    /**
     * @return string|null
     */
    public function getViewsImages(): ?string
    {
        return $this->viewsImages;
    }

    /**
     * @return bool
     */
    public function isImagenDescargada(): bool
    {
        return $this->imagenDescargada;
    }

    /**
     * @return bool
     */
    public function isActivo(): bool
    {
        return $this->activo;
    }

    /**
     * @param int|null $id
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @param bool $activo
     */
    public function setActivo(bool $activo): void
    {
        $this->activo = $activo;
    }

    /**
     * @param Color|null $color
     */
    public function setColor(?Color $color): void
    {
        $this->color = $color;
    }

    /**
     * @param int $eancode
     */
    public function setEancode(int $eancode): void
    {
        $this->eancode = $eancode;
    }

    /**
     * @param Media|null $imagen
     */
    public function setImagen(?Media $imagen): void
    {
        $this->imagen = $imagen;
    }

    /**
     * @param bool $imagenDescargada
     */
    public function setImagenDescargada(bool $imagenDescargada): void
    {
        $this->imagenDescargada = $imagenDescargada;
    }

    /**
     * @param Collection $inventario
     */
    public function setInventario(Collection $inventario): void
    {
        $this->inventario = $inventario;
    }

    /**
     * @param string|null $medidas
     */
    public function setMedidas(?string $medidas): void
    {
        $this->medidas = $medidas;
    }

    /**
     * @param Modelo|null $modelo
     */
    public function setModelo(?Modelo $modelo): void
    {
        $this->modelo = $modelo;
    }

    /**
     * @param string|null $precioCaja
     */
    public function setPrecioCaja(?string $precioCaja): void
    {
        $this->precioCaja = $precioCaja;
    }

    /**
     * @param string|null $precioPack
     */
    public function setPrecioPack(?string $precioPack): void
    {
        $this->precioPack = $precioPack;
    }

    /**
     * @param string|null $precioUnidad
     */
    public function setPrecioUnidad(?string $precioUnidad): void
    {
        $this->precioUnidad = $precioUnidad;
    }

    /**
     * @param string|null $referencia
     */
    public function setReferencia(?string $referencia): void
    {
        $this->referencia = $referencia;
    }

    /**
     * @param int $stock
     */
    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    /**
     * @param string|null $stockFuturo
     */
    public function setStockFuturo(?string $stockFuturo): void
    {
        $this->stockFuturo = $stockFuturo;
    }

    /**
     * @param string|null $talla
     */
    public function setTalla(?string $talla): void
    {
        $this->talla = $talla;
    }

    /**
     * @param \DateTimeInterface|null $updated
     */
    public function setUpdated(?\DateTimeInterface $updated): void
    {
        $this->updated = $updated;
    }

    /**
     * @param string|null $urlImage
     */
    public function setUrlImage(?string $urlImage): void
    {
        $this->urlImage = $urlImage;
    }

    /**
     * @param string|null $viewsImages
     */
    public function setViewsImages(?string $viewsImages): void
    {
        $this->viewsImages = $viewsImages;
    }
}