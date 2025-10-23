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

    public function findPreviousWithHash(Factura $factura): ?Factura
    {
        return $this->createQueryBuilder('f')
            ->where('f.id < :id')
            ->andWhere('f.verifactuHash IS NOT NULL')
            ->setParameter('id', $factura->getId())
            ->orderBy('f.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Factura[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.fecha BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('f.fecha', 'ASC')
            ->addOrderBy('f.numeroFactura', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra los datos del último registro Verifactu (factura o rectificativa)
     * para el encadenamiento.
     * @return array{hash: string, number: string, date: \DateTimeImmutable}|null
     */
    public function findLastVerifactuRecordData(): ?array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Modificamos la consulta para que devuelva todos los datos necesarios
        $sql = '
            (SELECT verifactu_hash as hash, nombre as number, fecha as date FROM factura WHERE verifactu_hash IS NOT NULL)
            UNION
            (SELECT verifactu_hash as hash, numero_factura as number, fecha as date FROM factura_rectificativa WHERE verifactu_hash IS NOT NULL)
            ORDER BY date DESC, number DESC
            LIMIT 1
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $lastRecord = $result->fetchAssociative();

        if (!$lastRecord) {
            return null;
        }

        return [
            'hash' => $lastRecord['hash'],
            'number' => $lastRecord['number'],
            'date' => $lastRecord['date']
        ];
    }

    /**
     * Encuentra todas las facturas que tienen hash pero no han sido enviadas.
     * @return Factura[]
     */
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

    // --- ESTE ES EL MÉTODO QUE FALTABA ---
    /**
     * Encuentra los datos del último registro Verifactu (factura o rectificativa)
     * ANTERIOR a la fecha y ID de la entidad actual.
     *
     * @return array{hash: string, number: string, date: string}|null
     */
    public function findPreviousVerifactuRecordData(\DateTimeInterface $currentDate, int $currentId, string $currentTable): ?array
    {
        $conn = $this->getEntityManager()->getConnection();
        $dateString = $currentDate->format('Y-m-d H:i:s');

        // Esta consulta busca el registro más reciente (por fecha, luego por ID)
        // que sea anterior al registro que estamos procesando.
        $sql = "
            SELECT * FROM (
                (SELECT id, fecha, verifactu_hash as hash, nombre as number, 'factura' as table_name 
                 FROM factura WHERE verifactu_hash IS NOT NULL)
                UNION
                (SELECT id, fecha, verifactu_hash as hash, numero_factura as number, 'factura_rectificativa' as table_name 
                 FROM factura_rectificativa WHERE verifactu_hash IS NOT NULL)
            ) as all_records
            WHERE (fecha < :currentDate) OR (fecha = :currentDate AND NOT (id = :currentId AND table_name = :currentTable))
            ORDER BY fecha DESC, id DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'currentDate' => $dateString,
            'currentId' => $currentId,
            'currentTable' => $currentTable
        ]);
        $lastRecord = $result->fetchAssociative();

        if (!$lastRecord) {
            return null;
        }

        return [
            'hash' => $lastRecord['hash'],
            'number' => $lastRecord['number'],
            'date' => $lastRecord['fecha'] // El servicio se encargará de la conversión
        ];
    }
}