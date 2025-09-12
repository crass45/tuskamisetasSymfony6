<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\PersonalizacionPrecioCantidad; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonalizacionPrecioCantidad> // <-- CAMBIAR ESTO
 */
class PersonalizacionPrecioCantidadRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonalizacionPrecioCantidad::class); // <-- CAMBIAR ESTO
    }
}