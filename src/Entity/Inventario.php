<?php

namespace App\Entity;

use App\Repository\InventarioRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventarioRepository::class)]
#[ORM\Table(name: 'inventario')]
class Inventario
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Producto::class, inversedBy: 'inventario', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'id_producto', referencedColumnName: 'id', nullable: false)]
    private ?Producto $producto = null;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $caja = null;

    #[ORM\Column]
    private ?int $cantidad = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observaciones = null;

    public function __toString(): string
    {
        $productoRef = $this->producto?->getReferencia() ?? 'N/A';
        return sprintf('Inventario Producto %s - Caja %d', $productoRef, $this->caja ?? 0);
    }

    // --- Métodos de Lógica ---

    public function addCantidad(int $cantidadASumar): self
    {
        $this->cantidad += $cantidadASumar;
        return $this;
    }

    public function lessCantidad(int $cantidadARestar): self
    {
        $this->cantidad -= $cantidadARestar;
        if ($this->cantidad < 0) {
            $this->cantidad = 0;
        }
        return $this;
    }

    // --- Getters y Setters ---

    public function getProducto(): ?Producto
    {
        return $this->producto;
    }

    public function setProducto(?Producto $producto): self
    {
        $this->producto = $producto;
        return $this;
    }

    public function getCaja(): ?int
    {
        return $this->caja;
    }

    public function setCaja(int $caja): self
    {
        $this->caja = $caja;
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

    public function getObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function setObservaciones(?string $observaciones): self
    {
        $this->observaciones = $observaciones;
        return $this;
    }
}