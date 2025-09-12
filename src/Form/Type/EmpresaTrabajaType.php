<?php

namespace App\Form\Type;

use App\Model\EmpresaTrabaja;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmpresaTrabajaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // MIGRACIÓN: Los tipos de campo ahora se definen con su clase ::class.
        $builder
            ->add('nombre', TextType::class)
            ->add('telefono', TextType::class)
            ->add('email', EmailType::class)
            ->add('provincia', TextType::class)
            ->add('pais', TextType::class)
            ->add('experiencia', TextType::class)
            ->add('serigrafia', CheckboxType::class, ['required' => false])
            ->add('tampografia', CheckboxType::class, ['required' => false])
            ->add('bordado', CheckboxType::class, ['required' => false])
            ->add('dgt', CheckboxType::class, ['required' => false])
            ->add('laser', CheckboxType::class, ['required' => false])
            ->add('otro', CheckboxType::class, ['required' => false])
            ->add('observaciones', TextareaType::class, ['required' => false])
            ->add('enviar', SubmitType::class, ['label' => 'Enviar Solicitud']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // MIGRACIÓN: Se enlaza el formulario con el objeto Modelo/DTO correspondiente.
        $resolver->setDefaults([
            'data_class' => EmpresaTrabaja::class,
        ]);
    }
}
