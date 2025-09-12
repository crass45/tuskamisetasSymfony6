<?php

namespace App\Admin;

use App\Entity\Empresa;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Sonata\Form\Type\CollectionType;
use Sonata\Form\Type\DateTimePickerType;
use Sonata\FormatterBundle\Form\Type\SimpleFormatterType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class EmpresaAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->tab('Información')
            ->with('Datos', ['class' => 'col-md-8'])
            ->add('nombre', TextType::class)
            ->add('cif', TextType::class)
            ->add('email', TextType::class)
            ->add('telefonoMovil', TextType::class, ['required' => false])
            ->add('telefonoOtro', TextType::class, ['required' => false])
            ->add('descripcion', SimpleFormatterType::class, ['format'=>'richhtml','required' => false])
            ->add('descripcionContacto', TextType::class, ['required' => false])
            ->add('direccion', ModelListType::class, ['label' => 'Dirección Facturación'])
            ->add('direccionEnvio', ModelListType::class, ['label' => 'Dirección Envío'])
            ->end()
            ->with('Logo y Galería', ['class' => 'col-md-4'])
            ->add('logo', ModelListType::class, ['required' => false])
//            ->add('galleryHasMedias', CollectionType::class, [
//                'by_reference' => false,
//                'label' => 'Imágenes de Galería',
//            ], [
//                'edit' => 'inline',
//                'inline' => 'table',
//            ])
            ->end()
            ->with('Geolocalización', ['class' => 'col-md-4'])
            ->add('longitud', NumberType::class, ['required' => false])
            ->add('latitud', NumberType::class, ['required' => false])
            ->end()
            ->end()
            ->tab('Pagos')
            ->with('Pago por Transferencia')
            ->add('cuentaBancaria', CKEditorType::class, ['required' => false])
            ->end()
            ->with('Pago por PayPal')
            ->add('cuentaPaypal', TextType::class, ['required' => false])
            ->end()
            ->with('Pasarela de Pago')
            ->add('merchantCode', TextType::class, ['required' => false])
            ->add('merchantId', TextType::class, ['required' => false])
            ->end()
            ->end()
            ->tab('Redes Sociales')
            ->with('Social')
            ->add('facebook', TextType::class, ['required' => false])
            ->add('twitter', TextType::class, ['required' => false])
            ->add('youtube', TextType::class, ['required' => false])
            ->add('pinterest', TextType::class, ['required' => false])
            ->end()
            ->end()
            ->tab('Textos Legales')
            ->with('Legal')
            ->add('textoLegal', CKEditorType::class, ['label' => 'Términos de Uso'])
            ->add('textoPrivacidad', CKEditorType::class, ['label' => 'Política Privacidad'])
            ->add('politicaCookies', CKEditorType::class, ['label' => 'Política de Cookies'])
            ->end()
            ->end()
            ->tab('Configuración')
            ->with('Servicio Exprés', ['class' => 'col-md-6'])
            ->add('servicioExpresActivo', CheckboxType::class, ['required' => false])
            ->add('precioServicioExpres', NumberType::class, ['required' => false])
            ->add('minimoDiasConImpresion', IntegerType::class, ['required' => false])
            ->add('maximoDiasConImpresion', IntegerType::class, ['required' => false])
            ->add('minimoDiasSinImprimir', IntegerType::class, ['required' => false])
            ->add('maximoDiasSinImprimir', IntegerType::class, ['required' => false])
            ->end()
            ->with('IVA', ['class' => 'col-md-6'])
            ->add('ivaSuperreducido', IntegerType::class, ['required' => false])
            ->add('ivaReducido', IntegerType::class, ['required' => false])
            ->add('ivaGeneral', IntegerType::class, ['required' => false])
            ->add('recargoEquivalencia', NumberType::class, ['required' => false])
            ->end()
            ->end()
            ->tab('Vacaciones')
            ->with('Periodo de Vacaciones')
            ->add('vacacionesActivas', CheckboxType::class, ['required' => false])
            ->add('fechaInicioVacaciones', DateTimePickerType::class, [
                'label' => 'Inicio',
                'format' => 'dd-MM-yyyy',
                'required' => false,
            ])
            ->add('fechaFinVacaciones', DateTimePickerType::class, [
                'label' => 'Fin',
                'format' => 'dd-MM-yyyy',
                'required' => false,
            ])
            ->end()
            ->end();
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('nombre', StringFilter::class);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('nombre');
    }

    /**
     * NOTA DE MIGRACIÓN: Estos métodos a menudo eran un "apaño" para forzar
     * la actualización de colecciones. Con Doctrine moderno y las relaciones
     * bidireccionales bien definidas en la entidad, a menudo ya no son necesarios.
     * Los he conservado por seguridad.
     */
    protected function prePersist(object $object): void
    {
        if (!$object instanceof Empresa) {
            return;
        }
        $object->setGalleryHasMedias($object->getGalleryHasMedias());
    }

    protected function preUpdate(object $object): void
    {
        if (!$object instanceof Empresa) {
            return;
        }
        $object->setGalleryHasMedias($object->getGalleryHasMedias());
    }
}