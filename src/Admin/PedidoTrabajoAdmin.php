<?php

namespace App\Admin;

use App\Entity\Personalizacion;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
// use Symfony\Component\Form\Extension\Core\Type\UrlType; // Lo cambiamos por TextType para ser más flexibles

final class PedidoTrabajoAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        // 1. Recuperamos el objeto que se está editando
        $trabajo = $this->getSubject();

        // 2. Generamos el HTML del botón dinámicamente
        $botonVerImagen = '';
        if ($trabajo && $trabajo->getId() && $trabajo->getUrlImagen()) {
            $valor = $trabajo->getUrlImagen();
            $ruta = $valor;

            // Si no empieza por http, asumimos que es un fichero local en /uploads/gallery/
            if (!str_starts_with($valor, 'http')) {
                $ruta = '/uploads/design/' . $valor;
            }

            $botonVerImagen = sprintf(
                '<a href="%s" target="_blank" class="btn btn-sm btn-info" style="margin-top: 5px;">' .
                '<i class="fa fa-external-link" aria-hidden="true"></i> Ver Imagen Actual</a>',
                $ruta
            );
        }

        $form
            ->with("General", ["class" => "col-md-12"])
            ->add('id', IntegerType::class, [
                'label' => 'ID (Fotolito)',
                'disabled' => true,
                'required' => false
            ])
            ->add('nombre', TextType::class, ['required' => false])

            // --- CAMPO URL IMAGEN MODIFICADO ---
            ->add('urlImagen', TextType::class, [ // Usamos TextType para permitir nombres de archivo sin validar protocolo
                'required' => false,
                'label' => 'URL Imagen / Archivo',
                'help' => $botonVerImagen, // Aquí inyectamos el botón
                'help_html' => true        // IMPORTANTE: Permite renderizar HTML en la ayuda
            ])
            // -----------------------------------

            ->add('personalizacion', EntityType::class, [
                'class' => Personalizacion::class,
                'choice_label' => 'nombre',
                'placeholder' => 'Selecciona una personalización',
                'required' => false
            ])
            ->add('nColores', IntegerType::class)
            ->add('imagenOriginal', ModelListType::class, ['required' => false])
            ->add('arteFin', ModelListType::class, ['required' => false])
            ->add('montaje', ModelListType::class, ['required' => false])
            ->end();
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('codigo', StringFilter::class)
            ->add('contacto', ModelFilter::class, [
                'label' => 'Usuario (Contacto)',
                'field_options' => [
                    'choice_label' => 'nombre'
                ]
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('codigo')
            ->add('contacto', null, [
                'label' => 'Cliente',
//                'associated_property' => '__toString'
            ])

            // Usamos la plantilla personalizada que creamos antes para el listado
            ->add('urlImagen', null, [
                'label' => 'URL Imagen',
                'template' => 'admin/CRUD/list_url_external.html.twig'
            ])

            ->add('imagenOriginal', 'string', [
                'label' => 'Imagen (Media)',
                'template' => '@SonataMedia/MediaAdmin/list_image.html.twig'
            ]);
    }
}