<?php

namespace App\Block;

use App\Entity\Pedido;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Admin\Pool as AdminPool;
use Sonata\AdminBundle\Form\Type\ImmutableArrayType;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService;
use Sonata\BlockBundle\Form\Mapper\FormMapper;
use Sonata\BlockBundle\Model\BlockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment as TwigEnvironment;

class PedidosRecientesBlockService extends AbstractBlockService
{
    protected EntityManagerInterface $entityManager;
    protected AdminPool $adminPool;

    public function __construct(TwigEnvironment $twig, EntityManagerInterface $entityManager, AdminPool $adminPool)
    {
        $this->entityManager = $entityManager;
        $this->adminPool = $adminPool;
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'number'     => 15,
            'mode'       => 'public',
            'title'      => 'Presupuestos Recientes',
            // AHORA: Nueva ruta de plantilla
            'template'   => 'block/pedidos_recientes.html.twig'
        ]);
    }

    public function buildEditForm(FormMapper $form, BlockInterface $block): void
    {
        $form->add('settings', ImmutableArrayType::class, [
            'keys' => [
                ['number', 'integer', ['required' => true]],
                ['title', 'text', ['required' => false]],
                ['mode', 'choice', [
                    'choices' => [
                        'public' => 'public',
                        'admin'  => 'admin'
                    ]
                ]]
            ]
        ]);
    }

    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        // AHORA: Usamos el FQCN de la entidad Pedido para obtener los pedidos
        $pedidos = $this->entityManager->getRepository(Pedido::class)->findBy(
            [], // Sin criterios de búsqueda
            ['fecha' => 'DESC'], // Ordenados por fecha descendente
            $blockContext->getSetting('number') // Límite de resultados
        );

        // Pasamos el Admin a la plantilla para generar las URLs
        $pedidoAdmin = $this->adminPool->getAdminByClass(Pedido::class);

        return $this->renderResponse($blockContext->getTemplate(), [
            'context'      => $blockContext,
            'settings'     => $blockContext->getSettings(),
            'block'        => $blockContext->getBlock(),
            'orders'       => $pedidos,
            'pedido_admin' => $pedidoAdmin,
        ], $response);
    }
}