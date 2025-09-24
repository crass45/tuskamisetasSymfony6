<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\Fabricante; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fabricante> // <-- CAMBIAR ESTO
 */
class FabricanteRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fabricante::class); // <-- CAMBIAR ESTO
    }

    /**
     * Encuentra solo los fabricantes que están activos y tienen al menos un modelo activo.
     * Versión corregida y más robusta usando un JOIN.
     */
    public function findActiveWithProducts(): array
    {
        return $this->getEntityManager()->createQuery(
            'SELECT f
             FROM App\Entity\Fabricante f
             JOIN App\Entity\Modelo m WITH m.fabricante = f
             WHERE m.activo = :activo
             GROUP BY f.id
             ORDER BY f.nombre ASC'
        )
            ->setParameter('activo', true)
            ->getResult();
    }
}