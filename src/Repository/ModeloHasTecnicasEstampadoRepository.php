<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\ModeloHasTecnicasEstampado; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModeloHasTecnicasEstampado> // <-- CAMBIAR ESTO
 */
class ModeloHasTecnicasEstampadoRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModeloHasTecnicasEstampado::class); // <-- CAMBIAR ESTO
    }
}