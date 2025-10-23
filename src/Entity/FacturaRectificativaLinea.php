<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FacturaRectificativaLinea
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lineas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FacturaRectificativa $facturaRectificativa = null;

    #[ORM\Column(length: 255)]
    private ?string $descripcion = null;

    #[ORM\Column]
    private ?int $cantidad = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private ?string $precio = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFacturaRectificativa(): ?FacturaRectificativa
    {
        return $this->facturaRectificativa;
    }

    public function setFacturaRectificativa(?FacturaRectificativa $facturaRectificativa): self
    {
        $this->facturaRectificativa = $facturaRectificativa;
        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(string $descripcion): self
    {
        $this->descripcion = $descripcion;
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

    public function getTotal(): float
    {
        return $this->cantidad * $this->precio;
    }
}
