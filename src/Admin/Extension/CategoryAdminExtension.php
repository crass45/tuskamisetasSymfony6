<?php

namespace App\Admin\Extension;

use App\Entity\Sonata\ClassificationCategory;
use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class CategoryAdminExtension extends AbstractAdminExtension
{
    public function configureFormFields(FormMapper $form): void
    {


        // Nosotros ahora simplemente añadimos los nuestros.
        $form
//            ->tab('General') // Volvemos a la pestaña 'General' para añadir nuestros campos
            ->with('Datos Personalizados', ['class' => 'col-md-12','label' => "Datos Personalizados"])
            ->add('slug')
            ->add('aparece_home', CheckboxType::class, ['label' => 'Aparece en la Home', 'required' => false])
            ->add('visible_menu', CheckboxType::class, ['label' => 'Visible en el Menú', 'required' => false])
            ->add('precio_min', NumberType::class, ['label' => 'Precio Mínimo', 'required' => false])
            ->add('imagen', ModelListType::class, ['label' => 'Imagen', 'required' => false])
            ->end()
//            ->end()
//            ->tab('Contenido Traducible') // Creamos nuestra propia pestaña para los campos traducibles
            ->with('Textos por Idioma')
            ->add('descripcion', TextareaType::class, ['label' => "Descripción", 'required' => false,])
            ->add('tituloSEOTrans', TextType::class, ['label' => "Título SEO", 'required' => false])
            ->add('textoArriba', TextareaType::class, ['label' => "Texto Parte Superior", 'required' => false,'attr' => ['class' => 'tinymce']])
            ->add('textoAbajo', TextareaType::class, ['label' => "Texto Parte Inferior", 'required' => false,'attr' => ['class' => 'tinymce']])
            ->end()
//            ->end()
        ;
    }
}

//PODEMOS REVISAR ESTO DESPUES PARA MODIFICAR EL ASPECTO Y LOS CAMPOS QUE SE MUESTRAN
//namespace App\Admin\Extension;
//
//use FOS\CKEditorBundle\Form\Type\CKEditorType;
//use Sonata\AdminBundle\Admin\AbstractAdminExtension;
//use Sonata\AdminBundle\Form\FormMapper;
//use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
//use Symfony\Component\Form\Extension\Core\Type\NumberType;
//use Symfony\Component\Form\Extension\Core\Type\TextareaType;
//use Symfony\Component\Form\Extension\Core\Type\TextType;
//
//final class CategoryAdminExtension extends AbstractAdminExtension
//{
//    public function configureFormFields(FormMapper $form): void
//    {
//        // ==========================================================
//        // PASO 1: MODIFICAR O ELIMINAR CAMPOS EXISTENTES
//        // ==========================================================
//
//        // Eliminamos el campo 'enabled' que añade Sonata por defecto
//        $form->remove('enabled');
//
//        // Modificamos la etiqueta del campo 'parent' que ya existe
//        $form
//            ->get('parent') // Obtenemos el campo 'parent'
//            ->setLabel('Categoría Padre (Modificado)'); // Le cambiamos la etiqueta
//
//        // ==========================================================
//        // PASO 2: AÑADIR NUESTROS PROPIOS CAMPOS Y PESTAÑAS
//        // ==========================================================
//        $form
//            ->tab('General')
//            ->with('Datos Personalizados', ['class' => 'col-md-12'])
//            ->add('aparece_home', CheckboxType::class, ['label' => 'Aparece en la Home', 'required' => false])
//            ->add('visible_menu', CheckboxType::class, ['label' => 'Visible en el Menú', 'required' => false])
//            ->add('precio_min', NumberType::class, ['label' => 'Precio Mínimo', 'required' => false])
//            ->end()
//            ->end()
//            ->tab('Contenido Traducible')
//            ->with('Textos por Idioma')
//            // Movemos el campo 'name' del grupo original a nuestra pestaña de traducciones
//            ->add('name', TextType::class, [
//                'label' => 'Nombre (Traducible)',
//                'help' => 'Este campo es añadido por Sonata, pero lo hemos movido aquí.'
//            ])
//            ->add('descripcion', TextareaType::class, ['label' => "Descripción", 'required' => false])
//            ->add('tituloSEOTrans', TextType::class, ['label' => "Título SEO", 'required' => false])
//            ->add('textoArriba', CKEditorType::class, ['label' => "Texto Parte Superior", 'required' => false])
//            ->add('textoAbajo', CKEditorType::class, ['label' => "Texto Parte Inferior", 'required' => false])
//            ->end()
//            ->end();
//    }
//}
//

