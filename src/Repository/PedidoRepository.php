<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\Pedido; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pedido> // <-- CAMBIAR ESTO
 */
class PedidoRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pedido::class); // <-- CAMBIAR ESTO
    }

    // --- MÉTODO AÑADIDO ---
    /**
     * Encuentra todos los pedidos que están listos para ser pedidos a un proveedor.
     * @return Pedido[]
     */
    public function findOrdersForSupplierRequest(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.estado IN (:statuses)')
            ->andWhere('p.cantidadPagada > 0')
            ->andWhere('p.fechaEntrega IS NOT NULL')
            ->setParameter('statuses', [3, 4, 10])
            ->getQuery()
            ->getResult();
    }

}