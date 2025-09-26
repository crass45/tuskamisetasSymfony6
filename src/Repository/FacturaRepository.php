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

    /**
     * Encuentra el número de factura más alto para un año fiscal determinado.
     * Devuelve 0 si no hay ninguna factura para ese año.
     */
    public function findLastNumberByYear(int $fiscalYear): int
    {
        $result = $this->createQueryBuilder('f')
            ->select('MAX(f.numeroFactura)')
            ->where('f.fiscalYear = :year')
            ->setParameter('year', $fiscalYear)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)$result;
    }
}