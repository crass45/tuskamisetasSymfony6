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

class PedidosComparativaBlockService extends AbstractBlockService
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
            'title'    => 'Comparativa Creados vs. Pagados (Últimos 12 meses)',
            'template' => 'block/pedidos_comparativa.html.twig',
        ]);
    }

    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        $adminIds = $this->getAdminIds();
        $startDate = new \DateTime('first day of this month -11 months 00:00:00');

        $results = $this->entityManager->getRepository(Pedido::class)->createQueryBuilder('p')
            ->select('p.fecha, p.cantidadPagada')
            ->where('p.fecha >= :startDate')
            ->andWhere('p.contacto NOT IN (:adminIds)')
            ->setParameter('startDate', $startDate)
            ->setParameter('adminIds', $adminIds)
            ->orderBy('p.fecha', 'ASC')
            ->getQuery()
            ->getResult();

        $chartData = [];

        // Inicializamos los datos para los últimos 12 meses
        for ($i = 0; $i < 12; $i++) {
            $date = (clone $startDate)->modify("+$i month");
            $key = $date->format('Y-m');
            $chartData[$key] = [
                'creados' => 0,
                'pagados' => 0,
                'label' => $this->getSpanishMonthName($date->format('n')) . ' ' . $date->format('y') // Nota 'y' minúscula para año de 2 dígitos
            ];
        }

        // Agrupamos los pedidos por mes
        foreach ($results as $pedido) {
            $key = $pedido['fecha']->format('Y-m');
            if (isset($chartData[$key])) {
                $chartData[$key]['creados']++;
                if ($pedido['cantidadPagada'] > 0) {
                    $chartData[$key]['pagados']++;
                }
            }
        }

        // --- CAMBIO: Calculamos el porcentaje y construimos las etiquetas finales ---
        $finalLabels = [];
        foreach ($chartData as $data) {
            if ($data['creados'] > 0) {
                // Calculamos el porcentaje y lo redondeamos a un decimal
                $porcentaje = round(($data['pagados'] / $data['creados']) * 100, 1);
                // Añadimos el porcentaje a la etiqueta
                $finalLabels[] = $data['label'] . " (" . $porcentaje . "%)";
            } else {
                // Si no hay pedidos creados, simplemente mostramos la etiqueta sin porcentaje
                $finalLabels[] = $data['label'] . " (0%)";
            }
        }

        return $this->renderResponse($blockContext->getTemplate(), [
            'block'       => $blockContext->getBlock(),
            'settings'    => $blockContext->getSettings(),
            'labels'      => json_encode($finalLabels), // Usamos las nuevas etiquetas con porcentaje
            'datos_creados' => json_encode(array_column($chartData, 'creados')),
            'datos_pagados' => json_encode(array_column($chartData, 'pagados')),
        ], $response);
    }

    private function getAdminIds(): array
    {
        $adminsQuery = $this->entityManager->getRepository(Contacto::class)->createQueryBuilder('c')
            ->select('c.id')
            ->join('c.usuario', 'u')
            ->where('u.roles LIKE :role_super_admin OR u.roles LIKE :role_admin')
            ->setParameter('role_super_admin', '%ROLE_SUPER_ADMIN%')
            ->setParameter('role_admin', '%ROLE_ADMIN%')
            ->getQuery();

        $adminIds = array_column($adminsQuery->getResult(), 'id');
        return !empty($adminIds) ? $adminIds : [0];
    }

    private function getSpanishMonthName(int $monthNumber): string
    {
        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return $meses[$monthNumber - 1];
    }
}