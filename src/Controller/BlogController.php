<?php
// src/Controller/BlogController.php

namespace App\Controller;

use App\Entity\Publicacion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}', requirements: ['_locale' => 'es|en|fr'])]
class BlogController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    #[Route('/publicaciones/{page}', name: 'app_blog_list', defaults: ['page' => 1], requirements: ['page' => '\d+'])]
    public function listAction(int $page = 1): Response
    {
        $limit = 25;
        $offset = ($page - 1) * $limit;
        $repository = $this->em->getRepository(Publicacion::class);

        $publications = $repository->findBy(['activo' => true], ['fecha' => 'DESC'], $limit, $offset);
        $totalPublications = $repository->count(['activo' => true]);
        $totalPages = ceil($totalPublications / $limit);

        return $this->render('web/blog/blog_list.html.twig', [
            'publicaciones' => $publications,
            'totalPages' => $totalPages,
            'currentPage' => $page,
        ]);
    }

    // --- MÉTODO AÑADIDO ---
    /**
     * URL final: /es/publicacion/el-slug-de-la-publicacion
     */
    #[Route('/publicacion/{slug}', name: 'app_blog_detail')]
    public function detailAction(string $slug): Response
    {
        // MIGRACIÓN: Se usa el nuevo nombre de propiedad 'nombreUrl'
        $publicacion = $this->em->getRepository(Publicacion::class)->findOneBy(['nombreUrl' => $slug, 'activo' => true]);

        if (!$publicacion) {
            throw $this->createNotFoundException('La publicación no existe o no está activa.');
        }

        return $this->render('web/blog/blog_detalle.html.twig', [
            'publicacion' => $publicacion,
        ]);
    }
}

