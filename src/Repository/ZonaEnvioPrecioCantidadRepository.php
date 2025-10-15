<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\ZonaEnvio;
use App\Entity\ZonaEnvioPrecioCantidad; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZonaEnvioPrecioCantidad> // <-- CAMBIAR ESTO
 */
class ZonaEnvioPrecioCantidadRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZonaEnvioPrecioCantidad::class); // <-- CAMBIAR ESTO
    }

    // src/Repository/ZonaEnvioPrecioCantidadRepository.php

    /**
     * Encuentra el precio de envío para una zona y un número de bultos.
     * Devuelve el precio del primer bulto + (precio de bulto adicional * (N-1) bultos).
     */
    public function findPriceByBultos(ZonaEnvio $zona, int $bultos): float
    {
        if ($bultos <= 0) {
            return 0.0;
        }

        // Buscamos la configuración de precios para esa zona.
        // Asumimos que solo hay una fila de precios por zona.
        $precios = $this->findOneBy(['zonaEnvio' => $zona]);

        if (!$precios) {
            return 0.0; // O un precio por defecto si no hay configuración para la zona
        }

        $precioPrimerBulto = (float)($precios->getPrecio() ?? 0.0);


            //esta parte es para cuando tengamos bultos adicionales a otro precio
//        $precioBultoAdicional = (float)($precios->getPrecioAdicional() ?? $precioPrimerBulto);
        $precioBultoAdicional = (float)($precios->getPrecio() ?? 0.0);

        if ($bultos === 1) {
            return $precioPrimerBulto;
        }

        // Coste total = Precio del primer bulto + (Precio adicional * (bultos restantes))
        $costeTotal = $precioPrimerBulto + ($precioBultoAdicional * ($bultos - 1));

        return $costeTotal;
    }
}