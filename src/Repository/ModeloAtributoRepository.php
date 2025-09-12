<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\ModeloAtributo; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModeloAtributo> // <-- CAMBIAR ESTO
 */
class ModeloAtributoRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModeloAtributo::class); // <-- CAMBIAR ESTO
    }

    /**
     * Encuentra todos los atributos únicos, agrupados por su nombre,
     * asociados a una lista de IDs de modelos.
     */
    public function findFromModelos(array $modeloIds): array
    {
        if (empty($modeloIds)) {
            return [];
        }

        $results = $this->createQueryBuilder('ma')
            ->select('ma')
            // 'm.atributos' es la relación ManyToMany en la entidad Modelo
            ->join('ma.modelos', 'm')
            ->where('m.id IN (:modeloIds)')
            ->setParameter('modeloIds', $modeloIds)
            ->orderBy('ma.nombre', 'ASC')
            ->addOrderBy('ma.valor', 'ASC')
            ->distinct(true)
            ->getQuery()
            ->getResult();

        // Agrupamos los resultados por el nombre del atributo (ej: 'Genero', 'Detalles')
        $groupedResults = [];
        foreach ($results as $atributo) {
            $groupedResults[$atributo->getNombre()][] = $atributo;
        }

        return $groupedResults;
    }
}