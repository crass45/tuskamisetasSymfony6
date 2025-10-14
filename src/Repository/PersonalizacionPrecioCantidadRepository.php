<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\Personalizacion;
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

    /**
     * Encuentra el tramo de precios de una personalización que corresponde a una cantidad dada.
     *
     * @param Personalizacion $personalizacion La personalización para la que se buscan precios.
     * @param int $cantidad La cantidad de prendas.
     * @return PersonalizacionPrecioCantidad|null
     */
    public function findPriceByQuantity(Personalizacion $personalizacion, int $cantidad): ?PersonalizacionPrecioCantidad
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.personalizacion = :personalizacion')
            ->setParameter('personalizacion', $personalizacion)

            // Condición: La cantidad del tramo debe ser menor o igual a la que buscamos
            ->andWhere('p.cantidad <= :cantidad')
            ->setParameter('cantidad', $cantidad)

            // Ordenamos de mayor a menor cantidad
            ->orderBy('p.cantidad', 'DESC')
            // Obtenemos solo el primer resultado, que será el tramo correcto
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}