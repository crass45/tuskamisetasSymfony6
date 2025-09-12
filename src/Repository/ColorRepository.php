<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\Color; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Color> // <-- CAMBIAR ESTO
 */
class ColorRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Color::class); // <-- CAMBIAR ESTO
    }

    /**
     * Encuentra todos los colores únicos asociados a una lista de IDs de modelos.
     */
    public function findFromModelos(array $modeloIds): array
    {
        if (empty($modeloIds)) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->select('c')
            // 'p.color' y 'm.modeloHasProductos' son las relaciones en las entidades
            ->join('App\Entity\Producto', 'p', 'WITH', 'p.color = c.id')
            ->join('p.modelo', 'm')
            ->where('m.id IN (:modeloIds)')
            ->setParameter('modeloIds', $modeloIds)
            ->groupBy('c.rgbUnificado') // Agrupamos por el color unificado para no repetir
            ->orderBy('c.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}