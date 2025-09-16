<?php
// src/Model/Presupuesto.php

namespace App\Model;

use App\Entity\Sonata\User;
use App\Model\PresupuestoProducto;
use App\Model\PresupuestoTrabajo;

/**
 * Representa el 'carrito' de presupuesto completo. No es una entidad de Doctrine.
 * Contiene los productos y trabajos, y toda la lógica de cálculo.
 * Se almacena en la sesión del usuario.
 */
class Presupuesto
{
    /** @var PresupuestoProducto[] */
    private array $productos = [];

    /** @var PresupuestoTrabajo[] */
    private array $trabajos = [];

    // --- MÉTODOS DE SERIALIZACIÓN MODERNOS PARA PHP 8+ ---

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'productos' => $this->productos,
            'trabajos' => $this->trabajos,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->productos = $data['productos'] ?? [];
        $this->trabajos = $data['trabajos'] ?? [];
    }

    // --- Métodos de Gestión de Trabajos ---

    /**
     * @return PresupuestoTrabajo[]
     */
    public function getTrabajos(): array
    {
        return $this->trabajos;
    }

    public function addTrabajo(PresupuestoTrabajo $item): self
    {
        $this->trabajos[] = $item;
        return $this;
    }

    public function eliminaTrabajo(PresupuestoTrabajo $itemParaEliminar): self
    {
        $this->trabajos = array_filter($this->trabajos, function (PresupuestoTrabajo $trabajo) use ($itemParaEliminar) {
            return $trabajo->getIdentificadorTrabajo() !== $itemParaEliminar->getIdentificadorTrabajo();
        });
        return $this;
    }

    // --- Métodos de Gestión de Productos ---

    /**
     * @return PresupuestoProducto[]
     */
    public function getProductos(): array
    {
        return $this->productos;
    }

    public function addProducto(PresupuestoProducto $item, ?User $user): self
    {
        if ($item->getCantidad() <= 0) {
            return $this;
        }

        foreach ($this->productos as $productoExistente) {
            if ($productoExistente->getId() === $item->getId()) {
                $productoExistente->addCantidad($item->getCantidad());
                $this->updateProductos($user);
                return $this;
            }
        }

        $this->productos[] = $item;
        $this->updateProductos($user);

        return $this;
    }

    public function eliminaProducto(int $idProducto, int $cantidad, string $trabajos, ?User $user): self
    {
        if ($this->getTrabajosString() !== $trabajos) {
            return $this;
        }

        $this->productos = array_filter($this->productos, function (PresupuestoProducto $p) use ($idProducto, $cantidad) {
            return !($p->getProducto()?->getId() === $idProducto && $p->getCantidad() === $cantidad);
        });

        $this->updateProductos($user);
        return $this;
    }

    public function lessProducto(int $idProducto, int $cantidad, string $trabajos, ?User $user): void
    {
        if ($this->getTrabajosString() !== $trabajos) {
            return;
        }

        foreach ($this->productos as $key => $productoPresupuesto) {
            if ($productoPresupuesto->getProducto()?->getId() === $idProducto && $productoPresupuesto->getCantidad() === $cantidad) {
                $modelo = $productoPresupuesto->getProducto()->getModelo();
                $cantidadARestar = ($modelo?->getObligadaVentaEnPack()) ? $modelo->getPack() : 1;

                $nuevaCantidad = $productoPresupuesto->getCantidad() - $cantidadARestar;

                if ($nuevaCantidad <= 0) {
                    unset($this->productos[$key]);
                } else {
                    $productoPresupuesto->setCantidad($nuevaCantidad);
                }
                break; // Salimos del bucle una vez encontrado y modificado
            }
        }
        $this->productos = array_values($this->productos); // Reindexar array
        $this->updateProductos($user);
    }

    public function upProducto(int $idProducto, int $cantidad, string $trabajos, ?User $user): void
    {
        if ($this->getTrabajosString() !== $trabajos) {
            return;
        }

        foreach ($this->productos as $productoPresupuesto) {
            if ($productoPresupuesto->getProducto()?->getId() === $idProducto && $productoPresupuesto->getCantidad() === $cantidad) {
                $modelo = $productoPresupuesto->getProducto()?->getModelo();
                $proveedor = $modelo?->getProveedor();
                $producto = $productoPresupuesto->getProducto();

                if (!$modelo || !$proveedor || !$producto) continue;

                $cantidadASumar = $modelo->getObligadaVentaEnPack() ? $modelo->getPack() : 1;
                $cantidadRequerida = $productoPresupuesto->getCantidad() + $cantidadASumar;

                $stockSuficiente = !$proveedor->getControlDeStock() || $proveedor->isPermiteVentaSinStock() || ($producto->getStock() >= $cantidadRequerida);

                if ($stockSuficiente) {
                    $productoPresupuesto->setCantidad($cantidadRequerida);
                }
                break;
            }
        }
        $this->updateProductos($user);
    }

    public function updateProductos(?User $user): void
    {
        $cantidadTotal = $this->getCantidadProductos();

        foreach ($this->productos as $productoPresupuesto) {
            $productoPresupuesto->setCantidadTotal($cantidadTotal);
            $cantidadFabricante = $this->getCantidadFabricante($productoPresupuesto->getProducto()?->getModelo()?->getProveedor()?->getId());
            $productoPresupuesto->setCantidadFabricante($cantidadFabricante);
            $productoPresupuesto->ajustaPrecio($user);
        }
    }

    // --- Métodos de Cálculo y Totales ---

    public function getCantidadProductos(): int
    {
        return array_sum(array_map(fn(PresupuestoProducto $p) => $p->getCantidad(), $this->productos));
    }

    public function getCantidadFabricante(?int $idFabricante): int
    {
        if ($idFabricante === null) return 0;

        $cantidadFabricante = 0;
        foreach ($this->productos as $producto) {
            if ($producto->getProducto()?->getModelo()?->getProveedor()?->getId() === $idFabricante) {
                $cantidadFabricante += $producto->getCantidad();
            }
        }
        return $cantidadFabricante;
    }

    public function getPrecioTotal(?User $user): float
    {
        $cantidadProductos = $this->getCantidadProductos();
        if ($cantidadProductos === 0) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($this->productos as $producto) {
            if ($producto->getProducto()) {
                $total += $producto->getProducto()->getPrecio($producto->getCantidad(), $cantidadProductos, $user) * $producto->getCantidad();
            }
        }

        return round($total + $this->getTotalTrabajo(), 2);
    }

    public function getTotalTrabajo(): float
    {
        $cantidadProductos = $this->getCantidadProductos();
        if ($cantidadProductos === 0) return 0.0;

        $totalTrabajo = 0.0;
        $totalBlancas = 0;
        $totalColor = 0;

        foreach ($this->productos as $producto) {
            $colorNombre = strtoupper((string) $producto->getColor());
            if (str_contains($colorNombre, 'LANCO') || str_contains($colorNombre, 'HITE')) {
                $totalBlancas += $producto->getCantidad();
            } else {
                $totalColor += $producto->getCantidad();
            }
        }

        foreach ($this->trabajos as $trabajo) {
            $personalizacion = $trabajo->getTrabajo();
            if ($personalizacion) {
                $totalTrabajo += $personalizacion->getPrecio($totalBlancas, $totalColor, $trabajo->getCantidad()) * $cantidadProductos;
            }
        }
        return $totalTrabajo;
    }

    public function getPrecioUnidad(?User $user): float
    {
        $cantidad = $this->getCantidadProductos();
        if ($cantidad === 0) {
            return 0.0;
        }
        return round($this->getPrecioTotal($user) / $cantidad, 2);
    }

    // --- Métodos de Ayuda ---

    public function getTrabajosString(): string
    {
        if (empty($this->trabajos)) return '';
        return implode(',', array_map(fn(PresupuestoTrabajo $t) => $t->getIdentificadorTrabajo(), $this->trabajos));
    }
}