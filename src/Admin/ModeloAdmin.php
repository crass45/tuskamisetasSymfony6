<?php

namespace App\Admin;


use App\Entity\Modelo;
use App\Entity\Sonata\ClassificationCategory;
use Doctrine\ORM\EntityRepository;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\Form\Type\CollectionType;
use Sonata\MediaBundle\Twig\Extension\MediaExtension;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class ModeloAdmin extends AbstractAdmin
{
    private MediaExtension $mediaExtension;

    public function __construct(MediaExtension $mediaExtension)
    {
        parent::__construct();
        $this->mediaExtension = $mediaExtension;
    }

    protected function configureFormFields(FormMapper $form): void
    {
        /** @var Modelo|null $modelo */
        $modelo = $this->getSubject();
        $imagen = '';

        // Lógica para mostrar la vista previa de la imagen
        if ($modelo && $modelo->getImagen()) {
            $format = 'big'; // O el formato que prefieras de tu sonata_media.yaml
            $webPath = $this->mediaExtension->path($modelo->getImagen(), $format);
            $imagen = '<img src="' . $webPath . '" class="admin-preview" style="max-width: 300px;"/>';
        }

        $form
            ->tab('Información')
            ->with('Datos', ['class' => 'col-md-8'])
            ->add('referencia', TextType::class)
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
            // Ya no usamos TranslationsType. Añadimos los campos directamente.
            // SonataTranslationBundle creará las pestañas de idioma automáticamente.
            ->add('tituloSEO', CKEditorType::class, [
                'label' => 'Título SEO',
                'required' => false,
            ])
            ->add('descripcionSEO', CKEditorType::class, [
                'label' => 'Descripción SEO',
                'required' => false,
            ])
            ->add('descripcion', CKEditorType::class, [
                'label' => 'Descripción',
                'required' => false,
            ])
            ->add('descripcionTusKamisetas', CKEditorType::class, [
                'label' => 'Descripción Tuskamisetas (no se borra cuando actualiza producto)',
                'required' => false,
            ])
            ->end()
            ->end()
            ->tab('Atributos y Relaciones')
            ->with('Atributos')
            ->add('atributos', ModelType::class, [
                'multiple' => true,
                'expanded' => false, // Renderiza como checkboxes
                'by_reference' => false,
            ])
            ->end()
            ->with('Modelos Relacionados')
//            ->add('modelosRelacionados', ModelType::class, [
//                'multiple' => true,
//                'by_reference' => false,
//                'required' => false,
//                // Aquí podrías añadir una consulta personalizada si la lista es muy grande
//            ])
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
            ->end()
        ;
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
    }

    protected function preUpdate(object $object): void
    {
        if (!$object instanceof Modelo) {
            return;
        }

        // Lógica para actualizar las medidas de las tallas dinámicamente
        $slugger = new AsciiSlugger();
        $tallas = $object->getTallas();
        foreach ($tallas as $talla) {
            $fieldName = (string) $slugger->slug($talla)->lower();
            if ($this->getForm()->has($fieldName)) {
                $fieldData = $this->getForm()->get($fieldName)->getData();
                // Aquí necesitarías una lógica para encontrar el producto específico y actualizarlo
                // $producto = findProductByTalla($talla);
                // $producto->setMedidas($fieldData);
            }
        }

        // ... (resto de la lógica preUpdate)
    }


    protected function configureTabMenu(MenuItemInterface $menu, string $action, ?AdminInterface $childAdmin = null): void
    {
//        if ('edit' !== $action) {
//            return;
//        }
//
//        $admin = $this->isChild() ? $this->getParent() : $this;
//        $id = $admin->getRequest()->get('id');
//
//        if (!$id) {
//            return;
//        }
//
//        // Obtenemos el Admin de Productos para generar la URL
//        $productoAdmin = $this->getConfigurationPool()->getAdminByAdminCode('App\Admin\ProductoAdmin');
//
//        $menu->addChild('Variaciones (Productos)', [
//            'uri' => $productoAdmin->generateUrl('list', ['filter' => ['modelo' => ['value' => $id]]])
//        ]);


        if (!$childAdmin && !in_array($action, ['edit', 'show'])) {
            return;
        }

        $admin = $this->isChild() ? $this->getParent() : $this;
        $id = $admin->getRequest()->get('id');

//        $menu->addChild('View Playlist', $admin->generateMenuUrl('show', ['id' => $id]));
//
        if ($this->isGranted('EDIT')) {
            $menu->addChild('Modelo', $admin->generateMenuUrl('edit', ['id' => $id]));
        }

        if ($this->isGranted('LIST')) {
            $menu->addChild('Variaciones', $admin->generateMenuUrl('App\Admin\ProductoAdmin.list', ['id' => $id]));
        }
    }
}