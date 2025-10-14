<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\Tarifa;
use App\Entity\TarifaPrecios; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TarifaPrecios> // <-- CAMBIAR ESTO
 */
class TarifaPreciosRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TarifaPrecios::class); // <-- CAMBIAR ESTO
    }

    public function findPriceByQuantity(Tarifa $tarifa, int $cantidad): ?TarifaPrecios
    {
        return $this->createQueryBuilder('tp')
            ->andWhere('tp.tarifa = :tarifa')
            ->setParameter('tarifa', $tarifa)

            // Condición: La cantidad del tramo debe ser menor o igual a la que buscamos
            ->andWhere('tp.cantidad <= :cantidad')
            ->setParameter('cantidad', $cantidad)

            // Ordenamos de mayor a menor cantidad
            ->orderBy('tp.cantidad', 'DESC')
            // Obtenemos solo el primer resultado, que será el tramo correcto
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLowestPriceTier(Tarifa $tarifa): ?TarifaPrecios
    {
        return $this->createQueryBuilder('tp')
            ->andWhere('tp.tarifa = :tarifa')
            ->setParameter('tarifa', $tarifa)
            ->orderBy('tp.precio', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}