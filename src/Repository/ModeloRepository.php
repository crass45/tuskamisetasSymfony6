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
     * MÉTODO PRINCIPAL: Obtiene los resultados paginados y OPTIMIZADOS.
     */
    // En src/Repository/ModeloRepository.php

    public function findByFiltros(Filtros $filtros, int $page = 1): Paginator
    {
        $qb = $this->createFindByFiltrosQueryBuilder($filtros);

        // 1. Consulta Principal: Solo datos del Modelo y relaciones simples "To-One"
        // NOTA: He quitado 't' (tarifa) de aquí para cargarla después junto con sus precios
        // y no ralentizar la paginación principal.
        $qb->addSelect('f, p, img')
            ->leftJoin('m.fabricante', 'f')
            ->leftJoin('m.proveedor', 'p')
            ->leftJoin('m.imagen', 'img');

        // Configuración de la consulta
        $query = $qb->getQuery();

        // Hints de optimización para traducciones (si usas Gedmo)
        $query->setHint(
            Query::HINT_CUSTOM_OUTPUT_WALKER,
            'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
        );
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $filtros->getLocale() ?? 'es'
        );

        $paginator = new Paginator($query, true);

        $paginator
            ->getQuery()
            ->setFirstResult(self::PAGINATOR_PER_PAGE * ($page - 1))
            ->setMaxResults(self::PAGINATOR_PER_PAGE);

        // 3. TRUCO DE RENDIMIENTO: "Eager Loading" Masivo (Hidratación)
        // Obtenemos los modelos de esta página para hidratar sus datos en BLOQUE.
        $modelosEnPagina = $paginator->getIterator()->getArrayCopy();

        if (!empty($modelosEnPagina)) {
            $em = $this->getEntityManager();

            // A) Cargar Productos + Colores + Imágenes de Productos (de golpe)
            // Esto elimina la consulta gigante de 800ms que tenías antes.
            $em->createQueryBuilder()
                ->select('PARTIAL m.{id}', 'p', 'c', 'pi')
                ->from(Modelo::class, 'm')
                ->leftJoin('m.productos', 'p')
                ->leftJoin('p.color', 'c')
                ->leftJoin('p.imagen', 'pi')
                ->where('m IN (:modelos)')
                ->andWhere('p.activo = true')
                ->setParameter('modelos', $modelosEnPagina)
                ->getQuery()
                ->getResult();

            // B) Cargar Tarifas y SUS PRECIOS (Solución al N+1 de tarifas)
            // Gracias a que tu Tarifa.php está bien, esto cargará los precios de golpe
            // evitando las 40 consultas repetidas de "tarifa_precios".
            $em->createQueryBuilder()
                ->select('PARTIAL m.{id}', 't', 'tp')
                ->from(Modelo::class, 'm')
                ->leftJoin('m.tarifa', 't')
                ->leftJoin('t.precios', 'tp')
                ->where('m IN (:modelos)')
                ->setParameter('modelos', $modelosEnPagina)
                ->getQuery()
                ->getResult();

            // C) Cargar Atributos (Algodón, etc.)
            $em->createQueryBuilder()
                ->select('PARTIAL m.{id}', 'attr')
                ->from(Modelo::class, 'm')
                ->leftJoin('m.atributos', 'attr')
                ->where('m IN (:modelos)')
                ->setParameter('modelos', $modelosEnPagina)
                ->getQuery()
                ->getResult();
        }

        return $paginator;
    }

    /**
     * Devuelve los filtros disponibles (Fabricantes, Colores, Atributos)
     * CORREGIDO: Agrupa colores por tono visual (rgbUnificado) para evitar duplicados.
     */
    public function findAvailableFilters(Filtros $filtros): array
    {
        $em = $this->getEntityManager();

        // 1. FABRICANTES
        $qbFab = $this->createFindByFiltrosQueryBuilder($filtros);
        $qbFab->select('DISTINCT f.id')
            ->join('m.fabricante', 'f')
            ->resetDQLPart('groupBy')
            ->resetDQLPart('orderBy')
            ->orderBy('f.nombre', 'ASC');

        $fabIds = $qbFab->getQuery()->getSingleColumnResult();

        $fabricantes = [];
        if (!empty($fabIds)) {
            $fabricantes = $em->getRepository(\App\Entity\Fabricante::class)
                ->findBy(['id' => $fabIds], ['nombre' => 'ASC']);
        }

        // 2. COLORES (CORREGIDO: Agrupación visual)
        $qbCol = $this->createFindByFiltrosQueryBuilder($filtros);

        // En lugar de traer todos los IDs, traemos "uno cualquiera" (MIN) de cada grupo visual
        $qbCol->select('MIN(c.id)')
            ->join('m.productos', 'p')
            ->join('p.color', 'c')
            ->andWhere('p.activo = true')
            ->resetDQLPart('groupBy') // Quitamos el group by de modelo
            ->resetDQLPart('orderBy')
            ->groupBy('c.rgbUnificado') // <--- CLAVE: Agrupamos por el tono visual
            ->orderBy('c.codigoRGB', 'ASC');

        $colIds = $qbCol->getQuery()->getSingleColumnResult();

        $colores = [];
        if (!empty($colIds)) {
            // Buscamos los objetos color reales usando esos IDs representativos
            $colores = $em->getRepository(\App\Entity\Color::class)
                ->findBy(['id' => $colIds], ['codigoRGB' => 'ASC']);
        }

        // 3. ATRIBUTOS
        $qbAttr = $this->createFindByFiltrosQueryBuilder($filtros);
        $qbAttr->select('DISTINCT a.id')
            ->join('m.atributos', 'a')
            ->resetDQLPart('groupBy')
            ->resetDQLPart('orderBy');

        $attrIds = $qbAttr->getQuery()->getSingleColumnResult();

        $atributos = [];
        if (!empty($attrIds)) {
            $atributosRaw = $em->getRepository(\App\Entity\ModeloAtributo::class)
                ->findBy(['id' => $attrIds]);

            // Agrupamos por nombre
            foreach ($atributosRaw as $atributo) {
                $atributos[$atributo->getNombre()][] = $atributo;
            }
        }

        return [
            'fabricantes' => $fabricantes,
            'colores' => $colores,
            'atributos' => $atributos,
        ];
    }

    /**
     * Cerebro constructor de la consulta (Filtros WHERE).
     */
    private function createFindByFiltrosQueryBuilder(Filtros $filtros): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.activo = true')
            ->andWhere('m.precioMin > 0')
            ->andWhere('m.precioMin < 9999')
        ;

        // --- LOGICA DE BÚSQUEDA HÍBRIDA ---
        if ($filtros->getBusqueda()) {
            $query = $filtros->getBusqueda();
            $conn = $this->getEntityManager()->getConnection();
            $nativeQb = $conn->createQueryBuilder();

            $nativeQb->select('m.id')
                ->from('modelo', 'm')
                ->leftJoin('m', 'fabricante', 'f', 'm.fabricante = f.id')
                ->leftJoin('m', 'modelo_modeloatributos', 'mma', 'm.id = mma.modelo_id')
                ->leftJoin('mma', 'modelo_atributo', 'ma', 'mma.modelo_atributo_id = ma.id')
                ->leftJoin('m', 'ext_translations', 't', "t.foreign_key = m.id AND t.object_class = :entityClass AND t.field = 'descripcion'")
                ->where('m.activo = 1')
                ->andWhere('m.precio_min > 0')
                ->groupBy('m.id');

            $nativeQb->setParameter('entityClass', Modelo::class);

            $keywords = explode(' ', $query);
            $keywordsExpr = $nativeQb->expr()->andX();

            foreach ($keywords as $index => $keyword) {
                if (!empty($keyword)) {
                    $keyParam = 'search_key_' . $index;
                    $keywordsExpr->add($nativeQb->expr()->orX(
                        'm.referencia LIKE :' . $keyParam,
                        'm.nombre LIKE :' . $keyParam,
                        'f.nombre LIKE :' . $keyParam,
                        'ma.nombre LIKE :' . $keyParam,
                        'ma.valor LIKE :' . $keyParam
                    ));
                    $nativeQb->setParameter($keyParam, '%' . $keyword . '%');
                }
            }

            $descOr = $nativeQb->expr()->orX(
                'm.descripcion LIKE :fullQuery',
                't.content LIKE :fullQuery'
            );
            $nativeQb->setParameter('fullQuery', '%' . $query . '%');

            if ($keywordsExpr->count() > 0) {
                $nativeQb->andWhere($nativeQb->expr()->orX($keywordsExpr, $descOr));
            } else {
                $nativeQb->andWhere($descOr);
            }

            $idsEncontrados = $nativeQb->executeQuery()->fetchFirstColumn();

            if (!empty($idsEncontrados)) {
                $qb->andWhere('m.id IN (:searchIds)')
                    ->setParameter('searchIds', $idsEncontrados);
            } else {
                $qb->andWhere('1=0');
            }
        }

        if ($filtros->getCategory()) {
            $idsCategorias = [$filtros->getCategory()->getId()];
            foreach ($filtros->getCategory()->getChildren() as $hijo) { $idsCategorias[] = $hijo->getId(); }
            $qb->leftJoin('m.category', 'cat') // Cambio alias 'c' a 'cat' por seguridad
            ->andWhere($qb->expr()->in('cat.id', ':idsCategorias'))
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
            case "Orden Por Defecto": $qb->addOrderBy('m.importancia', 'DESC')->addOrderBy('m.precioMinAdulto', 'ASC'); break;
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

    private function findModelosIdsByAtributos(array $atributosIds): array
    {
        if (empty($atributosIds)) return [];

        $sql = "SELECT modelo_id FROM modelo_modeloatributos WHERE modelo_atributo_id IN (:atributos) GROUP BY modelo_id HAVING COUNT(DISTINCT modelo_atributo_id) = :count";
        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery($sql, [
            'atributos' => $atributosIds,
            'count' => count($atributosIds)
        ], [
            'atributos' => Connection::PARAM_INT_ARRAY
        ]);

        return $result->fetchFirstColumn();
    }

    public function findDestacadosParaHome(): array
    {
        // Versión optimizada también para la home
        return $this->createQueryBuilder('m')
            ->addSelect('i') // Traemos la imagen
            ->leftJoin('m.imagen', 'i')
            ->where('m.activo = :activo')
            ->andWhere('m.destacado = :destacado')
            ->orderBy('m.importancia', 'DESC')
            ->setParameter('activo', true)
            ->setParameter('destacado', true)
            ->setMaxResults(12) // Importante limitar
            ->getQuery()->getResult();
    }

    /**
     * Búsqueda rápida para el Live Search (AJAX).
     * CORREGIDO: Soluciona el error de ExpressionBuilder::or() usando andX() / orX()
     */
    public function findByLiveSearch(string $query, int $limit = 10): array
    {
        if (empty($query) || strlen($query) < 3) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        $qb = $conn->createQueryBuilder();

        $qb->select('m.id')
            ->from('modelo', 'm')
            ->leftJoin('m', 'fabricante', 'f', 'm.fabricante = f.id')
            ->leftJoin('m', 'modelo_modeloatributos', 'mma', 'm.id = mma.modelo_id')
            ->leftJoin('mma', 'modelo_atributo', 'ma', 'mma.modelo_atributo_id = ma.id')
            // JOIN con traducciones (Gedmo Translatable)
            ->leftJoin('m', 'ext_translations', 't', "t.foreign_key = m.id AND t.object_class = :entityClass AND t.field = 'descripcion'")
            ->where('m.activo = 1')
            ->andWhere('m.precio_min > 0')
            ->groupBy('m.id')
            ->setMaxResults($limit);

        $qb->setParameter('entityClass', Modelo::class);

        $keywords = explode(' ', $query);

        // CORRECCIÓN 1: Usamos andX() para el contenedor principal.
        // Esto significa: (Palabra1 EN campos) Y (Palabra2 EN campos)...
        // Usamos andX() porque permite iniciarse vacío, a diferencia de or().
        $keywordsExpr = $qb->expr()->andX();

        foreach ($keywords as $index => $keyword) {
            if (!empty($keyword)) {
                $keyParam = 'keyword_' . $index;

                // CORRECCIÓN 2: Usamos orX() para las condiciones internas
                // Esto significa: (Nombre TIENE palabra O Ref TIENE palabra O ...)
                $keywordsExpr->add($qb->expr()->orX(
                    'm.referencia LIKE :' . $keyParam,
                    'm.nombre LIKE :' . $keyParam,
                    'f.nombre LIKE :' . $keyParam,
                    'ma.nombre LIKE :' . $keyParam,
                    'ma.valor LIKE :' . $keyParam
                ));
                $qb->setParameter($keyParam, '%' . $keyword . '%');
            }
        }

        // Lógica de Descripción (Frase exacta)
        // CORRECCIÓN 3: Usamos orX() aquí también por consistencia y seguridad
        $descOr = $qb->expr()->orX(
            'm.descripcion LIKE :fullQuery',
            't.content LIKE :fullQuery'
        );
        $qb->setParameter('fullQuery', '%' . $query . '%');

        // COMBINACIÓN FINAL: (Keywords Coinciden) O (Descripción Coincide)
        if ($keywordsExpr->count() > 0) {
            // Si hay palabras clave, buscamos: (Todas las palabras coinciden) O (La frase está en la descripción)
            $qb->andWhere($qb->expr()->orX($keywordsExpr, $descOr));
        } else {
            $qb->andWhere($descOr);
        }

        // Ordenación
        $qb->orderBy('m.importancia', 'DESC')
            ->addOrderBy('m.nombre', 'ASC');

        // Ejecutar y obtener IDs
        $ids = $qb->executeQuery()->fetchFirstColumn();

        if (empty($ids)) {
            return [];
        }

        // Hidratar objetos reales con Doctrine
        $modelos = $this->createQueryBuilder('m')
            ->where('m.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        // Reordenar en PHP
        $idMap = array_flip($ids);
        usort($modelos, function($a, $b) use ($idMap) {
            return $idMap[$a->getId()] <=> $idMap[$b->getId()];
        });

        return $modelos;
    }
}