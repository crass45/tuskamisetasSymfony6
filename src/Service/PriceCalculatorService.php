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
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Sonata\User;


class PriceCalculatorService
{
    private ?float $ivaPorcentaje = null;

    public function __construct(
        private TarifaPreciosRepository $tarifaPreciosRepository,
        private PersonalizacionPrecioCantidadRepository $personalizacionPrecioRepository,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function calculateFullPresupuesto(Carrito $carrito): array
    {
        // --- FASE 1: AGREGAR ---
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

            $costeTotalTrabajo = $this->calculateSingleTrabajoGlobalCost($trabajo, $totalUnidades, $productosAfectados);

            if ($totalUnidades > 0) {
                $costesPorUnidadTrabajo[$id] = $costeTotalTrabajo / $totalUnidades;
            }
        }

        // --- FASE 3: DISTRIBUIR Y CONSTRUIR RESULTADO ---
        $desgloseGrupos = [];
        $subtotalGeneralSinIva = 0;

        foreach ($carrito->getItems() as $presupuesto) {
            $desgloseProductosGrupo = [];
            $subtotalGrupo = 0;

            // ... dentro de FASE 3 ...
            foreach ($presupuesto->getProductos() as $itemProducto) {
                // 3a. Precio base del producto (coste + margen)
                $precioBaseProducto = $this->calculateSingleProductSellingPrice($itemProducto->getProducto(), $itemProducto->getCantidad(), $carrito);

                // 3b. Sumamos el coste por unidad de cada personalización
                $costeTotalPersonalizacionUnidad = 0;
                foreach ($presupuesto->getTrabajos() as $trabajo) {
                    $id = $trabajo->getIdentificadorTrabajo();
                    $costeTotalPersonalizacionUnidad += $costesPorUnidadTrabajo[$id] ?? 0;
                }

                // Cálculo del precio unitario final (con precisión alta)
                $precioUnitarioFinal = $precioBaseProducto + $costeTotalPersonalizacionUnidad;

                // --- CORRECCIÓN DE REDONDEO ---
                // Calculamos el total de línea matemático
                $totalLineaRaw = $precioUnitarioFinal * $itemProducto->getCantidad();

                // IMPORTANTE: Redondeamos el total de la línea ANTES de sumarlo al subtotal del grupo
                // Esto garantiza que la suma matemática coincida con la suma visual de la factura
                $totalLineaRedondeado = round($totalLineaRaw, 2);

                // Sumamos el valor redondeado al acumulador
                $subtotalGrupo += $totalLineaRedondeado;

                $desgloseProductosGrupo[] = [
                    'producto' => $itemProducto->getProducto(),
                    'unidades' => $itemProducto->getCantidad(),
                    'precio_unitario_final_sin_iva' => $precioUnitarioFinal, // Puedes dejarlo con decimales para info interna
                    'total_linea_sin_iva' => $totalLineaRedondeado, // Usamos el redondeado
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

    // --- MÉTODOS DE APOYO PARA "AUTO-REPARACIÓN" ---

    /**
     * Asegura que el producto tenga las relaciones (Modelo, Tarifa) cargadas.
     * Si viene de la sesión (desconectado), lo recarga de la BBDD.
     */
    private function getFreshProducto(Producto $producto): Producto
    {
        if ($producto->getId()) {
            // Intentamos buscarlo en el EntityManager (caché o BBDD)
            $fresh = $this->entityManager->getRepository(Producto::class)->find($producto->getId());
            if ($fresh) {
                return $fresh;
            }
        }
        return $producto;
    }

    /**
     * Asegura que la personalización tenga los precios cargados.
     */
    private function getFreshPersonalizacion(Personalizacion $personalizacion): Personalizacion
    {
        if ($personalizacion->getCodigo()) {
            $fresh = $this->entityManager->getRepository(Personalizacion::class)->find($personalizacion->getCodigo());
            if ($fresh) {
                // Truco para forzar la carga de la colección de precios si es Lazy
                $fresh->getPrecios()->count();
                return $fresh;
            }
        }
        return $personalizacion;
    }

    // ------------------------------------------------

    private function calculateSingleTrabajoGlobalCost(PresupuestoTrabajo $itemTrabajo, int $cantidadGlobal, array $productosAfectados): float
    {
        $personalizacion = $itemTrabajo->getTrabajo();

        // [AUTO-REPAIR] Si la personalización existe, aseguramos que esté "fresca"
        if ($personalizacion) {
            $personalizacion = $this->getFreshPersonalizacion($personalizacion);
        } else {
            return 0.0;
        }

        $rangoPrecios = $this->personalizacionPrecioRepository->findPriceByQuantity($personalizacion, $cantidadGlobal);

        // --- CÁLCULO DEL COSTE NORMAL ---
        $costeFijoNormal = 0;
        if ($rangoPrecios) {
            $numColores = $itemTrabajo->getCantidad();
            $costePantalla = (float)($rangoPrecios->getPantalla() ?? 0.0);
            $costeFijoNormal = $numColores * $costePantalla;
        }

        $costeVariableTotal = 0;
        foreach ($productosAfectados as $itemProducto) {
            // [AUTO-REPAIR] También refrescamos el producto aquí para leer bien el color
            $productoFresco = $this->getFreshProducto($itemProducto->getProducto());

            $precioUnitarioVariable = $this->getPrecioUnitarioTrabajoParaProducto($personalizacion, $rangoPrecios, $itemTrabajo, $productoFresco);
            $costeVariableTotal += $precioUnitarioVariable * $itemProducto->getCantidad();
        }

        $costeNormalCalculado = $costeFijoNormal + $costeVariableTotal;

        // --- CÁLCULO DEL COSTE MÍNIMO GARANTIZADO ---
        $numColoresParaMinimo = ($personalizacion->getNumeroMaximoColores() > 0) ? $itemTrabajo->getCantidad() : 1;
        $costeMinimoGarantizado = $numColoresParaMinimo * ((float)($personalizacion->getTrabajoMinimoPorColor() ?? 0.0));

        // --- COMPARACIÓN Y APLICACIÓN DE INCREMENTO ---
        $subtotalAntesDeIncremento = max($costeNormalCalculado, $costeMinimoGarantizado);

        $incremento = (float)($personalizacion->getIncrementoPrecio() ?? 0.0);

        return $subtotalAntesDeIncremento * (1 + ($incremento / 100));
    }

    private function calculateSingleProductSellingPrice(Producto $producto, int $unidades, Carrito $carrito): float
    {
        // [AUTO-REPAIR] Forzamos la carga fresca del producto y sus tarifas
        $producto = $this->getFreshProducto($producto);

        $precioCoste = $this->getPrecioCosteUnitario($producto, $unidades);

        $proveedor = $producto->getModelo()->getProveedor();
        $modelo = $producto->getModelo();

        // 1. Determinar la Tarifa Base
        $tarifaBase = $modelo->getTarifa() ?? $proveedor?->getTarifa();

        // Variable para la tarifa final a aplicar
        $tarifaAplicable = $tarifaBase;

        // 2. Lógica de Sustitución de Tarifa por Grupo de Usuario
        $user = $this->security->getUser();

        if ($user instanceof User && $tarifaBase) {
            foreach ($user->getGroups() as $group) {
                foreach ($group->getDescuentos() as $descuento) {
                    if ($descuento->getTarifaAnterior() &&
                        $descuento->getTarifaAnterior()->getId() === $tarifaBase->getId() &&
                        $descuento->getTarifa()) {

                        $tarifaAplicable = $descuento->getTarifa();
                        break 2;
                    }
                }
            }
        }

        // 3. Calcular cantidad acumulada
        $cantidadParaTarifa = $unidades;
        if ($modelo && $modelo->isAcumulaTotal() && $proveedor && method_exists($carrito, 'getUnidadesByProveedor')) {
            $cantidadParaTarifa = $carrito->getUnidadesByProveedor($proveedor);
        }

        // 4. Calcular precio final
        $precioVentaUnitario = $precioCoste;
        if ($tarifaAplicable) {
            $rangoTarifa = $this->tarifaPreciosRepository->findPriceByQuantity($tarifaAplicable, $cantidadParaTarifa);
            if ($rangoTarifa) {
                $margenAplicado = (float)$rangoTarifa->getPrecio();
                $precioVentaUnitario = $precioCoste * (1 + ($margenAplicado / 100));
            }
        }

        // 5. Aplicar descuento especial del proveedor
        if ($proveedor) {
            $precioVentaUnitario -= ($precioVentaUnitario * (float) $proveedor->getDescuentoEspecial() / 100);
        }

        return $precioVentaUnitario;
    }

    private function getPrecioUnitarioTrabajoParaProducto(?Personalizacion $personalizacion, ?PersonalizacionPrecioCantidad $rangoPrecios, PresupuestoTrabajo $itemTrabajo, Producto $producto): float
    {
        if (!$personalizacion || !$rangoPrecios) { return 0.0; }

        if ($personalizacion->getNumeroMaximoColores() <= 0) {
            return (float)($rangoPrecios->getPrecio() ?? 0.0);
        }

        $numColores = $itemTrabajo->getCantidad();
        if ($numColores <= 0) { return 0.0; }

        $colorProducto = $producto->getColor();
        // Nota: Asumimos que $producto ya viene "fresco" porque lo limpiamos antes de llamar a esta función

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
        // Nota: Aquí usamos el $producto fresco, así que getModelo() y sus propiedades funcionan seguro.
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
}