<?php
// src/Form/Type/DireccionType.php

namespace App\Form\Type;

use App\Entity\Direccion;
use App\Entity\Pais;
use App\Entity\Provincia;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DireccionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['is_shipping_address']) {
            $builder
                ->add('nombre', TextType::class, ['label' => 'Nombre / Alias (ej. Casa, Oficina)', 'required' => true])
                ->add('telefonoMovil', TextType::class, ['label' => 'Teléfono de Contacto', 'required' => false])
                ->add('predeterminada', CheckboxType::class, ['label' => 'Establecer como dirección de envío por defecto', 'required' => false]);
        }

        $builder
            ->add('paisBD', EntityType::class, [
                'class' => Pais::class,
                'choice_label' => 'nombre',
                'label' => 'País',
                'placeholder' => 'Selecciona un país',
                'attr' => ['class' => 'form-control chosen-select country-selector'],
            ])
            ->add('dir', TextType::class, ['label' => 'Dirección'])
            ->add('cp', TextType::class, ['label' => 'Código postal'])
            ->add('poblacion', TextType::class, ['label' => 'Población']);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit']);
    }

    protected function addProvinceFields(FormInterface $form, ?Pais $pais): void
    {
        $provinces = ($pais && $pais->getProvincias()->count() > 0) ? $pais->getProvincias() : [];

        // CORRECCIÓN: Se eliminan los 'style' => 'display:none'. El JS se encargará de la visibilidad.
        $form->add('provincia', TextType::class, [
            'label' => 'Provincia',
            'required' => false,
            'attr' => ['class' => 'province-text-input'],
        ]);

        $form->add('provinciaBD', EntityType::class, [
            'class' => Provincia::class,
            'label' => 'Provincia',
            'placeholder' => 'Selecciona una provincia',
            'choices' => $provinces,
            'required' => false,
            'attr' => ['class' => 'form-control chosen-select province-select'],
        ]);
    }

    public function onPreSetData(FormEvent $event): void
    {
        $data = $event->getData();
        $pais = $data instanceof Direccion ? $data->getPaisBD() : null;
        $this->addProvinceFields($event->getForm(), $pais);
    }

    public function onPreSubmit(FormEvent $event): void
    {
        $this->addProvinceFields($event->getForm(), null);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Direccion::class,
            'is_shipping_address' => false,
        ]);
    }
}

