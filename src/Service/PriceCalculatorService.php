<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\Personalizacion;
use App\Entity\PersonalizacionPrecioCantidad;
use App\Entity\Producto;
use App\Model\Carrito;
use App\Model\Presupuesto;
use App\Model\PresupuestoTrabajo;
use App\Repository\PersonalizacionPrecioCantidadRepository;
use App\Repository\TarifaPreciosRepository;
use Doctrine\ORM\EntityManagerInterface;

class PriceCalculatorService
{
    private ?float $ivaPorcentaje = null;

    public function __construct(
        private TarifaPreciosRepository $tarifaPreciosRepository,
        private PersonalizacionPrecioCantidadRepository $personalizacionPrecioRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function calculateFullPresupuesto(Carrito $carrito): array
    {
        // --- FASE 1: AGREGAR ---
        // Recopilamos la información global de todas las personalizaciones del carrito.
        $trabajosInfo = [];
        foreach ($carrito->getItems() as $presupuesto) {
            foreach ($presupuesto->getTrabajos() as $trabajo) {
                $id = $trabajo->getIdentificadorTrabajo();
                if (!isset($trabajosInfo[$id])) {
                    $trabajosInfo[$id] = [
                        'trabajo' => $trabajo,
                        'total_unidades' => 0,
                        'productos_afectados' => [],
                    ];
                }
                $trabajosInfo[$id]['total_unidades'] += $presupuesto->getTotalProductos();
                // Añadimos los productos de este grupo a la lista de productos afectados por este trabajo
                foreach($presupuesto->getProductos() as $productoItem){
                    $trabajosInfo[$id]['productos_afectados'][] = $productoItem;
                }
            }
        }

        // --- FASE 2: CALCULAR COSTE GLOBAL Y POR UNIDAD ---
        $costesPorUnidadTrabajo = [];
        foreach ($trabajosInfo as $id => $info) {
            /** @var PresupuestoTrabajo $trabajo */
            $trabajo = $info['trabajo'];
            $totalUnidades = $info['total_unidades'];
            $productosAfectados = $info['productos_afectados'];

            // Calculamos el coste total de este trabajo basándonos en su cantidad global
            $costeTotalTrabajo = $this->calculateSingleTrabajoGlobalCost($trabajo, $totalUnidades, $productosAfectados);

            // Calculamos el coste por unidad para distribuirlo después
            if ($totalUnidades > 0) {
                $costesPorUnidadTrabajo[$id] = $costeTotalTrabajo / $totalUnidades;
            }
        }

        // --- FASE 3: DISTRIBUIR Y CONSTRUIR RESULTADO (¡CON LA MEJORA!) ---
        $desgloseGrupos = [];
        $subtotalGeneralSinIva = 0;

        foreach ($carrito->getItems() as $presupuesto) {
            $desgloseProductosGrupo = [];
            $subtotalGrupo = 0;

            foreach ($presupuesto->getProductos() as $itemProducto) {
                // 3a. Precio base del producto (coste + margen)
                $precioBaseProducto = $this->calculateSingleProductSellingPrice($itemProducto->getProducto(), $itemProducto->getCantidad(), $carrito);

                // 3b. Sumamos el coste por unidad de cada personalización que afecta a este grupo
                $costeTotalPersonalizacionUnidad = 0;
                foreach ($presupuesto->getTrabajos() as $trabajo) {
                    $id = $trabajo->getIdentificadorTrabajo();
                    $costeTotalPersonalizacionUnidad += $costesPorUnidadTrabajo[$id] ?? 0;
                }

                $precioUnitarioFinal = $precioBaseProducto + $costeTotalPersonalizacionUnidad;
                $totalLinea = $precioUnitarioFinal * $itemProducto->getCantidad();
                $subtotalGrupo += $totalLinea;

                $desgloseProductosGrupo[] = [
                    'producto' => $itemProducto->getProducto(),
                    'unidades' => $itemProducto->getCantidad(),
                    'precio_unitario_final_sin_iva' => round($precioUnitarioFinal, 2),
                    'total_linea_sin_iva' => round($totalLinea, 2),
                    // ¡¡LA MEJORA CLAVE!! Desglosamos los costes para la plantilla
                    'precio_base_producto_sin_iva' => round($precioBaseProducto, 4),
                    'coste_personalizacion_por_unidad' => round($costeTotalPersonalizacionUnidad, 4),
                ];
            }

            $desgloseGrupos[] = [
                'trabajos_del_grupo' => $presupuesto->getTrabajos(),
                'desglose_productos' => $desgloseProductosGrupo,
                'subtotal_grupo_sin_iva' => round($subtotalGrupo, 2)
            ];
            $subtotalGeneralSinIva += $subtotalGrupo;
        }

        // ... El resto de la función (cálculo de IVA y totales) se mantiene igual ...
        $totalIva = $subtotalGeneralSinIva * ($this->getIva() / 100);
        $granTotal = $subtotalGeneralSinIva + $totalIva;

        return [
            'desglose_grupos' => $desgloseGrupos,
            'subtotal_sin_iva' => round($subtotalGeneralSinIva, 2),
            'total_iva' => round($totalIva, 2),
            'total_con_iva' => round($granTotal, 2),
            'iva_aplicado' => $this->getIva(),
        ];
    }

    /**
     * ¡NUEVO! Calcula el coste TOTAL de un trabajo, usando la cantidad global y una lista de productos.
     */
    private function calculateSingleTrabajoGlobalCost(PresupuestoTrabajo $itemTrabajo, int $cantidadGlobal, array $productosAfectados): float
    {
        $personalizacion = $itemTrabajo->getTrabajo();
        $rangoPrecios = $this->personalizacionPrecioRepository->findPriceByQuantity($personalizacion, $cantidadGlobal);

        // --- CÁLCULO DEL COSTE NORMAL ---
        $costeFijoNormal = 0;
//        if ($personalizacion->getNumeroMaximoColores() > 0) {
            if ($rangoPrecios) {
                $numColores = $itemTrabajo->getCantidad();
                $costePantalla = (float)($rangoPrecios->getPantalla() ?? 0.0);
                $costeFijoNormal = $numColores * $costePantalla;
            }
//        }

        $costeVariableTotal = 0;
        foreach ($productosAfectados as $itemProducto) {
            $precioUnitarioVariable = $this->getPrecioUnitarioTrabajoParaProducto($personalizacion, $rangoPrecios, $itemTrabajo, $itemProducto->getProducto());
            $costeVariableTotal += $precioUnitarioVariable * $itemProducto->getCantidad();
        }

        $costeNormalCalculado = $costeFijoNormal + $costeVariableTotal;

        // --- CÁLCULO DEL COSTE MÍNIMO GARANTIZADO ---
        $numColoresParaMinimo = ($personalizacion->getNumeroMaximoColores() > 0) ? $itemTrabajo->getCantidad() : 1;
        $costeMinimoGarantizado = $numColoresParaMinimo * ((float)($personalizacion->getTrabajoMinimoPorColor() ?? 0.0));

        // --- COMPARACIÓN Y APLICACIÓN DE INCREMENTO ---
        // El subtotal es el valor MÁS ALTO entre el cálculo normal y el mínimo garantizado.
        $subtotalAntesDeIncremento = max($costeNormalCalculado, $costeMinimoGarantizado);

        $incremento = (float)($personalizacion->getIncrementoPrecio() ?? 0.0);

        return $subtotalAntesDeIncremento * (1 + ($incremento / 100));
    }

    /**
     * Calcula el precio de venta de un producto (coste + margen de tarifa), sin personalización.
     */
    private function calculateSingleProductSellingPrice(Producto $producto, int $unidades, Carrito $carrito): float
    {
        $precioCoste = $this->getPrecioCosteUnitario($producto, $unidades);

        $proveedor = $producto->getModelo()->getProveedor();
        $modelo = $producto->getModelo();

        $cantidadParaTarifa = $unidades;
        if ($modelo && $modelo->isAcumulaTotal() && $proveedor && method_exists($carrito, 'getUnidadesByProveedor')) {
            $cantidadParaTarifa = $carrito->getUnidadesByProveedor($proveedor);
        }

        $precioVentaUnitario = $precioCoste;
        $tarifa = $producto->getModelo()->getTarifa() ?? $proveedor?->getTarifa();

        if ($tarifa) {
            $rangoTarifa = $this->tarifaPreciosRepository->findPriceByQuantity($tarifa, $cantidadParaTarifa);
            if ($rangoTarifa) {
                $margenAplicado = (float)$rangoTarifa->getPrecio();
                $precioVentaUnitario = $precioCoste * (1 + ($margenAplicado / 100));
            }
        }
        //añadimos el descuento del proveedor
        $precioVentaUnitario -= ($precioVentaUnitario * (float) $proveedor->getDescuentoEspecial() / 100);
        return $precioVentaUnitario;
    }

    private function getPrecioUnitarioTrabajoParaProducto(?Personalizacion $personalizacion, ?PersonalizacionPrecioCantidad $rangoPrecios, PresupuestoTrabajo $itemTrabajo, Producto $producto): float
    {
        if (!$personalizacion || !$rangoPrecios) { return 0.0; }

        if ($personalizacion->getNumeroMaximoColores() <= 0) {
            return (float)($rangoPrecios->getPrecio() ?? 0.0);
        }

        $numColores = $itemTrabajo->getCantidad();//getNumeroColores();
        if ($numColores <= 0) { return 0.0; }

        $colorProducto = $producto->getColor();
        $esPrendaBlanca = ($colorProducto && method_exists($colorProducto, 'isBlanco')) ? $colorProducto->isBlanco() : false;

        $precioPrimerColor = $esPrendaBlanca ? (float)($rangoPrecios->getPrecio() ?? 0.0) : (float)($rangoPrecios->getPrecioColor() ?? 0.0);
        $precioSiguientesColores = $esPrendaBlanca ? (float)($rangoPrecios->getPrecio2() ?? 0.0) : (float)($rangoPrecios->getPrecioColor2() ?? 0.0);

        $precioUnitarioTotal = $precioPrimerColor;
        if ($numColores > 1) {
            $precioUnitarioTotal += ($precioSiguientesColores * ($numColores - 1));
        }

        return $precioUnitarioTotal;
    }

    private function getPrecioCosteUnitario(Producto $producto, int $unidades): float
    {
        if ($producto->getModelo()->getBox() > 0 && $unidades >= $producto->getModelo()->getBox()) {
            return (float)($producto->getPrecioCaja() ?? $producto->getPrecioUnidad() ?? 0.0);
        }
        if ($producto->getmodelo()->getPack() > 0 && $unidades >= $producto->getModelo()->getPack()) {
            return (float)($producto->getPrecioPack() ?? $producto->getPrecioUnidad() ?? 0.0);
        }
        return (float)($producto->getPrecioUnidad() ?? 0.0);
    }

    private function getIva(): float
    {
        if ($this->ivaPorcentaje === null) {
            $empresa = $this->entityManager->getRepository(Empresa::class)->find(1);
            $this->ivaPorcentaje = $empresa ? (float)$empresa->getIvaGeneral() : 21.0;
        }
        return $this->ivaPorcentaje;
    }

    private function addIva(float $precioSinIva): float
    {
        return round($precioSinIva * (1 + ($this->getIva() / 100)), 2);
    }
}