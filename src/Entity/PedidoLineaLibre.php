<?php

namespace App\Entity;

use App\Repository\PedidoLineaLibreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PedidoLineaLibreRepository::class)]
#[ORM\Table(name: 'pedido_linea_libre')]
class PedidoLineaLibre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $cantidad = null;

    #[ORM\ManyToOne(targetEntity: Pedido::class, inversedBy: 'lineasLibres', cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id_pedido')]
    private ?Pedido $pedido = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private ?string $precio = '0.0000';

    public function __toString(): string
    {
        return $this->descripcion ?? 'Línea de pedido libre';
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPedido(): ?Pedido
    {
        return $this->pedido;
    }

    public function setPedido(?Pedido $pedido): self
    {
        $this->pedido = $pedido;
        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): self
    {
        $this->descripcion = $descripcion;
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
}