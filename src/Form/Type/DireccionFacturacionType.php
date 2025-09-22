<?php
// src/Form/Type/DireccionFacturacionType.php

namespace App\Form\Type;

use App\Entity\Direccion;
use App\Entity\Pais;
use App\Entity\Provincia;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulario específico para la dirección de facturación.
 * No incluye campos como 'nombre' o 'predeterminada'.
 */
class DireccionFacturacionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dir', TextType::class, ['label' => 'Dirección'])
            ->add('cp', TextType::class, ['label' => 'Código postal'])
            ->add('poblacion', TextType::class, ['label' => 'Población'])
            ->add('paisBD', EntityType::class, [
                'class' => Pais::class,
                'choice_label' => 'nombre',
                'label' => 'País',
                'placeholder' => 'Selecciona un país',
                'attr' => ['class' => 'form-control chosen-select country-selector'],
            ]);

        $this->addProvinceFieldsBasedOnCountry($builder);
    }

    private function addProvinceFieldsBasedOnCountry(FormBuilderInterface $builder): void
    {
        $formModifier = function (FormInterface $form, ?Pais $pais = null) {
            $provinces = (null === $pais || $pais->getProvincias()->isEmpty()) ? [] : $pais->getProvincias();

            $form->add('provincia', TextType::class, [
                'label' => 'Provincia',
                'required' => empty($provinces),
                'constraints' => empty($provinces) ? [new NotBlank(['message' => 'Por favor, introduce una provincia.'])] : [],
            ]);

            $form->add('provinciaBD', EntityType::class, [
                'class' => Provincia::class,
                'label' => 'Provincia',
                'placeholder' => 'Selecciona una provincia',
                'choices' => $provinces,
                'required' => !empty($provinces),
                'constraints' => !empty($provinces) ? [new NotBlank(['message' => 'Por favor, selecciona una provincia.'])] : [],
            ]);
        };

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifier) {
                $data = $event->getData();
                $formModifier($event->getForm(), $data instanceof Direccion ? $data->getPaisBD() : null);
            }
        );

        $builder->get('paisBD')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formModifier) {
                $pais = $event->getForm()->getData();
                $formModifier($event->getForm()->getParent(), $pais);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Direccion::class]);
    }
}
