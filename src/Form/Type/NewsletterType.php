<?php

namespace App\Form\Type;

use App\Entity\Newsletter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewsletterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // MIGRACIÓN: Los tipos de campo ('email') ahora se definen con su clase ::class.
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Tu dirección de correo electrónico',
                'attr' => ['placeholder' => 'email@ejemplo.com']
            ])
            ->add('suscribirse', SubmitType::class, [
                'label' => 'Suscribirse'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // MIGRACIÓN: Se enlaza el formulario con la entidad Newsletter.
        $resolver->setDefaults([
            'data_class' => Newsletter::class,
        ]);
    }
}
