<?php
// src/Service/ShippingCalculatorService.php

namespace App\Service;

use App\Entity\Pedido;
use App\Entity\ZonaEnvio;
use App\Model\Carrito;
use App\Repository\ZonaEnvioPrecioCantidadRepository;

class ShippingCalculatorService
{
    public function __construct(private ZonaEnvioPrecioCantidadRepository $zonaEnvioPrecioRepository)
    {
    }

    /**
     * Calcula el coste de envío para un carrito y una zona de envío.
     *
     * @param Carrito        $carrito      El carrito de la compra.
     * @param ZonaEnvio|null $zonaEnvio    La zona de envío seleccionada.
     * @param float          $subtotalNeto El subtotal del pedido sin IVA, para comprobar el envío gratuito.
     *
     * @return float El coste del envío calculado.
     */
    public function calculateShippingCost(Carrito $carrito, ?ZonaEnvio $zonaEnvio, float $subtotalNeto): float
    {
        // Si no hay zona de envío o es para recoger en tienda, el coste es 0.
        if (!$zonaEnvio || $carrito->getRecogerTienda()) {
            return 0.0;
        }

        // 1. Comprobar si se aplica el envío gratuito por superar el importe.
        $umbralEnvioGratis = (float)$zonaEnvio->getEnvioGratis();
        if ($umbralEnvioGratis > 0 && $subtotalNeto >= $umbralEnvioGratis) {
            return 0.0;
        }

        // 2. Calcular el número total de bultos en el carrito.
        $numeroBultos = $this->calculateTotalBultos($carrito);

        if ($numeroBultos === 0) {
            return 0.0;
        }

        // 3. Buscar el precio por bulto en la base de datos.
        $costeEnvio = $this->zonaEnvioPrecioRepository->findPriceByBultos($zonaEnvio, $numeroBultos);

        return $costeEnvio;
    }

    /**
     * Calcula el número estimado de bultos (cajas) que ocupará el pedido.
     */
    private function calculateTotalBultos(Carrito $carrito): int
    {
        $totalBultosFraccional = 0.0;

        foreach ($carrito->getItems() as $presupuesto) {
            foreach ($presupuesto->getProductos() as $itemProducto) {
                $producto = $itemProducto->getProducto();
                $cantidadPorCaja = $producto->getModelo()->getBox(); // Obtenemos la cantidad por caja del producto

                if ($cantidadPorCaja > 0) {
                    // Calculamos la fracción de caja que ocupa esta línea de producto
                    $totalBultosFraccional += $itemProducto->getCantidad() / $cantidadPorCaja;
                }
            }
        }

        // Si hay productos, devolvemos siempre como mínimo 1 bulto.
        return $totalBultosFraccional > 0 ? (int)ceil($totalBultosFraccional) : 0;
    }

    /**
     * ¡NUEVO MÉTODO!
     * Calcula el coste de envío para una entidad Pedido ya existente.
     * Ideal para usar en el backend (Admin, EventSubscribers, etc.).
     */
    public function calculateForPedido(Pedido $pedido): float
    {
        $direccionEnvio = $pedido->getDireccion();

        // Si no hay dirección de envío o código postal, el coste es 0.
        if (!$direccionEnvio) {
            return 0.0;
        }

        // Buscamos la zona de envío correspondiente
        $zonaEnvio = $direccionEnvio->getProvinciaBD()->getZonasEnvio()[0];

        // Si no se encuentra una zona, el coste es 55.
        if (!$zonaEnvio) {
            return 55;
        }

        // Comprobamos si se aplica el envío gratuito por superar el importe.
        $umbralEnvioGratis = (float)$zonaEnvio->getEnvioGratis();
        if ($umbralEnvioGratis > 0 && $pedido->getSubTotal() >= $umbralEnvioGratis) {
            return 0.0;
        }

        // Calculamos el número de bultos a partir de las líneas del pedido.
        $numeroBultos = $this->calculateTotalBultosForPedido($pedido);

        if ($numeroBultos === 0) {
            return 0.0;
        }

        // Buscamos el precio por bulto en la base de datos.
        return $this->zonaEnvioPrecioRepository->findPriceByBultos($zonaEnvio, $numeroBultos);
    }

    /**
     * Calcula el número de bultos para una entidad Pedido.
     */
    private function calculateTotalBultosForPedido(Pedido $pedido): int
    {
        $totalBultosFraccional = 0.0;

        foreach ($pedido->getLineas() as $linea) {
            $producto = $linea->getProducto();
            $cantidadPorCaja = $producto?->getModelo()?->getBox();

            if ($cantidadPorCaja > 0) {
                $totalBultosFraccional += $linea->getCantidad() / $cantidadPorCaja;
            }
        }

        return $totalBultosFraccional > 0 ? (int)ceil($totalBultosFraccional) : 0;
    }
}