<?php
// src/Service/DeliveryDateService.php

namespace App\Service;

use App\Entity\Empresa;
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

        var_dump($diasSinImprimir1);
        var_dump($diasSinImprimir2);
        var_dump($diasImpreso1);
        var_dump($diasImpreso2);
        var_dump($diasExpress);

        return [
            'fechaEntregaSinImprimir' => $this->calculateDate($diasSinImprimir1),
            'fechaEntregaImpreso' => $this->calculateDate($diasImpreso1),
            'fechaEntregaSinImprimir2' => $this->calculateDate($diasSinImprimir2),
            'fechaEntregaImpreso2' => $this->calculateDate($diasImpreso2),
            'fechaEntregaExpress' => $this->calculateDate($diasExpress),
        ];
    }

    /**
     * MIGRACIÓN: Esta es una implementación más avanzada que tiene en cuenta
     * los fines de semana y los festivos nacionales de España.
     */
    private function calculateDate(int $businessDays): \DateTime
    {
        $date = new \DateTime();
        $daysAdded = 0;

        while ($daysAdded < $businessDays) {
            $date->add(new \DateInterval('P1D')); // Añadimos un día natural

            // N es la representación numérica del día de la semana (6 = Sábado, 7 = Domingo)
            $dayOfWeek = (int) $date->format('N');

            // Si es fin de semana o festivo, no lo contamos y seguimos al siguiente día
            if ($dayOfWeek >= 6 || $this->isHoliday($date)) {
                continue;
            }

            // Si es un día laborable, incrementamos el contador
            $daysAdded++;
        }

        return $date;
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
}

