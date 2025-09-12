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
        $builder
            ->add('predeterminada', CheckboxType::class, [
                'label' => 'Establecer como dirección de envío por defecto',
                'required' => false,
            ])
            ->add('nombre', TextType::class, [
                'label' => 'Nombre / Alias (ej. Casa, Oficina)',
                'required' => true,
            ])
            ->add('telefonoMovil', TextType::class, [
                'label' => 'Teléfono de Contacto',
                'required' => false,
            ])
            ->add('paisBD', EntityType::class, [
                'class' => Pais::class,
                'choice_label' => 'nombre',
                'label' => 'País',
                'required' => true,
                'placeholder' => 'Selecciona un país',
                'attr' => ['class' => 'form-control chosen'],
            ])
            ->add('dir', TextType::class, ['label' => 'Dirección', 'required' => true])
            ->add('cp', TextType::class, ['label' => 'Código postal', 'required' => true])
            ->add('poblacion', TextType::class, ['label' => 'Población', 'required' => true])
            ->add('provincia', TextType::class, [
                'label' => 'Provincia (si no aparece en la lista)',
                'required' => false,
            ]);

        // MIGRACIÓN: Se activan los listeners para el desplegable de provincias dependiente.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit']);
    }

    /**
     * Añade el campo de provincia al formulario.
     */
    protected function addProvinceField(FormInterface $form, ?Pais $pais): void
    {
        $provinces = $pais ? $pais->getProvincias() : [];

        $form->add('provinciaBD', EntityType::class, [
            'class' => Provincia::class,
            'label' => 'Provincia',
            'placeholder' => 'Selecciona una provincia',
            'choices' => $provinces,
            'required' => false, // No es obligatorio si se escribe a mano
            'attr' => ['class' => 'form-control chosen'],
        ]);
    }

    /**
     * Se ejecuta al crear el formulario. Rellena las provincias si ya hay un país seleccionado.
     */
    public function onPreSetData(FormEvent $event): void
    {
        /** @var Direccion|null $data */
        $data = $event->getData();
        $form = $event->getForm();

        $pais = $data ? $data->getPaisBD() : null;
        $this->addProvinceField($form, $pais);
    }

    /**
     * Se ejecuta al enviar el formulario. Actualiza la lista de provincias según el país enviado.
     */
    public function onPreSubmit(FormEvent $event): void
    {
        $data = $event->getData();
        $form = $event->getForm();

        $paisId = $data['paisBD'] ?? null;

        if ($paisId) {
            $this->addProvinceField($form, null);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Direccion::class,
        ]);
    }
}

