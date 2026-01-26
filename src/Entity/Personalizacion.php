<?php

namespace App\Entity;

use App\Repository\PersonalizacionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonalizacionRepository::class)]
#[ORM\Table(name: 'personalizacion')]
class Personalizacion
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $codigo = null;

    #[ORM\Column(name: 'titulo', length: 100)]
    private ?string $nombre = '';

    #[ORM\Column(name: 'trabajo_minimo_por_color', type: 'decimal', precision: 10, scale: 4)]
    private ?string $trabajoMinimoPorColor = '0.0000';

    #[ORM\Column(nullable: true)]
    private ?int $numeroMaximoColores = 0;

    #[ORM\Column(nullable: true)]
    private ?int $teccode = 0;

    #[ORM\Column(name: 'incremento', type: 'decimal', precision: 10, scale: 4)]
    private ?string $incrementoPrecio = '0.0000';

    /**
     * @var Collection<int, PersonalizacionPrecioCantidad>
     */
    #[ORM\OneToMany(mappedBy: 'personalizacion', targetEntity: PersonalizacionPrecioCantidad::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['cantidad' => 'DESC'])]
    private Collection $precios;

    /**
     * @var Collection<int, ModeloHasTecnicasEstampado>
     */
    #[ORM\OneToMany(mappedBy: 'personalizacion', targetEntity: ModeloHasTecnicasEstampado::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['personalizacion' => 'ASC'])]
    private Collection $modelos;


    #[ORM\ManyToOne(targetEntity: Proveedor::class)]
    #[ORM\JoinColumn(nullable: true)] // Puede ser null si es una antigua tuya sin clasificar
    private ?Proveedor $proveedor = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $tiempoPersonalizacion = 0;


    public function getProveedor(): ?Proveedor
    {
        return $this->proveedor;
    }

    public function setProveedor(?Proveedor $proveedor): self
    {
        $this->proveedor = $proveedor;
        return $this;
    }

    public function __construct()
    {
        $this->precios = new ArrayCollection();
        $this->modelos = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('%s %s', $this->codigo, $this->nombre);
    }

    // ===================================================================
    // NOTA DE ARQUITECTURA: Lógica de Negocio
    // Se recomienda encarecidamente mover este método a un SERVICIO DEDICADO
    // (ej. 'PersonalizacionPricingService') para mantener las entidades limpias.
    // ===================================================================

    public function getPrecio(int $cantidadBlancas, int $cantidadColor, int $numColores): float
    {
        $cantidad = $cantidadBlancas + $cantidadColor;
        if ($cantidad === 0 || $numColores === 0) {
            return 0.0;
        }

        $precioSeleccionado = null;
        foreach ($this->precios as $precio) {
            if ($cantidad >= $precio->getCantidad()) {
                $precioSeleccionado = $precio;
                break; // La colección está ordenada por cantidad DESC, el primero que cumple es el correcto
            }
        }

        if ($precioSeleccionado === null) {
            return 0.0; // No se encontró un rango de precios aplicable
        }

        $precioBaseBlancas = (float) $precioSeleccionado->getPrecio();
        $precioExtraColorBlancas = (float) $precioSeleccionado->getPrecio2();
        $precioBaseColor = (float) $precioSeleccionado->getPrecioColor();
        $precioExtraColorColor = (float) $precioSeleccionado->getPrecioColor2();
        $costePantalla = (float) $precioSeleccionado->getPantalla();

        $costePrendasBlancas = ($precioBaseBlancas + $precioExtraColorBlancas * ($numColores - 1)) * $cantidadBlancas;
        $costePrendasColor = ($precioBaseColor + $precioExtraColorColor * ($numColores - 1)) * $cantidadColor;

        $costeFijoTotal = $costePantalla * $numColores;

        $precioMedioPorPrenda = ($costePrendasBlancas + $costePrendasColor + $costeFijoTotal) / $cantidad;

        $precioConIncremento = $precioMedioPorPrenda + ($precioMedioPorPrenda * (float) $this->incrementoPrecio / 100);

        $trabajoMinimoPorPrenda = ((float) $this->trabajoMinimoPorColor * $numColores + $costeFijoTotal) / $cantidad;
        $trabajoMinimoConIncremento = $trabajoMinimoPorPrenda + ($trabajoMinimoPorPrenda * (float) $this->incrementoPrecio / 100);

        return max($trabajoMinimoConIncremento, $precioConIncremento);
    }

    // --- Getters y Setters ---

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

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getTrabajoMinimoPorColor(): ?string
    {
        return $this->trabajoMinimoPorColor;
    }

    public function setTrabajoMinimoPorColor(string $trabajoMinimoPorColor): self
    {
        $this->trabajoMinimoPorColor = $trabajoMinimoPorColor;
        return $this;
    }

    public function getNumeroMaximoColores(): ?int
    {
        return $this->numeroMaximoColores;
    }

    public function setNumeroMaximoColores(?int $numeroMaximoColores): self
    {
        $this->numeroMaximoColores = $numeroMaximoColores;
        return $this;
    }

    public function getTeccode(): ?int
    {
        return $this->teccode;
    }

    public function setTeccode(?int $teccode): self
    {
        $this->teccode = $teccode;
        return $this;
    }

    public function getIncrementoPrecio(): ?string
    {
        return $this->incrementoPrecio;
    }

    public function setIncrementoPrecio(string $incrementoPrecio): self
    {
        $this->incrementoPrecio = $incrementoPrecio;
        return $this;
    }

    /**
     * @return Collection<int, PersonalizacionPrecioCantidad>
     */
    public function getPrecios(): Collection
    {
        return $this->precios;
    }

    public function addPrecio(PersonalizacionPrecioCantidad $precio): self
    {
        if (!$this->precios->contains($precio)) {
            $this->precios->add($precio);
            $precio->setPersonalizacion($this);
        }
        return $this;
    }

    public function removePrecio(PersonalizacionPrecioCantidad $precio): self
    {
        if ($this->precios->removeElement($precio)) {
            if ($precio->getPersonalizacion() === $this) {
                $precio->setPersonalizacion(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ModeloHasTecnicasEstampado>
     */
    public function getModelos(): Collection
    {
        return $this->modelos;
    }

    public function addModelo(ModeloHasTecnicasEstampado $modelo): self
    {
        if (!$this->modelos->contains($modelo)) {
            $this->modelos->add($modelo);
            $modelo->setPersonalizacion($this);
        }
        return $this;
    }

    public function removeModelo(ModeloHasTecnicasEstampado $modelo): self
    {
        if ($this->modelos->removeElement($modelo)) {
            if ($modelo->getPersonalizacion() === $this) {
                $modelo->setPersonalizacion(null);
            }
        }
        return $this;
    }

    public function getTiempoPersonalizacion(): int
    {
        return $this->tiempoPersonalizacion;
    }

    public function setTiempoPersonalizacion(int $tiempoPersonalizacion): self
    {
        $this->tiempoPersonalizacion = $tiempoPersonalizacion;
        return $this;
    }
}