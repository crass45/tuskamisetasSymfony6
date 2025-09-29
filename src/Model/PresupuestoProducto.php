<?php
// src/Model/PresupuestoProducto.php

namespace App\Model;

use App\Entity\PedidoLinea;
use App\Entity\Producto;
use App\Entity\Sonata\User; // NOTA: Asumimos que tu entidad de usuario se llamará 'User'

/**
 * Representa un producto dentro de un presupuesto. No es una entidad de Doctrine.
 * Se utiliza para ser almacenado en la sesión.
 */
class PresupuestoProducto
{
    private ?int $id = null;
    private ?int $cantidad = 0;
    private ?string $color = null;
    private ?Producto $producto = null;
    private float $precioProducto = 0.0;
    private int $cantidadTotal = 0;
    private int $cantidadFabricante = 0;

    // --- MÉTODOS DE SERIALIZACIÓN MODERNOS PARA PHP 8+ ---

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'cantidad' => $this->cantidad,
            'producto' => $this->producto,
            'color' => $this->color,
            'precioProducto' => $this->precioProducto,
            'cantidadTotal' => $this->cantidadTotal,
            'cantidadFabricante' => $this->cantidadFabricante,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->cantidad = $data['cantidad'] ?? 0;
        $this->producto = $data['producto'] ?? null;
        $this->color = $data['color'] ?? null;
        $this->precioProducto = $data['precioProducto'] ?? 0.0;
        $this->cantidadTotal = $data['cantidadTotal'] ?? 0;
        $this->cantidadFabricante = $data['cantidadFabricante'] ?? 0;
    }

    // --- GETTERS Y SETTERS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $idProducto): self
    {
        $this->id = $idProducto;
        return $this;
    }

    public function getCantidad(): ?int
    {
        return $this->cantidad;
    }

    public function setCantidad(?int $cantidad): self
    {
        $this->cantidad = $cantidad;
        return $this;
    }

    public function addCantidad(int $suma): self
    {
        $this->cantidad += $suma;
        return $this;
    }

    public function getProducto(): ?Producto
    {
        return $this->producto;
    }

    // NOTA: Revisa que el tipo del argumento $user sea el correcto para tu aplicación
    public function setProducto(Producto $prod, int $cantidadTotal, ?User $user): self
    {
        $this->cantidadTotal = $cantidadTotal;
        $this->setId($prod->getId());
        $this->producto = $prod;

        if ($prod->getColor()) {
            $this->color = $prod->getColor()->getNombre();
        }

        $this->precioProducto = $prod->getPrecio($cantidadTotal, $cantidadTotal, $user);
        return $this;
    }

    // NOTA: Revisa que el tipo del argumento $user sea el correcto para tu aplicación
    public function ajustaPrecio(?User $user): void
    {
        if (!$this->producto) {
            return;
        }

        $cant = $this->cantidadFabricante > 0 ? $this->cantidadFabricante : $this->cantidadTotal;
        $this->precioProducto = $this->producto->getPrecio($cant, $this->cantidadTotal, $user);
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getPrecioProducto(): float
    {
        return $this->precioProducto;
    }

    public function getCantidadTotal(): int
    {
        return $this->cantidadTotal;
    }

    public function getCantidadFabricante(): int
    {
        return $this->cantidadFabricante;
    }

    public function setCantidadFabricante(int $cantidadFabricante): self
    {
        $this->cantidadFabricante = $cantidadFabricante;
        return $this;
    }

    /**
     * @param int $cantidadTotal
     */
    public function setCantidadTotal(int $cantidadTotal): void
    {
        $this->cantidadTotal = $cantidadTotal;
    }

    /**
     * NUEVO MÉTODO: Rellena este objeto con los datos de un producto de un pedido existente.
     */
    public function fromPedidoLinea(PedidoLinea $linea): void
    {
        $this->setId($linea->getProducto()->getId());
        $this->setCantidad($linea->getCantidad());
        // Se establece el producto. El precio se recalculará dinámicamente en el carrito
        // cuando se llame al método setProducto.
        $this->setProducto($linea->getProducto(), $linea->getCantidad(), null);
    }
}