<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\AreasTecnicasEstampado; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AreasTecnicasEstampado> // <-- CAMBIAR ESTO
 */
class AreasTecnicasEstampadoRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AreasTecnicasEstampado::class); // <-- CAMBIAR ESTO
    }
}