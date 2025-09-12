<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

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
}