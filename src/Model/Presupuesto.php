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

    // --- Serialización y Gestión de Items (sin cambios) ---
    public function __serialize(): array { return ['productos' => $this->productos, 'trabajos' => $this->trabajos]; }
    public function __unserialize(array $data): void { $this->productos = $data['productos'] ?? []; $this->trabajos = $data['trabajos'] ?? []; }
    public function getProductos(): array { return $this->productos; }
//    public function addProducto(PresupuestoProducto $producto): void { $this->productos[] = $producto; }
    public function getTrabajos(): array { return $this->trabajos; }
    public function addTrabajo(PresupuestoTrabajo $trabajo): void { $this->trabajos[] = $trabajo; }
    public function getCantidadProductos(): int { return array_reduce($this->productos, fn($carry, $p) => $carry + $p->getCantidad(), 0); }

    // ===================================================================
    // INICIO DE LA CORRECCIÓN EN LA LÓGICA DE CÁLCULO
    // ===================================================================

    /**
     * Calcula el subtotal de los PRODUCTOS de este presupuesto.
     */
    public function getSubtotalProductos(?User $user): float
    {
        $totalProductos = 0.0;
        foreach ($this->productos as $productoPresupuesto) {
            $producto = $productoPresupuesto->getProducto();
            if ($producto) {
//                $cantidadParaCalculo = ($producto->getModelo()?->getProveedor()?->isAcumulaTotal() && $producto->getModelo()?->isAcumulaTotal())
//                    ? $this->getCantidadFabricante($producto->getModelo()?->getFabricante()?->getId())
//                    : $this->getCantidadProductos(); // Usamos la cantidad total de ESTE presupuesto

                $cantidadParaCalculo = $this->getCantidadProductos();

                $precioUnitario = $producto->getPrecio($productoPresupuesto->getCantidad(), $cantidadParaCalculo, $user);
                $totalProductos += $precioUnitario * $productoPresupuesto->getCantidad();
            }
        }
        return $totalProductos;
    }

    public function getSubtotalTrabajos(?User $user): float
    {
        $cantidadProductos = $this->getCantidadProductos();
        if ($cantidadProductos === 0 || empty($this->trabajos)) {
            return 0.0;
        }

        $totalTrabajo = 0.0;
        $totalBlancas = $this->getCantidadProductosPorColor('BLANCO');
        $totalColor = $this->getCantidadProductosPorColor('COLOR');

        foreach ($this->trabajos as $trabajo) {
            $personalizacion = $trabajo->getTrabajo();
            if ($personalizacion) {
                $precioUnitarioTrabajo = $personalizacion->getPrecio($totalBlancas, $totalColor, $trabajo->getCantidad(), $user);
                $totalTrabajo += $precioUnitarioTrabajo * $cantidadProductos;
            }
        }
        return $totalTrabajo;
    }

    /**
     * Calcula el PRECIO TOTAL de este item. Ya no necesita el Carrito.
     */
    public function getPrecioTotal(?User $user): float
    {
        $totalProductos = $this->getSubtotalProductos($user);

        $totalTrabajos = $this->getSubtotalTrabajos($user);
        return round($totalProductos + $totalTrabajos, 2);
    }

    /**
     * Devuelve el precio unitario final (producto + personalizaciones).
     */
    public function getPrecioUnidad(?User $user): float
    {
        $cantidad = $this->getCantidadProductos();
        return ($cantidad > 0) ? round($this->getPrecioTotal($user) / $cantidad, 2) : 0.0;
    }

    // --- Métodos de Gestión de Trabajos ---


    public function eliminaTrabajo(PresupuestoTrabajo $itemParaEliminar): self
    {
        $this->trabajos = array_filter($this->trabajos, function (PresupuestoTrabajo $trabajo) use ($itemParaEliminar) {
            return $trabajo->getIdentificadorTrabajo() !== $itemParaEliminar->getIdentificadorTrabajo();
        });
        return $this;
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

                $cantidadASumar = $modelo->isObligadaVentaEnPack() ? $modelo->getPack() : 1;
                $cantidadRequerida = $productoPresupuesto->getCantidad() + $cantidadASumar;

                $stockSuficiente = !$proveedor->isControlDeStock() || $proveedor->isPermiteVentaSinStock() || ($producto->getStock() >= $cantidadRequerida);

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

//    public function getPrecioTotal(?User $user): float
//    {
//        $cantidadProductos = $this->getCantidadProductos();
//        if ($cantidadProductos === 0) {
//            return 0.0;
//        }
//
//        $total = 0.0;
//        foreach ($this->productos as $producto) {
//            if ($producto->getProducto()) {
//                $total += $producto->getProducto()->getPrecio($producto->getCantidad(), $cantidadProductos, $user) * $producto->getCantidad();
//            }
//        }
//
//        return round($total + $this->getTotalTrabajo(), 2);
//    }

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

//    public function getPrecioUnidad(?User $user): float
//    {
//        $cantidad = $this->getCantidadProductos();
//        if ($cantidad === 0) {
//            return 0.0;
//        }
//        return round($this->getPrecioTotal($user) / $cantidad, 2);
//    }

    // --- Métodos de Ayuda ---

    public function getTrabajosString(): string
    {
        if (empty($this->trabajos)) return '';
        return implode(',', array_map(fn(PresupuestoTrabajo $t) => $t->getIdentificadorTrabajo(), $this->trabajos));
    }


    /**
     * CORREGIDO: Se añade el método que faltaba.
     * Cuenta la cantidad de productos de un tipo de color (blanco o color) dentro de este presupuesto.
     */
    public function getCantidadProductosPorColor(string $tipoColor): int
    {
        $cantidad = 0;
        $tipoColor = strtoupper($tipoColor);

        foreach ($this->productos as $productoPresupuesto) {
            // --- INICIO DE LA CORRECCIÓN ---
            // 1. Obtenemos el nombre del color de forma segura
            $colorNombre = $productoPresupuesto->getProducto()?->getColor()?->getNombre();

            // 2. Si no hay nombre de color, saltamos a la siguiente iteración
            if (!$colorNombre) {
                continue;
            }

            // 3. Ahora que sabemos que $colorNombre es un string, lo convertimos a mayúsculas
            $colorNombre = strtoupper($colorNombre);
            // --- FIN DE LA CORRECCIÓN ---

            if ($tipoColor === 'BLANCO') {
                if (str_contains($colorNombre, 'BLANCO') || str_contains($colorNombre, 'WHITE')) {
                    $cantidad += $productoPresupuesto->getCantidad();
                }
            } else { // Asumimos que el otro tipo es 'COLOR'
                if (!str_contains($colorNombre, 'BLANCO') && !str_contains($colorNombre, 'WHITE')) {
                    $cantidad += $productoPresupuesto->getCantidad();
                }
            }
        }
        return $cantidad;
    }

    // ... (El resto de tus métodos, como getPrecioTotal, etc., se quedan como están)

    // --- INICIO DE LA CORRECCIÓN ---
    /**
     * Fusiona los productos de otro presupuesto en este.
     * Esencial para que el carrito agrupe productos idénticos.
     */
    public function addProductos(array $nuevosProductos, ?User $user): self
    {
        foreach ($nuevosProductos as $nuevoProducto) {
            $encontrado = false;
            foreach ($this->productos as $productoExistente) {
                // Si encontramos un producto con la misma referencia, sumamos la cantidad
                if ($productoExistente->getProducto()?->getReferencia() === $nuevoProducto->getProducto()?->getReferencia()) {
                    $productoExistente->addCantidad($nuevoProducto->getCantidad());
                    $encontrado = true;
                    break;
                }
            }
            if (!$encontrado) {
                // Si es un producto completamente nuevo para este presupuesto, lo añadimos
                $this->productos[] = $nuevoProducto;
            }
        }
        return $this;
    }
    // --- FIN DE LA CORRECCIÓN ---



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
    public function getCantidadProductosFabricante(?int $idFabricante): int
    {
        if ($idFabricante === null) return 0;
        $cantidad = 0;
        foreach ($this->items as $item) {
            $cantidad += $item->getCantidadFabricante($idFabricante);
        }
        return $cantidad;
    }

    public function getSubtotal(?User $user): float
    {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            // Llama al método getPrecioTotal de la clase Presupuesto, que ahora es autosuficiente
            $subtotal += $item->getPrecioTotal($user);
        }
        return $subtotal;
    }

    public function eliminaItem(Presupuesto $itemParaEliminar): void
    {
        $this->items = array_filter($this->items, fn(Presupuesto $item) => $item !== $itemParaEliminar);
    }

    /**
     * CORREGIDO: Añade un item (Presupuesto) al carrito.
     * Si ya existe un item con las mismas personalizaciones, fusiona los productos.
     */
    public function addItem(Presupuesto $newItem, ?User $user = null): void
    {
        foreach ($this->items as $existingItem) {
            // Buscamos un Presupuesto existente con las mismas personalizaciones
            if ($existingItem->getTrabajosString() === $newItem->getTrabajosString()) {
                // Si lo encontramos, fusionamos los productos del nuevo presupuesto en el existente
                $existingItem->addProductos($newItem->getProductos(), $user);
                // Como hemos fusionado, no necesitamos hacer nada más.
                return;
            }
        }

        // Si no se encontró ningún item con las mismas personalizaciones, añadimos el nuevo
        $this->items[] = $newItem;
    }


    public function getPrecioProducto($productoID)
    {
        $precio = 0;
        foreach ($this->productos as $productoPresupuesto) {
            if ($productoPresupuesto->getId() == $productoID) {
                $precio = $productoPresupuesto->getPrecioProducto();
            }
        }
        $precioTrabajo = $this->getTotalTrabajo() / $this->getCantidadProductos();
        return $precio + $precioTrabajo;
    }

    /**
     * Elimina un producto de este presupuesto por su índice.
     */
    public function eliminaProductoPorIndice(int $indice): void
    {
        // Comprobamos si el producto en esa posición existe
        if (isset($this->productos[$indice])) {
            // Si existe, lo eliminamos
            unset($this->productos[$indice]);
            // Re-indexamos el array para que no queden huecos en las claves
            $this->productos = array_values($this->productos);
        }
    }

    // --- MÉTODOS AÑADIDOS ---

    public function increaseProductQuantity(int $productIndex): void
    {
        if (isset($this->productos[$productIndex])) {
            $productoPresupuesto = $this->productos[$productIndex];
            $modelo = $productoPresupuesto->getProducto()?->getModelo();
            $cantidadASumar = $modelo?->isObligadaVentaEnPack() ? $modelo->getPack() : 1;

            // Aquí podrías añadir una comprobación de stock si fuera necesario

            $productoPresupuesto->addCantidad($cantidadASumar);
        }
    }

    public function decreaseProductQuantity(int $productIndex): void
    {
        if (isset($this->productos[$productIndex])) {
            $productoPresupuesto = $this->productos[$productIndex];
            $modelo = $productoPresupuesto->getProducto()?->getModelo();
            $cantidadARestar = $modelo?->isObligadaVentaEnPack() ? $modelo->getPack() : 1;

            $nuevaCantidad = $productoPresupuesto->getCantidad() - $cantidadARestar;

            if ($nuevaCantidad <= 0) {
                // Si la cantidad llega a 0 o menos, eliminamos el producto de este presupuesto
                $this->eliminaProductoPorIndice($productIndex);
            } else {
                $productoPresupuesto->setCantidad($nuevaCantidad);
            }
        }
    }

    // --- MÉTODO AÑADIDO ---
    /**
     * Actualiza la cantidad de un producto a un valor específico, respetando las reglas de pack.
     */
    public function updateProductQuantity(int $productIndex, int $quantity): void
    {
        if (isset($this->productos[$productIndex])) {
            if ($quantity <= 0) {
                // Si la cantidad es 0 o menos, eliminamos el producto.
                $this->eliminaProductoPorIndice($productIndex);
                return;
            }

            $productoPresupuesto = $this->productos[$productIndex];
            $modelo = $productoPresupuesto->getProducto()?->getModelo();

            // Si hay que vender en packs, ajustamos la cantidad al múltiplo más cercano.
            if ($modelo && $modelo->isObligadaVentaEnPack() && $modelo->getPack() > 1) {
                $pack = $modelo->getPack();
                // Redondeamos hacia arriba al siguiente múltiplo del pack.
                $quantity = (int)(ceil($quantity / $pack) * $pack);
            }

            $productoPresupuesto->setCantidad($quantity);
        }
    }
}