<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\GastosEnvio; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GastosEnvio> // <-- CAMBIAR ESTO
 */
class GastosEnvioRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GastosEnvio::class); // <-- CAMBIAR ESTO
    }

    public function findPriceForQuantity(int $cantidad): ?GastosEnvio
    {
        return $this->createQueryBuilder('g')
            ->where('g.cantidad <= :cantidad')
            ->setParameter('cantidad', $cantidad)
            ->orderBy('g.cantidad', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}