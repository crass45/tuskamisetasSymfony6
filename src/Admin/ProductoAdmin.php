<?php

namespace App\Admin;

use App\Entity\Producto;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class ProductoAdmin extends AbstractAdmin
{
    protected function configure(): void
    {
        $this->parentAssociationMapping = 'modelo';
    }

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
            ->add('color', ModelType::class, [
                'choice_label' => 'nombre'
            ])
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
            ->add('precioUnidad', 'currency', ['currency' => 'EUR']);
    }

    protected function prePersist(object $object): void
    {
        if (!$object instanceof Producto) {
            return;
        }

        // Lógica para generar la referencia, ahora de forma segura
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
        // Actualiza el precio mínimo en el modelo padre
        $object->getModelo()?->setPrecioMin($object->getPrecioMin());
    }
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        if ($this->isChild()) {
            return;
        }

        // This is the route configuration as a parent
        $collection->clear();

    }

}