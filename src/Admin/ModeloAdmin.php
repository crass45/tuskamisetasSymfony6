<?php

namespace App\Admin;


use App\Entity\Modelo;
use App\Entity\Sonata\ClassificationCategory;
use Doctrine\ORM\EntityRepository;
use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\Form\Type\CollectionType;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Sonata\AdminBundle\Route\RouteCollectionInterface;

final class ModeloAdmin extends AbstractAdmin
{
    private Pool $mediaExtension;

    public function __construct(Pool $mediaExtension)
    {
        parent::__construct();
        $this->mediaExtension = $mediaExtension;
    }

    protected function configureFormFields(FormMapper $form): void
    {
        /** @var Modelo|null $modelo */
        $modelo = $this->getSubject();
        $imagen = '';

        if ($modelo && $modelo->getImagen()) {
            $format = 'reference';
            $provider = $this->mediaExtension->getProvider($modelo->getImagen()->getProviderName());//path($modelo->getImagen(), $format);
            $webPath = $provider->generatePublicUrl($modelo->getImagen(), $format);
            $imagen = '<img src="' . $webPath . '" class="admin-preview" style="max-width: 300px;"/>';
        }

        $form
            ->tab('Información')
            ->with('Datos', ['class' => 'col-md-8'])
            ->add('referencia', TextType::class)
            ->add('importancia')
            ->add('nombre', TextType::class)
            ->add('proveedor', ModelType::class)
            ->add('fabricante', ModelType::class)
            ->add('category', EntityType::class, [
                'class' => ClassificationCategory::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')->where('c.parent IS NOT NULL');
                },
                'multiple' => true,
                'by_reference' => false,
                'label' => 'Categorías de Sonata',
                'required' => false
            ])
            ->add('composicion', TextType::class, ['required' => false])
            ->add('pack', IntegerType::class, ['required' => false])
            ->add('box', IntegerType::class, ['label' => 'Unidades por Caja', 'required' => false])
            ->add('obligadaVentaEnPack', CheckboxType::class, ['label' => 'Venta en Pack Obligatoria', 'required' => false])
            ->add('descuentoEspecial', NumberType::class)
            ->add('acumulaTotal', CheckboxType::class, ['required' => false])
            ->add('familia', ModelType::class, ['label' => 'Familia Principal', 'required' => false])
            ->add('gender', ModelType::class, ['label' => 'Género', 'required' => false])
            ->add('isForChildren', CheckboxType::class, ['label' => 'Es para Niños', 'required' => false])
            ->add('isNovelty', CheckboxType::class, ['label' => 'Es Novedad', 'required' => false])
            ->add('activo', CheckboxType::class, ['required' => false])
            ->add('destacado', CheckboxType::class, ['required' => false])
            ->end()
            ->with('Media', ['class' => 'col-md-4'])
            ->add('urlImage')
            ->add('imagen', ModelListType::class, [
                'required' => false,
                'help' => $imagen,
                'help_html' => true
            ])
            ->add('fichaTecnica', ModelListType::class, ['required' => false])
            ->add('urlFichaTecnica', TextType::class, ['required' => false])
            ->end()
            ->end()
            ->tab('Traducciones y SEO')
            ->with('Contenidos por Idioma')
            // --> CAMBIO 2: Reemplazamos todos los CKEditorType
            ->add('tituloSEO', TextareaType::class, [
                'label' => 'Título SEO',
                'required' => false
            ])
            ->add('descripcionSEO', TextareaType::class, [
                'label' => 'Descripción SEO',
                'required' => false
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => ['class' => 'tinymce']
            ])
            ->add('descripcionTusKamisetas', TextareaType::class, [
                'label' => 'Descripción Tuskamisetas (no se borra cuando actualiza producto)',
                'required' => false,
                'attr' => ['class' => 'tinymce']
            ])
            ->end()
            ->end()
            ->tab('Atributos y Relaciones')
            ->with('Atributos')
            ->add('atributos', ModelType::class, [
                'multiple' => true,
                'expanded' => false,
                'by_reference' => false,
            ])
            ->end()
            ->with('Modelos Relacionados')
            ->end()
            ->end()
            ->tab('Técnicas y Tarifas')
            ->with('Tarifa')
            ->add('tarifa', ModelType::class, ['required' => false])
            ->end()
            ->with('Técnicas de Estampado')
            ->add('tecnicas', CollectionType::class, [
                'by_reference' => false,
            ], [
                'edit' => 'inline',
                'inline' => 'table',
            ])
            ->end()
            ->end();
        // --- FUNCIONALIDAD DE ACTUALIZACIÓN MASIVA DE MEDIDAS ---
        if ($modelo && $modelo->getId()) {
            $slugger = new AsciiSlugger();
            $tallas = $modelo->getTallas(); // Array de strings ['S', 'M', 'L'...]

            // Solo pintamos la pestaña si hay tallas
            if (!empty($tallas)) {
                $form
                    ->tab('Gestión de Tallas')
                    ->with('Actualizar Medidas por Talla', [
                        'class' => 'col-md-12',
                        'description' => '<div class="alert alert-info" style="margin-bottom:15px;"><i class="fa fa-info-circle"></i> Escribe las medidas aquí (ej: 50/70) y guarda el modelo. Se actualizarán automáticamente en <b>todas</b> las variaciones de color de esa talla.</div>'
                    ]);

                foreach ($tallas as $talla) {
                    // Buscamos un valor actual para previsualizar
                    $valorActual = '';
                    foreach ($modelo->getProductos() as $producto) {
                        if ($producto->getTalla() === $talla) {
                            $valorActual = $producto->getMedidas();
                            break;
                        }
                    }

                    // Creamos un nombre de campo único y seguro para el formulario
                    // Ej: "medida_xl", "medida_3_4_anos"
                    $fieldName = 'medida_' . str_replace(['.', '/'], '_', $slugger->slug($talla)->lower());

                    $form->add($fieldName, TextType::class, [
                        'label' => 'Medidas Talla ' . $talla,
                        'mapped' => false, // ¡Importante! No existe en la entidad Modelo
                        'required' => false,
                        'data' => $valorActual,
                        'attr' => ['placeholder' => 'Ej: 54/72 cm'],
                        'help' => 'Ancho / Alto'
                    ]);
                }

                $form->end()->end();
            }
        }
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('referencia')
            ->add('nombre')
            ->add('familia')
            ->add('proveedor')
            ->add('fabricante')
            ->add('category', null, ['label' => 'Categoría'])
            ->add('activo');
    }

    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        // Añadimos la ruta que apunta a nuestra acción personalizada
        $collection->add('aplicar_tecnicas_tuskamisetas', $this->getRouterIdParameter() . '/aplicar-tecnicas-tuskamisetas');
    }

    protected function configureActionButtons(array $buttonList, string $action, ?object $object = null): array
    {
        // Solo mostramos el botón si estamos editando y existe el objeto
        if ($action === 'edit' && $object) {
            $buttonList['aplicar_tecnicas'] = [
                'template' => 'admin/CRUD/button_aplicar_tecnicas_edit.html.twig',
            ];
        }

        return $buttonList;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('referencia')
            ->add('imagen', 'string', ['template' => '@SonataMedia/MediaAdmin/list_image.html.twig'])
            ->add('nombre')
            ->add('activo', null, ['editable' => true])
            ->add('proveedor')
            ->add('fabricante')
            ->add('familia')
            ->add('precioMin', null, ['label' => 'Precio Mín.']);
//            ->add(ListMapper::NAME_ACTIONS, null, [
//                'actions' => [
//                    'show' => [],
//                    'edit' => [],
//                    'delete' => [],
//                    // ... tus otros botones ...
//
//                    // --- NUEVO BOTÓN ---
//                    'aplicar_tecnicas' => [
//                        'template' => 'admin/CRUD/list__action_aplicar_tecnicas.html.twig',
//                    ],
//                ],
//            ]);
    }

    protected function preUpdate(object $object): void
    {
        if (!$object instanceof Modelo) {
            return;
        }

        // 1. Lógica existente del Slug (la mantenemos)
        /* (Tu código original aquí si tenías algo, aunque el slug se genera en la entidad normalmente) */

        // 2. Lógica de ACTUALIZACIÓN DE MEDIDAS
        $slugger = new AsciiSlugger();
        $tallas = $object->getTallas();

        // Obtenemos el formulario para poder leer los campos "extra" (no mapeados)
        $form = $this->getForm();

        foreach ($tallas as $talla) {
            // Reconstruimos el nombre del campo tal cual lo definimos en configureFormFields
            $fieldName = 'medida_' . str_replace('.', '_', $slugger->slug($talla)->lower());

            // Si el campo existe en el formulario enviado
            if ($form->has($fieldName)) {
                $nuevaMedida = $form->get($fieldName)->getData();

                // Si hay un valor (incluso vacío para borrar), actualizamos los productos
                if ($nuevaMedida !== null) {
                    foreach ($object->getProductos() as $producto) {
                        // Si la talla coincide, actualizamos su medida
                        if ($producto->getTalla() === $talla) {
                            $producto->setMedidas($nuevaMedida);
                        }
                    }
                }
            }
        }
    }


    protected function configureTabMenu(MenuItemInterface $menu, string $action, ?AdminInterface $childAdmin = null): void
    {
        if (!$childAdmin && !in_array($action, ['edit', 'show'])) {
            return;
        }

        $admin = $this->isChild() ? $this->getParent() : $this;
        $id = $admin->getRequest()->get('id');

        if ($this->isGranted('EDIT')) {
            $menu->addChild('Modelo', $admin->generateMenuUrl('edit', ['id' => $id]));
        }

        if ($this->isGranted('LIST')) {
            $menu->addChild('Variaciones', $admin->generateMenuUrl('App\Admin\ProductoAdmin.list', ['id' => $id]));
        }
    }
}