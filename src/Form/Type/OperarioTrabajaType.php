<?php

namespace App\Form\Type;

use App\Model\ContactoTrabaja;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType; // AÑADIR ESTE IMPORT
use Symfony\Component\Form\Extension\Core\Type\IntegerType; // AÑADIR ESTE IMPORT

class OperarioTrabajaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class)
            ->add('edad', IntegerType::class, ['required' => true]) // <-- FALTABA ESTE
            ->add('telefono', TextType::class)
            ->add('email', EmailType::class)
            ->add('provincia', TextType::class)
            ->add('pais', TextType::class)
            ->add('experiencia', TextType::class)
            ->add('empresas', TextType::class)
            // --- CAMPO DE SUBIDA DE FICHEROS ---
            ->add('cv', FileType::class, [ // <-- FALTABA ESTE
                'label' => 'Subir CV (PDF/Word)',
                'required' => true,
            ])
            // -----------------------------------
            ->add('operario', CheckboxType::class, ['required' => false])
            ->add('comercial', CheckboxType::class, ['required' => false])
            ->add('design', CheckboxType::class, ['required' => false])
            ->add('observaciones', TextareaType::class, ['required' => false])
            ->add('enviar', SubmitType::class, ['label' => 'Enviar Solicitud']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactoTrabaja::class,
        ]);
    }
}
