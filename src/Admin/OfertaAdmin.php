<?php

namespace App\Admin;

use App\Entity\Oferta;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\DoctrineORMAdminBundle\Filter\StringFilter;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class OfertaAdmin extends AbstractAdmin
{
    private Pool $mediaExtension;

    public function __construct(Pool $mediaExtension)
    {
        parent::__construct();
        $this->mediaExtension = $mediaExtension;
    }

    protected function configureFormFields(FormMapper $form): void
    {
        /** @var Oferta|null $oferta */
        $oferta = $this->getSubject();
        $imagen = '';

        if ($oferta && $oferta->getImagen()) {
            $format = 'small';
            $provider = $this->mediaExtension->getProvider($oferta->getImagen()->getProviderName());//path($modelo->getImagen(), $format);
            $webPath = $provider->generatePublicUrl($oferta->getImagen(), $format);
            $imagen = '<img src="' . $webPath . '" class="admin-preview" style="max-height: 100px;"/>';
        }

        $form
            ->tab("GENERAL")
            ->with('Datos', ['class' => 'col-md-8'])
            ->add('nombre', TextType::class)
            ->add('descripcion', TextareaType::class, ['required' => false])
            ->add('activo', CheckboxType::class, ['required' => false])
            ->add('imagen', ModelListType::class, [
                'required' => false,
                'help' => $imagen,
                'help_html' => true
            ])
            ->end()
            ->with('SEO', ['class' => 'col-md-4'])
            ->add('tituloSEO', TextType::class, ['label' => "Título SEO", 'required' => false])
            ->add('descripcionSEO', TextareaType::class, ['label' => "Descripción SEO", 'required' => false])
            ->add('nombreUrl', TextType::class, ['label' => "URL"])
            ->end()
            ->end()
            ->tab("CONFIGURACIÓN")
            ->with("Configuración de la Oferta", ['class' => 'col-md-12'])
            ->add('modelos', ModelAutocompleteType::class, [
                'property' => 'nombre',
                'multiple' => true,
                'label' => 'Modelos incluidos'
            ])
            ->add('cantidadMinima', IntegerType::class, ['required' => false])
            ->add('incrementoCantidadMinima', MoneyType::class, [
                'label' => 'Incremento por no llegar a cant. mínima',
                'currency' => 'EUR',
                'required' => false
            ])
            ->add('precio', MoneyType::class, [
                'label' => 'Precio de la oferta',
                'currency' => 'EUR'
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
            ->addIdentifier('nombre')
            ->add('precio', 'currency', [
                'currency' => 'EUR'
            ])
            ->add('activo', null, ['editable' => true]);
    }
}