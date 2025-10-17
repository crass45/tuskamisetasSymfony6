<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
final class BannerHomeAdmin extends AbstractAdmin
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
            ->add('orden', IntegerType::class)
            ->add('titulo', TextType::class)
            ->add('subtitulo', TextType::class)
            ->add('texto', TextareaType::class, [
                'required' => false,
                'attr' => ['class' => 'tinymce']
            ])
            ->add('url', UrlType::class)
            ->add('activo', CheckboxType::class, [
                'required' => false,
            ])
            ->add('imagen', ModelListType::class, [
                'required' => false,
                'btn_add' => 'Añadir Imagen',
                'btn_list' => 'Seleccionar Imagen',
                'btn_delete' => false,
            ], [
                'link_parameters' => $link_parameters
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('titulo', StringFilter::class)
            ->add('subtitulo', StringFilter::class);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('orden', null, [
                'editable' => true,
            ])
            ->addIdentifier('titulo')
            ->add('texto', null, [
                'safe' => true,
            ])
            ->add('activo', null, [
                'editable' => true,
            ]);
    }
}