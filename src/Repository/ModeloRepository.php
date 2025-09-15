<?php
// src/Repository/ModeloRepository.php

namespace App\Repository;

use App\Entity\Modelo;
use App\Model\Filtros;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Sonata\ClassificationCategory;
use Doctrine\DBAL\Connection; // <-- 1. Se añade el 'use' para la Conexión

/**
 * @extends ServiceEntityRepository<Modelo>
 */
class ModeloRepository extends ServiceEntityRepository
{
    public const PAGINATOR_PER_PAGE = 36;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Modelo::class);
    }

    /**
     * MÉTODO PRINCIPAL: Obtiene los resultados paginados.
     */
    public function findByFiltros(Filtros $filtros, int $page = 1): Paginator
    {
        $qb = $this->createFindByFiltrosQueryBuilder($filtros);

        $paginator = new Paginator($qb->getQuery());
        $paginator
            ->getQuery()
            ->setFirstResult(self::PAGINATOR_PER_PAGE * ($page - 1))
            ->setMaxResults(self::PAGINATOR_PER_PAGE);

        return $paginator;
    }

    /**
     * Analiza los resultados de una búsqueda y devuelve los filtros disponibles.
     */
    public function findAvailableFilters(Filtros $filtros): array
    {
        $qb = $this->createFindByFiltrosQueryBuilder($filtros);

        $qb->select('DISTINCT m.id');
        $modelIds = array_column($qb->getQuery()->getScalarResult(), 'id');

        if (empty($modelIds)) {
            return ['fabricantes' => [], 'colores' => [], 'atributos' => []];
        }

        // Obtener fabricantes disponibles
        $fabricantes = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT f')
            ->from('App\Entity\Fabricante', 'f')
            ->join('f.modelos', 'm')
            ->where('m.id IN (:ids)')
            ->setParameter('ids', $modelIds)
            ->getQuery()->getResult();

        // Obtener colores disponibles
        $colores = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT c')
            ->from('App\Entity\Color', 'c')
            ->join('c.productos', 'p')
            ->where('p.modelo IN (:ids)')
            ->setParameter('ids', $modelIds)
            ->groupBy('c.rgbUnificado')
            ->orderBy('c.codigoRGB', 'ASC')
            ->getQuery()->getResult();

        // Obtener atributos disponibles
        $atributosRaw = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT a')
            ->from('App\Entity\ModeloAtributo', 'a')
            ->join('a.modelos', 'm')
            ->where('m.id IN (:ids)')
            ->setParameter('ids', $modelIds)
            ->getQuery()->getResult();

        $atributos = [];
        foreach ($atributosRaw as $atributo) {
            $atributos[$atributo->getNombre()][] = $atributo;
        }

        return [
            'fabricantes' => $fabricantes,
            'colores' => $colores,
            'atributos' => $atributos,
        ];
    }

    /**
     * Este es ahora el "cerebro" que construye la consulta, fusionando tu lógica antigua.
     */
    private function createFindByFiltrosQueryBuilder(Filtros $filtros): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.activo = true')
            ->andWhere('m.precioMin > 0');

        if ($filtros->getBusqueda()) {
            // Lógica de Búsqueda por palabra clave
            if ($filtros->getBusqueda()) {
                $keywords = explode(" ", $filtros->getBusqueda());
                $qb->leftJoin('m.fabricante', 'f');

                foreach ($keywords as $key => $keyword) {
                    if (!empty($keyword)) {
                        $paramName = ':busqueda' . $key;
                        $qb->andWhere($qb->expr()->orX(
                            'm.referencia LIKE ' . $paramName,
                            'm.nombre LIKE ' . $paramName,
                            'f.nombre LIKE ' . $paramName
                        ))->setParameter($paramName, '%' . rtrim($keyword, 'es') . '%');
                    }
                }
            }
        }

        if ($filtros->getCategory()) {

            $idsCategorias = [$filtros->getCategory()->getId()];
            foreach ($filtros->getCategory()->getChildren() as $hijo) { $idsCategorias[] = $hijo->getId(); }
            $qb->leftJoin('m.category', 'c')
                ->andWhere($qb->expr()->in('c.id', ':idsCategorias'))
                ->setParameter('idsCategorias', $idsCategorias);
        }

        if ($filtros->getFabricante()) {

            $qb->andWhere('m.fabricante = :fabricanteId')
                ->setParameter('fabricanteId', $filtros->getFabricante()->getId());
        }

        if ($filtros->getFamilia()) {

            $qb->leftJoin("m.familias", "fam")
                ->andWhere('fam.id = :familiaId OR m.familia = :familiaId')
                ->setParameter('familiaId', $filtros->getFamilia()->getId());
        }

        if (!empty($filtros->getColores())) {

            $qb->leftJoin('m.productos', 'p_color')
                ->leftJoin('p_color.color', 'co')
                ->andWhere('co.rgbUnificado IN (:colores)')
                ->setParameter('colores', $filtros->getColores());
        }

        if (!empty($filtros->getAtributos())) {
            $mapaAtributos = [
                'genro_mujer' => 115, 'genro_hombre' => 114, 'isForChildren' => 116,
                'algodon' => 130, 'poliester' => 131, 'mezcla' => 129, 'tecnica' => 139,
                'mangacorta' => 123, 'mangalarga' => 124, 'sinmangas' => 125,
                'cuellopico' => 34, 'colorfluor' => 106, 'altavisibilidad' => 31,
                'cremallera' => 25, 'concapucha' => 33, 'conbolsillo' => 140, 'tallasgrandes' => 19
            ];
            $idsAtributos = array_map(fn($attr) => $mapaAtributos[$attr] ?? $attr, $filtros->getAtributos());

            $idsModelosPorAtributo = $this->findModelosIdsByAtributos($idsAtributos);
            if (!empty($idsModelosPorAtributo)) {
                $qb->andWhere('m.id IN (:idsModelosPorAtributo)')
                    ->setParameter('idsModelosPorAtributo', $idsModelosPorAtributo);
            } else {
                $qb->andWhere('1=0');
            }
        }

        // Lógica de Ordenación
        switch ($filtros->getOrden()) {
//            case "Precio ASC": $qb->orderBy('m.precioMin', 'ASC'); break;
//            case "Precio DESC": $qb->orderBy('m.precioMin', 'DESC'); break;

            case "Orden Por Defecto": $qb->addOrderBy('m.importancia', 'DESC')->addOrderBy('o.precioMinAdulto', 'ASC'); break;
            case "Precio Adulto DESC": $qb->orderBy('m.precioMinAdulto', 'DESC'); break;
            case "Precio Adulto ASC": $qb->orderBy('m.precioMinAdulto', 'ASC'); break;
            case "Precio DESC": $qb->orderBy('m.precioMin', 'DESC'); break;
            case "Precio ASC": $qb->orderBy('m.precioMin', 'ASC'); break;
            case "Nombre DESC": $qb->orderBy('m.nombre', 'DESC'); break;
            case "Nombre ASC": $qb->orderBy('m.nombre', 'ASC'); break;

            default:
                $qb->orderBy('m.importancia', 'DESC')->addOrderBy('m.precioMinAdulto', 'ASC');
                break;
        }

        $qb->groupBy('m.id');
        return $qb;
    }

    /**
     * Método auxiliar para obtener IDs de modelos que tienen TODOS los atributos seleccionados.
     */
    private function findModelosIdsByAtributos(array $atributosIds): array
    {
        if (empty($atributosIds)) {
            return [];
        }

        // CORRECCIÓN: Se cambia 'atributo_id' por el nombre correcto de la columna 'modelo_atributo_id'
        $sql = "SELECT modelo_id FROM modelo_modeloatributos WHERE modelo_atributo_id IN (:atributos) GROUP BY modelo_id HAVING COUNT(DISTINCT modelo_atributo_id) = :count";

        // --- INICIO DE LA CORRECCIÓN ---
        // 2. Obtenemos la conexión a la base de datos
        $conn = $this->getEntityManager()->getConnection();

        // 3. Ejecutamos la consulta pasándole los tipos de parámetros explícitamente
        $result = $conn->executeQuery($sql, [
            'atributos' => $atributosIds,
            'count' => count($atributosIds)
        ], [
            'atributos' => Connection::PARAM_INT_ARRAY // <-- La línea clave que lo soluciona
        ]);
        // --- FIN DE LA CORRECCIÓN ---

        return $result->fetchFirstColumn();
    }

    public function findDestacadosParaHome(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.activo = :activo')
            ->andWhere('m.destacado = :destacado')
            ->orderBy('m.importancia', 'DESC')
            ->setParameter('activo', true)
            ->setParameter('destacado', true)
            ->getQuery()->getResult();
    }
}