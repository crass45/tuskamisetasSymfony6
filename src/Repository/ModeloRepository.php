<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\Modelo;


use App\Model\Filtros;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

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
     * Encuentra y pagina los modelos basándose en un objeto de filtros.
     * Esta función reemplaza toda la lógica del antiguo FiltrosController.
     */
    public function findByFiltros(Filtros $filtros, int $page = 1): Paginator
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.activo = :activo')
            ->andWhere('m.precioMin > 0')
            ->setParameter('activo', true);

        // Filtro por Búsqueda de Texto
        if ($filtros->getBusqueda()) {
            $qb->leftJoin('m.fabricante', 'f')
                ->andWhere('m.referencia LIKE :busqueda OR m.nombre LIKE :busqueda OR f.nombre LIKE :busqueda')
                ->setParameter('busqueda', '%' . $filtros->getBusqueda() . '%');
        }

        // Filtro por Categoría (y sus hijos)
        if ($filtros->getCategory()) {
            $categoryIds = [$filtros->getCategory()->getId()];
            foreach ($filtros->getCategory()->getChildren() as $child) {
                $categoryIds[] = $child->getId();
            }
            $qb->leftJoin('m.category', 'c')
                ->andWhere('c.id IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds);
        }

        // Filtro por Fabricante (Marca)
        if ($filtros->getFabricante()) {
            $qb->andWhere('m.fabricante = :fabricanteId')
                ->setParameter('fabricanteId', $filtros->getFabricante()->getId());
        }

        // Filtro por Familia
        if ($filtros->getFamilia()) {
            $qb->leftJoin('m.familias', 'fam')
                ->andWhere('fam.id = :familiaId OR m.familia = :familiaId')
                ->setParameter('familiaId', $filtros->getFamilia()->getId());
        }

        // Filtro por Colores
        if (!empty($filtros->getColores())) {
            $qb->leftJoin('m.productos', 'p_color')
                ->leftJoin('p_color.color', 'co')
                ->andWhere('co.rgbUnificado IN (:colores)')
                ->setParameter('colores', $filtros->getColores());
        }

        // Filtro por Atributos (lógica compleja)
        if (!empty($filtros->getAtributos())) {
            $idsModelosPorAtributo = $this->findModelosIdsByAtributos($filtros->getAtributos());
            if (!empty($idsModelosPorAtributo)) {
                $qb->andWhere('m.id IN (:idsModelosPorAtributo)')
                    ->setParameter('idsModelosPorAtributo', $idsModelosPorAtributo);
            } else {
                // Si no hay modelos que cumplan todos los atributos, no devolver nada.
                $qb->andWhere('1=0');
            }
        }

        // Lógica de Ordenación
        switch ($filtros->getOrden()) {
            case "Precio ASC":
                $qb->orderBy('m.precioMin', 'ASC');
                break;
            case "Precio DESC":
                $qb->orderBy('m.precioMin', 'DESC');
                break;
            // ... otros casos de ordenación ...
            default:
                $qb->orderBy('m.importancia', 'DESC')->addOrderBy('m.precioMin', 'ASC');
                break;
        }

        $qb->groupBy('m.id');

        // Paginación
        $paginator = new Paginator($qb->getQuery());
        $paginator
            ->getQuery()
            ->setFirstResult(self::PAGINATOR_PER_PAGE * ($page - 1))
            ->setMaxResults(self::PAGINATOR_PER_PAGE);

        return $paginator;
    }

    /**
     * Método auxiliar para obtener IDs de modelos que tienen TODOS los atributos seleccionados.
     */
    private function findModelosIdsByAtributos(array $atributosIds): array
    {
        if (empty($atributosIds)) {
            return [];
        }

        $sql = "SELECT modelo_id FROM modelo_modeloatributos WHERE atributo_id IN (:atributos) GROUP BY modelo_id HAVING COUNT(DISTINCT atributo_id) = :count";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery([
            'atributos' => $atributosIds,
            'count' => count($atributosIds)
        ]);

        return $result->fetchFirstColumn();
    }


    public function findDestacadosParaHome()
    {
        $qb = $this->createQueryBuilder('m'); // 'm' es el alias para Modelo

        $qb->select('m, c, i') // Seleccionamos Modelo, Category e Imagen
        ->leftJoin('m.category', 'c')   // <-- CORRECCIÓN: 'm.category' en lugar de 'm.categoria'
        ->leftJoin('m.imagen', 'i')     // Esto es correcto, tu propiedad se llama 'imagen'
        ->where('m.activo = :activo')
            ->andWhere('m.destacado = :destacado')
            ->orderBy('m.importancia', 'DESC')
            ->setParameter('activo', true)
            ->setParameter('destacado', true);

        return $qb->getQuery()->getResult();
    }

    public function findByFiltersQueryBuilder(array $filtros, $busqueda = null, $idioma = 'es')
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.activo = true')
            ->andWhere('o.precioMin > 0')
            ->andWhere('o.precioMin < 10000')
            ->andWhere('o.precioMin != 99.999');

        // --- Lógica de Búsqueda por palabra clave (unificada) ---
        if ($busqueda) {
            $keywords = explode(" ", $busqueda);
            $qb->leftJoin('o.translations', 'mt', 'WITH', 'mt.locale = :locale');
            $qb->leftJoin('o.fabricante', 'f');

            foreach ($keywords as $key => $keyword) {
                if (!empty($keyword)) {
                    // Usamos un parámetro único por cada palabra para evitar colisiones
                    $paramName = ':nombre' . $key;
                    $qb->andWhere($qb->expr()->orX(
                        'o.referencia LIKE ' . $paramName,
                        'o.nombre LIKE ' . $paramName,
                        'f.nombre LIKE ' . $paramName,
                        'mt.descripcion LIKE ' . $paramName
                    ))->setParameter($paramName, '%' . rtrim($keyword, 'es') . '%');
                }
            }
            $qb->setParameter('locale', $idioma);
        }

        // --- Lógica de Filtros ---
        $qb->leftJoin("o.modeloHasProductos", "a");
        $qb->leftJoin("o.category", "c");

        // Filtro de Colores (¡FORMA SEGURA!)
        if (!empty($filtros['colores'])) {
            $qb->leftJoin("a.color", "color")
                ->andWhere($qb->expr()->in('color.rgbUnificado', ':colores'))
                ->setParameter('colores', $filtros['colores']);
        }

        // Filtro de Atributos (¡FORMA SEGURA Y EFICIENTE!)
        if (!empty($filtros['atributos']) && $filtros['atributos'][0] != 'no-filter') {
            // Mapeo de atributos (esto podría ir a un servicio o a la entidad Filtros si lo prefieres)
            $mapaAtributos = [
                'genro_mujer' => "115", 'genro_hombre' => "114", 'isForChildren' => "116",
                'algodon' => "130", 'poliester' => "131", 'mezcla' => "129", 'tecnica' => "139",
                'mangacorta' => "123", 'mangalarga' => "124", 'sinmangas' => "125",
                'cuellopico' => "34", 'colorfluor' => "106", 'altavisibilidad' => "31",
                'cremallera' => "25", 'concapucha' => "33", 'conbolsillo' => "140", 'tallasgrandes' => "19"
            ];

            $idsAtributos = [];
            foreach ($filtros['atributos'] as $atributo) {
                if (isset($mapaAtributos[$atributo])) {
                    $idsAtributos[] = $mapaAtributos[$atributo];
                }
            }

            if (!empty($idsAtributos)) {
                // Subconsulta para encontrar modelos que tienen TODOS los atributos seleccionados
                $subQb = $this->_em->createQueryBuilder()
                    ->select('sub_mma.modelo_id')
                    ->from('modelo_modeloatributos', 'sub_mma')
                    ->where($qb->expr()->in('sub_mma.atributo_id', ':idsAtributos'))
                    ->groupBy('sub_mma.modelo_id')
                    ->having('COUNT(sub_mma.modelo_id) >= :countAtributos');

                $qb->andWhere($qb->expr()->in('o.id', $subQb->getDQL()))
                    ->setParameter('idsAtributos', $idsAtributos)
                    ->setParameter('countAtributos', count($idsAtributos));
            } else {
                $qb->andWhere('1=0'); // Si los atributos no son válidos, no devolver nada
            }
        }

        // Filtro de Familia
        if (!empty($filtros['familia'])) {
            $qb->leftJoin("o.familias", "familias")
                ->andWhere('familias.id = :familiaId OR o.familia = :familiaId')
                ->setParameter('familiaId', $filtros['familia']);
        }

        // Filtro de Fabricante
        if (!empty($filtros['fabricante'])) {
            $qb->andWhere('o.fabricante = :fabricanteId')
                ->setParameter('fabricanteId', $filtros['fabricante']);
        }

        // Filtro de Categoría (con hijos)
        if (!empty($filtros['category'])) {
            $categoryRepo = $this->_em->getRepository("ApplicationSonataClassificationBundle:Category");
            $categoria = $categoryRepo->find($filtros['category']);
            if ($categoria) {
                $idsCategorias = [$categoria->getId()];
                foreach ($categoria->getChildren() as $hijo) {
                    $idsCategorias[] = $hijo->getId();
                }
                $qb->andWhere($qb->expr()->in('c.id', ':idsCategorias'))
                    ->setParameter('idsCategorias', $idsCategorias);
            }
        }

        // --- Ordenación ---
        $orden = $filtros['orden'] ?? 'default'; // Usar 'default' si no viene
        switch ($orden) {
            case "Precio Adulto DESC":
                $qb->orderBy('o.precioMinAdulto', 'DESC');
                break;
            case "Precio Adulto ASC":
                $qb->orderBy('o.precioMinAdulto', 'ASC');
                break;
            // ... otros casos de ordenación
            default:
                $qb->addOrderBy('o.importancia', 'DESC')->addOrderBy('o.precioMinAdulto', 'ASC');
                break;
        }

        $qb->groupBy('o.id');
        return $qb;
    }

}