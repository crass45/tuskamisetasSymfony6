<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\PedidoLineaHasTrabajo; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PedidoLineaHasTrabajo> // <-- CAMBIAR ESTO
 */
class PedidoLineaHasTrabajoRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PedidoLineaHasTrabajo::class); // <-- CAMBIAR ESTO
    }
}