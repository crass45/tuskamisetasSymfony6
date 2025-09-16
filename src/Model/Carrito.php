<?php
// src/Model/Carrito.php

namespace App\Model;

use App\Entity\Sonata\User;
use App\Entity\ZonaEnvio;

/**
 * Representa el Carrito de la compra. No es una entidad de Doctrine.
 * Contiene los grupos de productos (items) y toda la lógica de cálculo.
 * Se almacena en la sesión del usuario.
 */
class Carrito
{
    private ?string $direccionEnvio = '';
    private ?string $codigoPostal = '';
    private ?string $poblacion = '';
    private ?string $provincia = '';
    private float $precioGastos = 0.0;
    private float $precioGastosReducidos = 0.0;
    private int $tipoEnvio = 1;
    private bool $servicioExpres = false;
    private ?string $observaciones = '';
    private int $idPedido = 0;
    private float $descuento = 0.0;

    /** @var Presupuesto[] */
    private array $items = [];

    // --- MÉTODOS DE SERIALIZACIÓN MODERNOS PARA PHP 8+ ---

    public function __serialize(): array
    {
        return [
            'direccionEnvio' => $this->direccionEnvio,
            'codigoPostal' => $this->codigoPostal,
            'poblacion' => $this->poblacion,
            'provincia' => $this->provincia,
            'precioGastos' => $this->precioGastos,
            'precioGastosReducidos' => $this->precioGastosReducidos,
            'tipoEnvio' => $this->tipoEnvio,
            'servicioExpres' => $this->servicioExpres,
            'observaciones' => $this->observaciones,
            'idPedido' => $this->idPedido,
            'descuento' => $this->descuento,
            'items' => $this->items,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->direccionEnvio = $data['direccionEnvio'] ?? '';
        $this->codigoPostal = $data['codigoPostal'] ?? '';
        $this->poblacion = $data['poblacion'] ?? '';
        $this->provincia = $data['provincia'] ?? '';
        $this->precioGastos = $data['precioGastos'] ?? 0.0;
        $this->precioGastosReducidos = $data['precioGastosReducidos'] ?? 0.0;
        $this->tipoEnvio = $data['tipoEnvio'] ?? 1;
        $this->servicioExpres = $data['servicioExpres'] ?? false;
        $this->observaciones = $data['observaciones'] ?? '';
        $this->idPedido = $data['idPedido'] ?? 0;
        $this->descuento = $data['descuento'] ?? 0.0;
        $this->items = $data['items'] ?? [];
    }

    // --- LÓGICA DE GESTIÓN DEL CARRITO ---

    public function addItem(Presupuesto $item, ?User $user = null): void
    {
        foreach ($this->items as $carritoItem) {
            if ($this->comparaItems($carritoItem, $item)) {
                $carritoItem->addProductos($item->getProductos(), $user);
                return;
            }
        }
        $this->items[] = $item;
    }

    private function comparaItems(Presupuesto $carritoItem, Presupuesto $item): bool
    {
        return $carritoItem->getTrabajosString() === $item->getTrabajosString();
    }

    public function eliminaItem(Presupuesto $itemParaEliminar): void
    {
        $this->items = array_filter($this->items, fn(Presupuesto $item) => $item !== $itemParaEliminar);
    }

    // --- LÓGICA DE CÁLCULO DE PRECIOS Y CANTIDADES ---

    public function getSubTotal(?User $user): float
    {
        $subtotal = 0.0;
        foreach ($this->items as $indice => $item) {
            foreach ($item->getProductos() as $producto) {
                if ($producto->getProducto()) {
                    $subtotal += $this->getPrecioProducto($producto->getProducto()->getId(), $indice, $user) * $producto->getCantidad();
                }
            }
        }
        return $subtotal;
    }

    public function getPrecioProducto(?int $productoID, int $itemIndex, ?User $user): float
    {
        if ($productoID === null) return 0.0;

        $precio = 0.0;
        $item = $this->items[$itemIndex] ?? null;

        if (!$item) return 0.0;

        foreach ($item->getProductos() as $productoPresupuesto) {
            if ($productoPresupuesto->getId() === $productoID && $productoPresupuesto->getCantidad() > 0) {
                $productoEntity = $productoPresupuesto->getProducto();
                if ($productoEntity) {
                    $cantidadCalculo = ($productoEntity->getModelo()?->getProveedor()?->isAcumulaTotal() && $productoEntity->getModelo()?->isAcumulaTotal())
                        ? $this->getCantidadProductosFabricante($productoEntity->getModelo()?->getFabricante()?->getId())
                        : $this->getCantidadProductosIguales($productoEntity->getModelo()?->getId());

                    $precio = $productoEntity->getPrecioTotal($productoPresupuesto->getCantidad(), $cantidadCalculo, $user, $productoPresupuesto->getCantidad()) / $productoPresupuesto->getCantidad();
                }
            }
        }

        $precioPersonalizaciones = $this->getPrecioPersonalizacion($productoID, $itemIndex, $user);

        return $this->roundUp($precio + $precioPersonalizaciones, 3);
    }

    public function getPrecioProductoSinGrabar(?int $productoID, int $itemIndex, ?User $user): float
    {
        if ($productoID === null) return 0.0;

        $precio = 0.0;
        $item = $this->items[$itemIndex] ?? null;
        if (!$item) return 0.0;

        foreach ($item->getProductos() as $productoPresupuesto) {
            if ($productoPresupuesto->getId() === $productoID && $productoPresupuesto->getCantidad() > 0) {
                $productoEntity = $productoPresupuesto->getProducto();
                if ($productoEntity) {
                    $cantidadCalculo = ($productoEntity->getModelo()?->getProveedor()?->isAcumulaTotal() && $productoEntity->getModelo()?->isAcumulaTotal())
                        ? $this->getCantidadProductosFabricante($productoEntity->getModelo()?->getFabricante()?->getId())
                        : $this->getCantidadProductosIguales($productoEntity->getModelo()?->getId());

                    $precio = $productoEntity->getPrecio($productoPresupuesto->getCantidad(), $cantidadCalculo, $user);
                }
            }
        }

        return $this->roundUp($precio, 3);
    }

    public function getPrecioPersonalizacion(?int $productoId, int $itemIndex, ?User $user): float
    {
        $precioPersonalizaciones = 0.0;
        $item = $this->items[$itemIndex] ?? null;
        if (!$item) return 0.0;

        foreach ($item->getTrabajos() as $trabajoPresupuesto) {
            $personalizacion = $trabajoPresupuesto->getTrabajo();
            if ($personalizacion) {
                $blancas = $this->getCantidadProductosPersonalizacionBlancas($trabajoPresupuesto->getIdentificadorTrabajo());
                $color = $this->getCantidadProductosPersonalizacionColor($trabajoPresupuesto->getIdentificadorTrabajo());
                $precioPersonalizaciones += $personalizacion->getPrecio($blancas, $color, $trabajoPresupuesto->getCantidad());
            }
        }
        return $precioPersonalizaciones;
    }

    public function getIvaAAplicar(?User $user, float $ivaGeneral): float
    {
        $gastosEnvio = $this->getGastosEnvio($user);
        return round(($this->getSubTotal($user) + $gastosEnvio) * $ivaGeneral, 2);
    }

    public function getGastosEnvio(?User $user, ZonaEnvio $zonaEnvio = null): float
    {
        if ($this->tipoEnvio === 3) return 0.0; // Recoger en tienda

        $subTotal = $this->getSubTotal($user);

        if ($zonaEnvio === null) {
            if ($subTotal >= 300) return 0.0;
            $pGastos = ($subTotal > 200) ? $this->precioGastosReducidos : $this->precioGastos;
        } else {
            if ($zonaEnvio->getEnvioGratis() > 0 && $subTotal >= $zonaEnvio->getEnvioGratis()) {
                return 0.0;
            }
            return (float) $zonaEnvio->getPrecio((int)ceil(round($this->getBultos(), 6)));
        }

        return $pGastos * ceil(round($this->getBultos(), 6));
    }

    public function getBultos(): float
    {
        $totalCajas = 0.0;
        foreach ($this->items as $presupuesto) {
            foreach ($presupuesto->getProductos() as $producto) {
                $boxSize = $producto->getProducto()?->getModelo()?->getBox();
                if ($boxSize > 0) {
                    $totalCajas += $producto->getCantidad() / $boxSize;
                }
            }
        }
        return $totalCajas;
    }

    public function getCantidadProductosTotales(): int
    {
        return array_sum(array_map(fn(Presupuesto $item) => $item->getCantidadProductos(), $this->items));
    }

    public function getCantidadProductosFabricante(?int $idFabricante): int
    {
        if ($idFabricante === null) return 0;
        $cantidad = 0;
        foreach ($this->items as $item) {
            $cantidad += $item->getCantidadFabricante($idFabricante);
        }
        return $cantidad;
    }

    public function getCantidadProductosIguales(?int $idModelo): int
    {
        if ($idModelo === null) return 0;
        $cantidad = 0;
        foreach ($this->items as $item) {
            foreach ($item->getProductos() as $producto) {
                if ($producto->getProducto()?->getModelo()?->getId() === $idModelo) {
                    $cantidad += $producto->getCantidad();
                }
            }
        }
        return $cantidad;
    }

    public function getCantidadProductosPersonalizacionBlancas(string $codigoPersonalizacion): int
    {
        $cantidad = 0;
        foreach ($this->items as $item) {
            foreach ($item->getTrabajos() as $trabajo) {
                if ($trabajo->getIdentificadorTrabajo() === $codigoPersonalizacion) {
                    foreach ($item->getProductos() as $producto) {
                        $colorNombre = strtoupper((string)$producto->getColor());
                        if (str_contains($colorNombre, 'LANCO') || str_contains($colorNombre, 'HITE')) {
                            $cantidad += $producto->getCantidad();
                        }
                    }
                }
            }
        }
        return $cantidad;
    }

    public function getCantidadProductosPersonalizacionColor(string $codigoPersonalizacion): int
    {
        $cantidad = 0;
        foreach ($this->items as $item) {
            foreach ($item->getTrabajos() as $trabajo) {
                if ($trabajo->getIdentificadorTrabajo() === $codigoPersonalizacion) {
                    foreach ($item->getProductos() as $producto) {
                        $colorNombre = strtoupper((string)$producto->getColor());
                        if (!str_contains($colorNombre, 'LANCO') && !str_contains($colorNombre, 'HITE')) {
                            $cantidad += $producto->getCantidad();
                        }
                    }
                }
            }
        }
        return $cantidad;
    }

    private function roundUp(float|string $value, int $precision): float
    {
        $pow = 10 ** $precision;
        return (ceil($pow * (float)$value) + ceil($pow * (float)$value - ceil($pow * (float)$value))) / $pow;
    }

    // --- Getters y Setters Estándar ---

    public function getItems(): array { return $this->items; }
    public function getDireccionEnvio(): ?string { return $this->direccionEnvio; }
    public function setDireccionEnvio(?string $direccionEnvio): self { $this->direccionEnvio = $direccionEnvio; return $this; }
    public function getCodigoPostal(): ?string { return $this->codigoPostal; }
    public function setCodigoPostal(?string $codigoPostal): self { $this->codigoPostal = $codigoPostal; return $this; }
    public function getPoblacion(): ?string { return $this->poblacion; }
    public function setPoblacion(?string $poblacion): self { $this->poblacion = $poblacion; return $this; }
    public function getProvincia(): ?string { return $this->provincia; }
    public function setProvincia(?string $provincia): self { $this->provincia = $provincia; return $this; }
    public function getPrecioGastos(): float { return $this->precioGastos; }
    public function setPrecioGastos(float $precioGastos): self { $this->precioGastos = $precioGastos; return $this; }
    public function getPrecioGastosReducidos(): float { return $this->precioGastosReducidos; }
    public function setPrecioGastosReducidos(float $precioGastosReducidos): self { $this->precioGastosReducidos = $precioGastosReducidos; return $this; }
    public function getTipoEnvio(): int { return $this->tipoEnvio; }
    public function setTipoEnvio(int $tipoEnvio): self { $this->tipoEnvio = $tipoEnvio; return $this; }
    public function isServicioExpres(): bool { return $this->servicioExpres; }
    public function setServicioExpres(bool $servicioExpres): self { $this->servicioExpres = $servicioExpres; return $this; }
    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $observaciones): self { $this->observaciones = $observaciones; return $this; }
    public function getIdPedido(): int { return $this->idPedido; }
    public function setIdPedido(int $idPedido): self { $this->idPedido = $idPedido; return $this; }
    public function getDescuento(): float { return $this->descuento; }
    public function setDescuento(float $descuento): self { $this->descuento = $descuento; return $this; }
    public function getRecogerTienda(): bool { return $this->tipoEnvio === 3; }
}