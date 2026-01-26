<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class PedidoLineaHasTrabajoAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        // Recuperamos el objeto para pintar la imagen en el formulario
        $subject = $this->getSubject();

        $imagenHtml = '';
        if ($subject && $subject->getUrlImagenArea()) {
            $imagenHtml = sprintf(
                '<div style="margin-top:5px;"><img src="%s" style="max-height: 150px; border: 1px solid #ccc; padding: 5px;"></div>',
                $subject->getUrlImagenArea()
            );
        }

        $form
            ->with('Datos del Trabajo', ['class' => 'col-md-7'])
            ->add('pedidoTrabajo', ModelListType::class, [
                'label' => 'Trabajo Realizado',
                'btn_delete' => false,
                'btn_add' => false,
                'btn_edit' => false,
                'btn_list' => false,
            ])
            ->add('ubicacion', TextType::class, [
                'label' => 'Ubicación (Original)',
                'required' => false,
                'help' => 'Nombre que seleccionó el cliente (puede no coincidir con el snapshot si se editó)'
            ])
            ->add('observaciones', TextareaType::class, ['required' => false])
            ->end()

            ->with('Snapshot (Foto Fija al Comprar)', ['class' => 'col-md-5'])
            ->add('nombreArea', TextType::class, [
                'label' => 'Nombre Área (Técnica)',
                'required' => false,
                'disabled' => true // Readonly para mantener integridad histórica
            ])
            ->add('anchoArea', NumberType::class, [
                'label' => 'Ancho (cm)',
                'required' => false,
                'disabled' => true
            ])
            ->add('altoArea', NumberType::class, [
                'label' => 'Alto (cm)',
                'required' => false,
                'disabled' => true
            ])
            ->add('urlImagenArea', TextType::class, [
                'label' => 'Imagen del Área',
                'required' => false,
                'disabled' => true,
                'help' => $imagenHtml, // Aquí mostramos la foto
                'help_html' => true
            ])
            ->end();
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            // Usamos nuestra plantilla personalizada para ver la foto pequeña
            ->add('urlImagenArea', null, [
                'label' => 'Img',
                'template' => 'admin/CRUD/list_snapshot_image.html.twig'
            ])
            ->addIdentifier('pedidoTrabajo', null, ['label' => 'Trabajo'])
            ->add('cantidad')
            ->add('ubicacion', null, ['label' => 'Ubicación'])
            ->add('nombreArea', null, ['label' => 'Área Técnica'])
            ->add('anchoArea', null, ['label' => 'Ancho'])
            ->add('altoArea', null, ['label' => 'Alto'])
            // Si quieres ver las medidas juntas, podrías usar un template o getter virtual,
            // pero columnas separadas está bien para ordenar.
        ;
    }
}