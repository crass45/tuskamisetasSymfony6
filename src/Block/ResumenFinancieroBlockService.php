<?php

namespace App\Block;

use App\Entity\Contacto;
use App\Entity\Pedido;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment as TwigEnvironment;

class ResumenFinancieroBlockService extends AbstractBlockService
{
    protected EntityManagerInterface $entityManager;

    public function __construct(TwigEnvironment $twig, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'title'    => 'Resumen Financiero',
            'template' => 'block/resumen_financiero.html.twig',
        ]);
    }

    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        $pedidoRepo = $this->entityManager->getRepository(Pedido::class);
        $userRepo = $this->entityManager->getRepository(Contacto::class);

        // --- CAMBIO: La consulta ahora une Contacto (c) con User (u) ---
        $adminsQuery = $this->entityManager->getRepository(Contacto::class)->createQueryBuilder('c')
            ->select('c.id')
            // Hacemos un JOIN desde Contacto a su propiedad "usuario"
            ->join('c.usuario', 'u')
            // Y ahora sí, filtramos por el campo "roles" de la entidad User (alias 'u')
            ->where('u.roles LIKE :role_super_admin')
            ->orWhere('u.roles LIKE :role_admin')
            ->setParameter('role_super_admin', '%ROLE_SUPER_ADMIN%')
            ->setParameter('role_admin', '%ROLE_ADMIN%')
            ->getQuery();

        $adminIds = array_column($adminsQuery->getResult(), 'id');
        if (empty($adminIds)) {
            $adminIds = [0];
        }


        // --- 2. Ventas de Hoy ---
        $hoyInicio = new \DateTime('today');
        $hoyFin = new \DateTime('tomorrow');
        $ventasHoy = $pedidoRepo->createQueryBuilder('p')
            ->select('SUM(p.total) as total')
            ->where('p.fecha >= :inicio')
            ->andWhere('p.fecha < :fin')
            // --- CAMBIO: Condición de pedido pagado ---
            ->andWhere('p.cantidadPagada > 0')
            // --- CAMBIO: Excluimos los pedidos de los administradores ---
            ->andWhere('p.contacto NOT IN (:adminIds)')
            ->setParameter('inicio', $hoyInicio)
            ->setParameter('fin', $hoyFin)
            ->setParameter('adminIds', $adminIds) // Pasamos el array de IDs
            ->getQuery()
            ->getSingleScalarResult();

        // --- 3. Ventas del Mes ---
        $mesInicio = new \DateTime('first day of this month 00:00:00');
        $mesFin = new \DateTime('first day of next month 00:00:00');
        $queryVentasMes = $pedidoRepo->createQueryBuilder('p')
            ->select('SUM(p.total) as total, COUNT(p.id) as num_pedidos')
            ->where('p.fecha >= :inicio')
            ->andWhere('p.fecha < :fin')
            // --- CAMBIO: Condición de pedido pagado ---
            ->andWhere('p.cantidadPagada > 0')
            // --- CAMBIO: Excluimos los pedidos de los administradores ---
            ->andWhere('p.contacto NOT IN (:adminIds)')
            ->setParameter('inicio', $mesInicio)
            ->setParameter('fin', $mesFin)
            ->setParameter('adminIds', $adminIds) // Pasamos el array de IDs
            ->getQuery()
            ->getSingleResult();

        $ventasMes = $queryVentasMes['total'] ?? 0;
        $numPedidosMes = (int) ($queryVentasMes['num_pedidos'] ?? 0);

        // --- 4. Ticket Medio ---
        $ticketMedio = ($numPedidosMes > 0) ? ($ventasMes / $numPedidosMes) : 0;

        return $this->renderResponse($blockContext->getTemplate(), [
            'block'       => $blockContext->getBlock(),
            'settings'    => $blockContext->getSettings(),
            'ventas_hoy'  => $ventasHoy ?? 0,
            'ventas_mes'  => $ventasMes,
            'ticket_medio' => $ticketMedio,
        ], $response);
    }
}