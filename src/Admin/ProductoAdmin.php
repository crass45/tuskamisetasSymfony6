<?php

namespace App\Admin;

use App\Entity\Color; // <--- AÑADIR ESTE IMPORT
use App\Entity\Producto;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType; // <--- AÑADIR ESTE IMPORT
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class ProductoAdmin extends AbstractAdmin
{
    // --- 1. ELIMINAMOS EL MÉTODO configure() QUE CAUSABA DEPRECATIONS ---
    // protected function configure(): void
    // {
    //    $this->parentAssociationMapping = 'modelo';
    // }

    protected function configureFormFields(FormMapper $form): void
    {
        $link_parameters = [];
        if ($this->hasRequest()) {
            $context = $this->getRequest()->get('context');
            if (null !== $context) {
                $link_parameters['context'] = $context;
            }
        }

        $form
            ->add('referencia', TextType::class, ['required' => false])
            ->add('talla', TextType::class)

            // --- 2. CAMBIO CRÍTICO: ModelType -> EntityType ---
            // Usamos EntityType para asegurar que Symfony trate los colores como Objetos
            ->add('color', EntityType::class, [
                'class' => Color::class,   // Definimos la clase explícitamente
                'choice_label' => 'nombre', // Propiedad a mostrar
                'placeholder' => 'Selecciona un color...',
                'required' => true // O false, según tu lógica
            ])
            // --------------------------------------------------

            ->add('precioUnidad', MoneyType::class, ['currency' => 'EUR'])
            ->add('precioPack', MoneyType::class, ['currency' => 'EUR'])
            ->add('precioCaja', MoneyType::class, ['currency' => 'EUR'])
            ->add('medidas', TextType::class, ['required' => false])
            ->add('urlImage', TextType::class, ['label' => 'URL de Imagen', 'required' => false])
            ->add('imagen', ModelListType::class, [
                'required' => false,
            ], [
                'link_parameters' => $link_parameters,
            ])
            ->add('activo', CheckboxType::class, ['required' => false]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        if (!$this->isChild()) {
            $datagrid->add('modelo', ModelFilter::class, [
                'field_options' => ['choice_label' => 'nombre']
            ]);
        }
        $datagrid
            ->add('talla')
            ->add('referencia')
            ->add('modelo.referencia', null, ['label' => 'Ref. Modelo'])
            ->add('modelo.familia', null, ['label' => 'Familia'])
            ->add('color.nombre', null, ['label' => 'Nombre del Color']);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('modelo', null, ['associated_property' => 'nombre'])
            ->addIdentifier('referencia')
            ->add('talla')
            ->add('color', null, ['associated_property' => 'nombre'])
            ->add('activo', null, ['editable' => true])
            ->add('stock')
            ->add('precioCaja', 'currency', ['currency' => 'EUR','label' => 'Precio Compra']);
    }

    protected function prePersist(object $object): void
    {
        if (!$object instanceof Producto) {
            return;
        }

        // Lógica para generar la referencia
        $refModelo = $object->getModelo()?->getReferencia() ?? '';
        $talla = $object->getTalla() ?? '';
        $nombreUrlColor = $object->getColor()?->getNombreUrl() ?? '';
        $object->setReferencia($refModelo . $talla . strtoupper($nombreUrlColor));

        $object->getModelo()?->setPrecioMin($object->getPrecioMin());
    }

    protected function preUpdate(object $object): void
    {
        if (!$object instanceof Producto) {
            return;
        }
        $object->getModelo()?->setPrecioMin($object->getPrecioMin());
    }

    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        if ($this->isChild()) {
            return;
        }
        $collection->clear();
    }
}