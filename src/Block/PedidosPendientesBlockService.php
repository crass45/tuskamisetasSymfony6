<?php

// AHORA: El namespace sigue la estructura de directorios de 'src/'
namespace App\Block;

// AHORA: Se importan las clases modernas y se usa el alias 'TwigEnvironment' para evitar conflictos.
use App\Entity\Pedido;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Form\Type\ImmutableArrayType;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService;
use Sonata\BlockBundle\Form\Mapper\FormMapper;
use Sonata\BlockBundle\Model\BlockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment as TwigEnvironment;
use Sonata\AdminBundle\Admin\Pool as AdminPool;

class PedidosPendientesBlockService extends AbstractBlockService
{
    // AHORA: Tipamos la propiedad para mayor claridad y robustez.
    protected EntityManagerInterface $entityManager;
// AÑADIR ESTA PROPIEDAD
    protected AdminPool $adminPool;

    // AÑADIR AdminPool AL CONSTRUCTOR
    public function __construct(TwigEnvironment $twig, EntityManagerInterface $entityManager, AdminPool $adminPool)
    {
        $this->entityManager = $entityManager;
        // AÑADIR ESTA LÍNEA
        $this->adminPool = $adminPool;
        parent::__construct($twig);
    }

    /**
     * {@inheritdoc}
     * Esta función es prácticamente idéntica. Solo cambia la ruta de la plantilla.
     */
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'number'     => 15,
            'mode'       => 'public',
            'title'      => 'PEDIDOS PAGADOS SIN ENVIAR',
            // AHORA: La nueva sintaxis de plantillas.
            'template'   => 'block/pedidos_pendientes.html.twig'
        ]);
    }

    /**
     * {@inheritdoc}
     * El único cambio es cómo se referencia el tipo de formulario.
     */
    public function buildEditForm(FormMapper $form, BlockInterface $block): void
    {
        $form->add('settings', ImmutableArrayType::class, [ // AHORA: Se usa el FQCN de la clase
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

    /**
     * {@inheritdoc}
     * El cambio clave aquí es cómo se obtiene el repositorio de Doctrine.
     */
    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        // ANTES: $this->manager->getRepository('SSTiendaBundle:Pedido')
        // AHORA: Se usa el FQCN de la entidad. ¡Mucho más limpio y a prueba de refactorización!
        $pedidos = $this->entityManager->getRepository(Pedido::class)
            ->createQueryBuilder('q')
            ->where("q.fechaEntrega is not null AND q.estado > 2 AND q.estado != 11")
            ->orderBy("q.fechaEntrega")
            ->getQuery()
            ->getResult();
        $pedidoAdmin = $this->adminPool->getAdminByClass(Pedido::class);

        return $this->renderResponse($blockContext->getTemplate(), [
            'context'   => $blockContext,
            'settings'  => $blockContext->getSettings(),
            'block'     => $blockContext->getBlock(),
            'orders'    => $pedidos,
            'pedido_admin' => $pedidoAdmin,
        ], $response);
    }

    /*
     * ANTES: Tenías un método getName().
     * AHORA: Este método está obsoleto y debe ser eliminado.
     * El "nombre" o identificador del bloque ahora es su FQCN (App\Block\PedidosPendientesBlockService).
     */
}