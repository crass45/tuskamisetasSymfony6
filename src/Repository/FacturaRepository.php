<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\Factura; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Factura> // <-- CAMBIAR ESTO
 */
class FacturaRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Factura::class); // <-- CAMBIAR ESTO
    }
}