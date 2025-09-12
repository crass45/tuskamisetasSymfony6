<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

final class EmpresaHasMediaAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $link_parameters = [];

        if ($this->hasParentFieldDescription()) {
            $link_parameters = $this->getParentFieldDescription()->getOption('link_parameters', []);
        }

        if ($this->hasRequest()) {
            $context = $this->getRequest()->get('context');

            if (null !== $context) {
                $link_parameters['context'] = $context;
            }
        }

        $form
            ->add('media', ModelListType::class, [
                'required' => true, // La relación debe tener una imagen
                'btn_add' => 'Añadir Media',
                'btn_list' => 'Seleccionar',
                'btn_delete' => false,
            ], [
                'link_parameters' => $link_parameters
            ])
            ->add('position', HiddenType::class); // Campo de posición para ordenar
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            // NOTA: Renombrado 'gallery' a 'empresa' para coincidir con la entidad
            ->add('empresa')
            ->addIdentifier('media');
    }
}