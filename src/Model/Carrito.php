<?php
// src/Model/Carrito.php

namespace App\Model;

use App\Entity\Producto;
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

    // --- Serialización (sin cambios) ---
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

    /**
     * Elimina un item (Presupuesto) del carrito.
     */
    public function eliminaItem(Presupuesto $itemParaEliminar): void
    {
        $this->items = array_filter($this->items, function (Presupuesto $item) use ($itemParaEliminar) {
            // Comparamos los objetos directamente. Si son el mismo, se filtra.
            return $item !== $itemParaEliminar;
        });

        // Re-indexamos el array para evitar huecos en las claves
        $this->items = array_values($this->items);
    }

    // ===================================================================
    // LÓGICA DE CÁLCULO SIMPLIFICADA
    // ===================================================================

    /**
     * CORREGIDO: El subtotal del carrito ahora es la suma de los totales de cada item.
     * Ya no contiene la lógica compleja de cálculo.
     */
    public function getSubtotal(?User $user): float
    {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            // Llama al nuevo método getPrecioTotal de la clase Presupuesto
            $subtotal += $item->getPrecioTotal($user, $this);
        }
        return $subtotal;
    }

    // --- MÉTODOS DE AYUDA (necesarios para que Presupuesto funcione) ---

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

    public function getCantidadProductosTotales()
    {
        $cantidad = 0;
        foreach ($this->items as $carritoItem) {
            $cantidad += $carritoItem->getCantidadProductos();
        }
        return $cantidad;
    }

    public function getBultos()
    {
        $totalCajas = 0;

        foreach ($this->items as $presupuesto) {
            foreach ($presupuesto->getProductos() as $producto) {
                if ($producto->getProducto() != null) {
                    $totalCajas = $totalCajas + $producto->getCantidad() / $producto->getProducto()->getModelo()->getBox();
                }
            }
        }
        return (ceil(round($totalCajas, 6)));
    }

    public function getGastosEnvio($user, ZonaEnvio $zonaEnvio = null)
    {
        if ($this->tipoEnvio == 3) {
            return 0;
        }
        $totalCajas = 0;

        if ($zonaEnvio==null) {
            //los gastos envios gratuitos si el subtotal supera los 500€
            if ($this->getSubTotal($user) >= 300) {
                return 0;
            }
            if ($this->getSubTotal($user) > 200) {
                $pGastos = $this->precioGastosReducidos;
            } else {
                $pGastos = $this->precioGastos;
            }
        }else{
            if($zonaEnvio->getEnvioGratis() > 0) {
                if ($this->getSubTotal($user) >= $zonaEnvio->getEnvioGratis()) {
                    return 0;
                }
            }
            $pGastos = $zonaEnvio->getPrecio(ceil(round($this->getBultos(), 6)));
            return $pGastos;
        }

        $totalCajas = $this->getBultos();
        return ($pGastos * ceil(round($totalCajas, 6)));
    }

    /**
     * Elimina una línea de producto específica de un Presupuesto.
     * Si el Presupuesto se queda sin productos, lo elimina del carrito.
     */
    public function eliminaProductoPorIndice(int $itemIndex, int $productIndex): void
    {
        // Comprobamos si el item (Presupuesto) en esa posición existe
        if (isset($this->items[$itemIndex])) {
            $presupuesto = $this->items[$itemIndex];

            // Le pedimos al Presupuesto que elimine el producto por su índice interno
            $presupuesto->eliminaProductoPorIndice($productIndex);

            // Si el Presupuesto se ha quedado sin productos después de la eliminación,
            // lo eliminamos del carrito.
            if (count($presupuesto->getProductos()) === 0) {
                unset($this->items[$itemIndex]);
                // Re-indexamos el array principal para que no queden huecos en las claves
                $this->items = array_values($this->items);
            }
        }
    }

    // --- MÉTODOS AÑADIDOS ---

    public function increaseProductQuantity(int $itemIndex, int $productIndex): void
    {
        if (isset($this->items[$itemIndex])) {
            $presupuesto = $this->items[$itemIndex];
            $presupuesto->increaseProductQuantity($productIndex);
        }
    }

    public function decreaseProductQuantity(int $itemIndex, int $productIndex): void
    {
        if (isset($this->items[$itemIndex])) {
            $presupuesto = $this->items[$itemIndex];
            $presupuesto->decreaseProductQuantity($productIndex);

            // Si el presupuesto se queda vacío después de disminuir la cantidad, lo eliminamos del carrito
            if (count($presupuesto->getProductos()) === 0) {
                unset($this->items[$itemIndex]);
                $this->items = array_values($this->items);
            }
        }
    }

}