<?php

namespace App\Entity;

use App\Repository\PersonalizacionPrecioCantidadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonalizacionPrecioCantidadRepository::class)]
#[ORM\Table(name: 'personalizacion_precios')]
class PersonalizacionPrecioCantidad
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Personalizacion::class, inversedBy: 'precios')]
    #[ORM\JoinColumn(name: 'personalizacion', referencedColumnName: 'codigo', nullable: false)]
    private ?Personalizacion $personalizacion = null;

    #[ORM\Column]
    private ?int $cantidad = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private ?string $precio = '0.0000';

    #[ORM\Column(name: 'precio2Color', type: 'decimal', precision: 10, scale: 4)]
    private ?string $precio2 = '0.0000';

    #[ORM\Column(name: 'precio_color', type: 'decimal', precision: 10, scale: 4)]
    private ?string $precioColor = '0.0000';

    #[ORM\Column(name: 'precio_color2Color', type: 'decimal', precision: 10, scale: 4)]
    private ?string $precioColor2 = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private ?string $pantalla = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private ?string $repeticion = '0.0000';

    public function __toString(): string
    {
        return sprintf('Desde %d uds.', $this->cantidad ?? 0);
    }

    // --- Lógica de Precios Personalizada ---

    public function getPrecioColor(): ?string
    {
        // Si precioColor no es un valor positivo, devuelve el precio base.
        if (!($this->precioColor > 0)) {
            return $this->precio;
        }
        return $this->precioColor;
    }

    public function setPrecioColor(string $precio): self
    {
        // Si el precio no es un valor positivo, se establece el precio base.
        if (!($precio > 0)) {
            $this->precioColor = $this->precio;
        } else {
            $this->precioColor = $precio;
        }
        return $this;
    }

    public function getPrecioColor2(): ?string
    {
        if (!($this->precioColor2 > 0)) {
            return $this->precio2;
        }
        return $this->precioColor2;
    }

    public function setPrecioColor2(string $precio): self
    {
        if (!($precio > 0)) {
            $this->precioColor2 = $this->precio2;
        } else {
            $this->precioColor2 = $precio;
        }
        return $this;
    }

    // --- Getters y Setters Estándar ---

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCantidad(): ?int
    {
        return $this->cantidad;
    }

    public function setCantidad(int $cantidad): self
    {
        $this->cantidad = $cantidad;
        return $this;
    }

    public function getPrecio(): ?string
    {
        return $this->precio;
    }

    public function setPrecio(string $precio): self
    {
        $this->precio = $precio;
        return $this;
    }

    public function getPrecio2(): ?string
    {
        return $this->precio2;
    }

    public function setPrecio2(string $precio2): self
    {
        $this->precio2 = $precio2;
        return $this;
    }

    public function getPantalla(): ?string
    {
        return $this->pantalla;
    }

    public function setPantalla(string $pantalla): self
    {
        $this->pantalla = $pantalla;
        return $this;
    }

    public function getRepeticion(): ?string
    {
        return $this->repeticion;
    }

    public function setRepeticion(string $repeticion): self
    {
        $this->repeticion = $repeticion;
        return $this;
    }
}