<?php

namespace App\Block;

// AHORA: Se importa la entidad Contacto
use App\Entity\Contacto;
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

class ClientesRecientesBlockService extends AbstractBlockService
{
    protected EntityManagerInterface $entityManager;
    protected AdminPool $adminPool;

    // AHORA: Constructor moderno con autowiring
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
            'title'      => 'Clientes Recientes',
            // AHORA: Nueva ruta de plantilla
            'template'   => 'block/clientes_recientes.html.twig'
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
        // AHORA: Se usa el FQCN de la entidad Contacto
        $clientes = $this->entityManager->getRepository(Contacto::class)->findBy(
            [], // Sin criterios
            ['id' => 'DESC'], // Ordenados por ID descendente
            $blockContext->getSetting('number') // Límite
        );

        // AHORA: Obtenemos el Admin de Contacto para pasarlo a la vista
        $contactoAdmin = $this->adminPool->getAdminByClass(Contacto::class);

        return $this->renderResponse($blockContext->getTemplate(), [
            'context'   => $blockContext,
            'settings'  => $blockContext->getSettings(),
            'block'     => $blockContext->getBlock(),
            'customers' => $clientes,
            // ¡Nueva variable para la vista!
            'contacto_admin' => $contactoAdmin,
        ], $response);
    }
}