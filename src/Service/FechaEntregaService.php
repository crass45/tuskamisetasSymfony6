<?php
// src/Service/FechaEntregaService.php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\Pedido;
use App\Entity\PedidoLinea;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Servicio para calcular las fechas de entrega estimadas.
 */
class FechaEntregaService
{
    private ?Empresa $empresaConfig;

    public function __construct(private EntityManagerInterface $em)
    {
        // Cargamos la configuración de la empresa una vez en el constructor
        $this->empresaConfig = $this->em->getRepository(Empresa::class)->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Calcula el rango de fechas de entrega (mínima y máxima) para un pedido completo.
     * @param Pedido $pedido El pedido para el que se calcula la fecha.
     * @param \DateTime $fechaInicio La fecha desde la que empezar a contar (ej. hoy).
     * @return array{min: \DateTime, max: \DateTime, express: \DateTime}|null
     */
    public function getFechasEntregaPedido(Pedido $pedido, \DateTime $fechaInicio): ?array
    {
        if (!$this->empresaConfig) {
            return null;
        }

        // 1. Encontrar el proveedor con el mayor tiempo de envío en el pedido
        $maxDiasEnvioProveedor = 0;
        foreach ($pedido->getLineas() as $linea) {
            /** @var PedidoLinea $linea */
            $proveedor = $linea->getProducto()?->getModelo()?->getProveedor();
            if ($proveedor && $proveedor->getDiasEnvio() > $maxDiasEnvioProveedor) {
                $maxDiasEnvioProveedor = $proveedor->getDiasEnvio();
            }
        }

        // 2. Sumar días adicionales del pedido, del proveedor y del envío
        $diasIncrementoZona = 0;
        $direccionEnvio = $pedido->getDireccion();
        if ($direccionEnvio && $direccionEnvio->getProvinciaBD() && $direccionEnvio->getProvinciaBD()->getZonasEnvio()[0]) {
            $diasIncrementoZona = $direccionEnvio->getProvinciaBD()->getZonasEnvio()[0]->getIncrementoTiempoPedido() ?? 0;
        }

        // --- NUEVO: Obtener días extra por complejidad de personalización ---
        $diasExtraPersonalizacion = $this->getMaxDiasProduccionExtra($pedido);

        // Sumamos todo: Proveedor + Config Pedido + Zona + TIEMPO PERSONALIZACIÓN
        $diasExtra = $maxDiasEnvioProveedor
            + ($pedido->getDiasAdicionales() ?? 0)
            + $diasIncrementoZona
            + $diasExtraPersonalizacion;

        // 3. Determinar los días de producción base (mínimo, máximo y exprés)
        $diasMinProduccion = 0;
        $diasMaxProduccion = 0;

        if ($pedido->compruebaTrabajos()) { // Con personalización
            $diasMinProduccion = $this->empresaConfig->getMinimoDiasConImpresion() ?? 0;
            $diasMaxProduccion = $this->empresaConfig->getMaximoDiasConImpresion() ?? 0;
        } else { // Sin personalización
            $diasMinProduccion = $this->empresaConfig->getMinimoDiasSinImprimir() ?? 0;
            $diasMaxProduccion = $this->empresaConfig->getMaximoDiasSinImprimir() ?? 0;
        }

        // El servicio exprés siempre se calcula sobre el mínimo sin impresión + 3 días
        // (Nota: Si quieres que el Express también sufra el retraso de la técnica, suma $diasExtraPersonalizacion aquí también,
        // pero normalmente Express ignora colas de producción estándar).
        $diasExpressProduccion = ($this->empresaConfig->getMinimoDiasSinImprimir() ?? 0) + 3;

        // 4. Calcular los totales de días laborables
        $totalDiasMin = $diasMinProduccion + $diasExtra;
        $totalDiasMax = $diasMaxProduccion + $diasExtra;
        $totalDiasExpress = $diasExpressProduccion + $diasExtra;

        // 5. Calcular las fechas finales
        return [
            'min' => $this->calculateDate($totalDiasMin, clone $fechaInicio),
            'max' => $this->calculateDate($totalDiasMax, clone $fechaInicio),
            'express' => $this->calculateDate($totalDiasExpress, clone $fechaInicio),
        ];
    }

    /**
     * Calcula las fechas de entrega para un modelo específico (Ficha de producto).
     */
    public function getDeliveryDatesForModel(\App\Entity\Modelo $modelo): array
    {
        if (!$this->empresaConfig) {
            return [];
        }

        $sumaDias = $modelo->getProveedor()?->getDiasEnvio() ?? 0;

        $diasSinImprimir1 = $this->empresaConfig->getMinimoDiasSinImprimir() + $sumaDias;
        $diasImpreso1 = $this->empresaConfig->getMinimoDiasConImpresion() + $sumaDias;
        $diasSinImprimir2 = $this->empresaConfig->getMaximoDiasSinImprimir() + $sumaDias;
        $diasImpreso2 = $this->empresaConfig->getMaximoDiasConImpresion() + $sumaDias;
        $diasExpress = $diasSinImprimir1 + 3;

        return [
            'fechaEntregaSinImprimir' => $this->calculateDate($diasSinImprimir1, new \DateTime()),
            'fechaEntregaImpreso' => $this->calculateDate($diasImpreso1, new \DateTime()),
            'fechaEntregaSinImprimir2' => $this->calculateDate($diasSinImprimir2, new \DateTime()),
            'fechaEntregaImpreso2' => $this->calculateDate($diasImpreso2, new \DateTime()),
            'fechaEntregaExpress' => $this->calculateDate($diasExpress, new \DateTime()),
        ];
    }

    /**
     * Añade días laborables a una fecha, considerando fines de semana y festivos.
     */
    public function calculateDate(int $businessDays, \DateTime $startDate): \DateTime
    {
        $date = clone $startDate;
        $daysAdded = 0;

        if ($businessDays <= 0) {
            return $date;
        }

        while ($daysAdded < $businessDays) {
            $date->add(new \DateInterval('P1D'));
            $dayOfWeek = (int) $date->format('N');

            if ($dayOfWeek >= 6 || $this->isHoliday($date) || $this->isVacation($date)) {
                continue;
            }
            $daysAdded++;
        }
        return $date;
    }

    private function isVacation(\DateTime $date): bool
    {
        if (!$this->empresaConfig) return false;

        $fechaInicioVacaciones = $this->empresaConfig->getFechaInicioVacaciones();
        $fechaFinVacaciones = $this->empresaConfig->getFechaFinVacaciones();

        if ($fechaInicioVacaciones && $fechaFinVacaciones) {
            return ($date >= $fechaInicioVacaciones && $date <= $fechaFinVacaciones);
        }
        return false;
    }

    private function isHoliday(\DateTime $date): bool
    {
        // Festivos 2025 (Idealmente mover a BBDD)
        $holidays = [
            '2025-01-01', '2025-01-06', '2025-04-18', '2025-05-01',
            '2025-08-15', '2025-10-12', '2025-11-01', '2025-12-06',
            '2025-12-08', '2025-12-25',
        ];

        return in_array($date->format('Y-m-d'), $holidays, true);
    }

    /**
     * NUEVO MÉTODO: Recalcula la fecha de entrega para un pedido ya pagado.
     */
    public function recalculateForPaidOrder(Pedido $pedido): \DateTime
    {
        if (!$this->empresaConfig) {
            return new \DateTime();
        }

        $diasBase = $this->empresaConfig->getMaximoDiasSinImprimir();
        if ($pedido->compruebaTrabajos()) {
            $diasBase = $pedido->getPedidoExpres() ? 7 : $this->empresaConfig->getMaximoDiasConImpresion();
        }

        // Sumamos los días adicionales del proveedor
        $sumaDiasProveedor = 0;
        foreach ($pedido->getLineas() as $linea) {
            $diasEnvio = $linea->getProducto()->getModelo()->getProveedor()->getDiasEnvio();
            if ($diasEnvio > $sumaDiasProveedor) {
                $sumaDiasProveedor = $diasEnvio;
            }
        }

        // --- NUEVO: Obtener días extra por complejidad de personalización ---
        $diasExtraPersonalizacion = $this->getMaxDiasProduccionExtra($pedido);

        $diasTotales = $diasBase + $sumaDiasProveedor + ($pedido->getDiasAdicionales() ?? 0) + $diasExtraPersonalizacion;

        return $this->calculateDate($diasTotales, new \DateTime());
    }

    /**
     * Helper privado para extraer el MAXIMO tiempo de personalización de todas las líneas.
     * Si una camiseta tarda 2 días y otra 10, el pedido se retrasa 10 días (no 12).
     */
    private function getMaxDiasProduccionExtra(Pedido $pedido): int
    {
        $maxDias = 0;

        foreach ($pedido->getLineas() as $linea) {
            /** @var PedidoLinea $linea */
            // Obtenemos la colección de relaciones 'PedidoLineaHasTrabajo'
            foreach ($linea->getPersonalizaciones() as $relacionTrabajo) {
                /** @var \App\Entity\PedidoLineaHasTrabajo $relacionTrabajo */

                // 1. Accedemos al trabajo real
                $pedidoTrabajo = $relacionTrabajo->getPedidoTrabajo();

                if ($pedidoTrabajo) {
                    // 2. Accedemos a la definición de la personalización
                    $personalizacion = $pedidoTrabajo->getPersonalizacion();

                    if ($personalizacion) {
                        // 3. Obtenemos los días extra (usamos método si existe, sino 0 por seguridad)
                        $dias = method_exists($personalizacion, 'getTiempoPersonalizacion')
                            ? $personalizacion->getTiempoPersonalizacion()
                            : 0;

                        // Nos quedamos siempre con el proceso más lento (cuello de botella)
                        if ($dias > $maxDias) {
                            $maxDias = $dias;
                        }
                    }
                }
            }
        }

        return $maxDias;
    }
}