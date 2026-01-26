<?php

namespace App\Controller\Admin;

use App\Entity\ModeloHasTecnicasEstampado; // <--- Importante
use App\Entity\Personalizacion;
use Doctrine\ORM\Tools\Pagination\Paginator; // <--- Importante
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PersonalizacionCRUDController extends CRUDController
{
    public function verProductosAction(int|string $id, Request $request): Response
    {
        $personalizacion = $this->admin->getSubject();

        if (!$personalizacion) {
            throw $this->createNotFoundException(sprintf('No se encontró la personalización con id: %s', $id));
        }

        // --- LÓGICA DE PAGINACIÓN ---
        $limit = 20; // Productos por página
        $page = $request->query->getInt('page', 1);
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;

        // Usamos el EntityManager de Sonata para obtener el repositorio
        $em = $this->admin->getModelManager()->getEntityManager(ModeloHasTecnicasEstampado::class);

        // Creamos la consulta optimizada (Joins para no hacer lazy loading masivo)
        $qb = $em->getRepository(ModeloHasTecnicasEstampado::class)->createQueryBuilder('mt')
            ->leftJoin('mt.modelo', 'm')
            ->addSelect('m') // Traemos el modelo en la misma consulta
            ->leftJoin('mt.areas', 'a')
            ->addSelect('a') // Traemos las áreas en la misma consulta
            ->where('mt.personalizacion = :personalizacion')
            ->setParameter('personalizacion', $personalizacion)
            ->orderBy('m.referencia', 'ASC') // Ordenamos por referencia
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        // Usamos Paginator de Doctrine para obtener resultados y cuenta total eficientemente
        $paginator = new Paginator($qb, true);
        $totalItems = count($paginator);
        $pagesCount = ceil($totalItems / $limit);

        return $this->renderWithExtraParams('admin/personalizacion/ver_productos.html.twig', [
            'action' => 'ver_productos',
            'object' => $personalizacion,
            'relaciones' => $paginator, // Pasamos el paginador a la vista
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'pagesCount' => $pagesCount,
        ]);
    }
}