<?php
// src/Service/DeliveryDateService.php

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
     * Este es el nuevo método que solicitaste.
     *
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

        // 2. Sumar días adicionales del pedido y del proveedor
        $diasExtra = $maxDiasEnvioProveedor + ($pedido->getDiasAdicionales() ?? 0);

        // 3. Determinar los días de producción (mínimo, máximo y exprés)
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
        $diasExpressProduccion = ($this->empresaConfig->getMinimoDiasSinImprimir() ?? 0) + 3;

        // 4. Calcular los totales de días laborables
        $totalDiasMin = $diasMinProduccion + $diasExtra;
        $totalDiasMax = $diasMaxProduccion + $diasExtra;
        $totalDiasExpress = $diasExpressProduccion + $diasExtra;

        // 5. Calcular las fechas finales usando tu método 'calculateDate' y devolver el array
        return [
            'min' => $this->calculateDate($totalDiasMin, clone $fechaInicio),
            'max' => $this->calculateDate($totalDiasMax, clone $fechaInicio),
            'express' => $this->calculateDate($totalDiasExpress, clone $fechaInicio),
        ];
    }


    /**
     * Calcula las fechas de entrega para un modelo específico.
     * Reemplaza la lógica que estaba duplicada en el controlador.
     */
    public function getDeliveryDatesForModel(\App\Entity\Modelo $modelo): array
    {
        if (!$this->empresaConfig) {
            return []; // Devuelve vacío si no hay configuración de empresa
        }

        $sumaDias = $modelo->getProveedor()?->getDiasEnvio() ?? 0;

        // MIGRACIÓN: Se han añadido más fechas de entrega que tenías en tu controlador original.
        $diasSinImprimir1 = $this->empresaConfig->getMinimoDiasSinImprimir() + $sumaDias;
        $diasImpreso1 = $this->empresaConfig->getMinimoDiasConImpresion() + $sumaDias;
        $diasSinImprimir2 = $this->empresaConfig->getMaximoDiasSinImprimir() + $sumaDias;
        $diasImpreso2 = $this->empresaConfig->getMaximoDiasConImpresion() + $sumaDias;
        $diasExpress = $diasSinImprimir1 + 3; // Lógica para el servicio express

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
     * Este es tu método `calculateDate` existente, lo he hecho público para reutilizarlo si es necesario.
     */
    public function calculateDate(int $businessDays, \DateTime $startDate): \DateTime
    {
        $date = clone $startDate;
        $daysAdded = 0;

        // Si no hay días que añadir, devolvemos la fecha de inicio
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

    /**
     * Comprueba si una fecha está dentro del periodo de vacaciones de la empresa.
     */
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

    /**
     * Comprueba si una fecha dada es un festivo nacional en España.
     * NOTA: Esta lista es para 2025. Debería actualizarse anualmente o moverse a la BBDD.
     */
    private function isHoliday(\DateTime $date): bool
    {
        $holidays = [
            '2025-01-01', // Año Nuevo
            '2025-01-06', // Epifanía del Señor (Reyes)
            '2025-04-18', // Viernes Santo
            '2025-05-01', // Fiesta del Trabajo
            '2025-08-15', // Asunción de la Virgen
            '2025-10-12', // Fiesta Nacional de España (aunque sea domingo)
            '2025-11-01', // Todos los Santos (aunque sea sábado)
            '2025-12-06', // Día de la Constitución (aunque sea sábado)
            '2025-12-08', // Inmaculada Concepción
            '2025-12-25', // Navidad
        ];

        $dateString = $date->format('Y-m-d');

        return in_array($dateString, $holidays, true);
    }

    /**
     * NUEVO MÉTODO: Recalcula la fecha de entrega para un pedido ya pagado.
     * Migración de la lógica de tu 'pedidoPagoConfirmadoBancoAction'.
     */
    public function recalculateForPaidOrder(Pedido $pedido): \DateTime
    {
        if (!$this->empresaConfig) {
            return new \DateTime(); // Devolver ahora si no hay configuración
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

        $diasTotales = $diasBase + $sumaDiasProveedor + $pedido->getDiasAdicionales();

        return $this->calculateDate($diasTotales, new \DateTime());
    }
}

