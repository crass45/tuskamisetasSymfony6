<?php

namespace App\Entity;

use App\Repository\PedidoLineaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PedidoLineaRepository::class)]
#[ORM\Table(name: 'pedido_linea')]
class PedidoLinea
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $cantidad = null;

    #[ORM\ManyToOne(targetEntity: Pedido::class, inversedBy: 'lineas', cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id_pedido')]
    private ?Pedido $pedido = null;

    #[ORM\ManyToOne(targetEntity: Producto::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id_producto')]
    private ?Producto $producto = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $personalizacion = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private ?string $precio = '0.0000';

    /**
     * @var Collection<int, PedidoLineaHasTrabajo>
     */
    #[ORM\OneToMany(mappedBy: 'pedidoLinea', targetEntity: PedidoLineaHasTrabajo::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    private Collection $personalizaciones;

    public function __construct()
    {
        $this->personalizaciones = new ArrayCollection();
    }

    public function __toString(): string
    {
        if ($this->producto) {
            $modeloNombre = $this->producto->getModelo()?->getNombre() ?? '';
            $talla = $this->producto->getTalla() ?? '';
            $color = $this->producto->getColor()?->__toString() ?? '';
            return sprintf('%s %s %s (%d unidades)', $modeloNombre, $talla, $color, $this->cantidad);
        }
        return sprintf('Línea de pedido: %d', $this->id);
    }

    // --- Getters y Setters ---

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

    public function getProducto(): ?Producto
    {
        return $this->producto;
    }

    public function setProducto(?Producto $producto): self
    {
        $this->producto = $producto;
        return $this;
    }

    public function getPersonalizacion(): ?string
    {
        return $this->personalizacion;
    }

    public function setPersonalizacion(?string $personalizacion): self
    {
        $this->personalizacion = $personalizacion;
        return $this;
    }

    public function getPrecio(): ?string
    {
        return $this->precio;
    }

    public function setPrecio(string $precio): self
    {
        // NOTA DE MIGRACIÓN: Se ha conservado la lógica de redondeo.
        // A largo plazo, esta lógica debería estar en un servicio en lugar de en el setter de la entidad.
        $this->precio = $this->roundUp($precio, 4);
        return $this;
    }

    /**
     * @return Collection<int, PedidoLineaHasTrabajo>
     */
    public function getPersonalizaciones(): Collection
    {
        return $this->personalizaciones;
    }

    public function addPersonalizacione(PedidoLineaHasTrabajo $personalizacione): self
    {
        if (!$this->personalizaciones->contains($personalizacione)) {
            $this->personalizaciones->add($personalizacione);
            $personalizacione->setPedidoLinea($this);
        }
        return $this;
    }

    public function removePersonalizacione(PedidoLineaHasTrabajo $personalizacione): self
    {
        if ($this->personalizaciones->removeElement($personalizacione)) {
            // set the owning side to null (unless already changed)
            if ($personalizacione->getPedidoLinea() === $this) {
                $personalizacione->setPedidoLinea(null);
            }
        }
        return $this;
    }

    /**
     * Reimplementación del antiguo Utiles::round_up.
     */
    private function roundUp(float|string $value, int $precision): string
    {
        $pow = 10 ** $precision;
        $rounded = (ceil($pow * (float)$value) + ceil($pow * (float)$value - ceil($pow * (float)$value))) / $pow;
        return number_format($rounded, $precision, '.', '');
    }
}