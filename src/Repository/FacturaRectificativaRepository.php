<?php

namespace App\Repository;

use App\Entity\FacturaRectificativa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FacturaRectificativa>
 */
class FacturaRectificativaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FacturaRectificativa::class);
    }

    /**
     * Encuentra el último número de factura rectificativa para un año fiscal dado.
     */
    public function findLastNumberByYear(int $fiscalYear): int
    {
        // Formato esperado: R25/00001, donde 25 es el año fiscal.
        $serie = 'R' . $fiscalYear . '/';

        // Versión más robusta: ordena por ID y obtén el último registro.
        $lastInvoice = $this->createQueryBuilder('fr')
            ->where('fr.numeroFactura LIKE :serie')
            ->setParameter('serie', $serie . '%')
            ->orderBy('fr.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Si no hay ninguna factura para ese año, empezamos desde 0.
        if ($lastInvoice === null) {
            return 0;
        }

        // Extraemos la parte numérica después de la barra "/"
        $lastNumberStr = substr($lastInvoice->getNumeroFactura(), strpos($lastInvoice->getNumeroFactura(), '/') + 1);
        return (int)$lastNumberStr;
    }

    public function findPendingLroe(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.verifactuHash IS NOT NULL')
            ->andWhere('f.verifactuEnviadoAt IS NULL')
            ->orderBy('f.fecha', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
