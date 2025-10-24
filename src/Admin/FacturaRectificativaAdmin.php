<?php

namespace App\Admin;

use App\Entity\Factura;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\Form\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class FacturaRectificativaAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        // 1. Obtenemos el objeto actual (la FacturaRectificativa)
        /** @var \App\Entity\FacturaRectificativa|null $subject */
        $subject = $this->getSubject();
        $facturaOriginal = ($subject && $subject->getId()) ? $subject->getFacturaPadre() : null;

        // 2. Preparamos las opciones para el campo facturaPadre
        $facturaPadreOptions = [
            'label' => 'Factura Original',
            'disabled' => true,
        ];

        // 3. Si existe una factura original, generamos el enlace
        if ($facturaOriginal) {
            try {
                // Obtenemos el Admin de la Factura (padre) usando el Pool
                $facturaAdmin = $this->getConfigurationPool()
                    ->getAdminByClass(Factura::class); // <-- ¡Usa tu clase de entidad Factura!

                // Generamos la URL a la acción 'edit' de la factura padre
                $url = $facturaAdmin->generateObjectUrl('edit', $facturaOriginal);

                // Creamos el HTML del enlace (con target="_blank")
                $linkHtml = sprintf(
                    '<a href="%s" target="_blank" class="btn btn-info btn-sm" style="margin-top: 5px;">' .
                    '<i class="fa fa-eye"></i> Ver Factura Original (%s) en una pestaña nueva' .
                    '</a>',
                    $url,
                    (string)$facturaOriginal // Muestra el No de factura, o lo que devuelva __toString()
                );

                // 4. Añadimos el enlace a las opciones del campo
                $facturaPadreOptions['help'] = $linkHtml;
                $facturaPadreOptions['help_html'] = true; // ¡Muy importante!

            } catch (\Exception $e) {
                // Fallback por si no se encuentra el admin (raro, pero seguro)
                $facturaPadreOptions['help'] = 'Error: No se pudo generar el enlace a la factura original.';
            }
        }

        $form
            ->with('General', ['class' => 'col-md-6'])
            ->add('facturaPadre', TextType::class, $facturaPadreOptions)
            ->add('numeroFactura', null, [
                'label' => 'Número de Factura Rectificativa','disabled'=>'true','attr'=>['placeholder'=>'Se rellena automáticamente al sincronizar con verifactu'],
            ])
            ->add('motivo', TextareaType::class, [
                'label' => 'Motivo de la Rectificación',
                'required' => true,
            ])
            ->end()
            ->with('Datos Factura', ['class' => 'col-md-6'])
            ->add('razonSocial', null, [])
            ->add('cif', null, [])
            ->add('direccion', null, [])
            ->add('cp', null, [])
            ->add('poblacion', null, [])
            ->add('provincia', null, [])
            ->add('pais', null, [])
            ->end()
            ->with('Líneas de la Factura', ['class' => 'col-md-12'])
            ->add('lineas', CollectionType::class, [
                'label' => false,
                'by_reference' => false,
            ], [
                'edit' => 'inline',
                'inline' => 'table',
                // Le decimos a Sonata que use el 'mini-admin' para renderizar cada fila
                'admin_code' => 'admin.factura_rectificativa_linea',
            ])
            ->end()
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('numeroFactura', null, ['label' => 'Número'])
            ->add('facturaPadre.pedido.contacto', null, ['label' => 'Contacto'])
            ->add('facturaPadre', null, ['label' => 'Factura Original'])
            ->add('motivo', null, ['label' => 'Motivo'])
            ->add('fecha', null, [
                'label' => 'Fecha de Creación',
                'format' => 'd/m/Y H:i',
            ])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show_pdf' => [
                        'template' => 'admin/CRUD/list_action_show_rectificativa_pdf.html.twig'
                    ],
                    // --- ACCIÓN AÑADIDA ---
                    'generateVerifactu' => [
                        'template' => 'admin/CRUD/list__action_generate_verifactu.html.twig'
                    ],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    protected function configureActionButtons(array $buttonList, string $action, ?object $object = null): array
    {
        // AÑADIMOS LA NUEVA ACCIÓN
        if (in_array($action, ['edit', 'show']) && $object) {
            $buttonList['showFacturaRectificativa'] = [
                'template' => 'admin/button_rectificativa_show_rectificativa.html.twig',
            ];
            $buttonList['generateVerifactu'] = [
                'template' => 'admin/button_rectificativa_generate_verifactu.html.twig',
            ];
        }

        return $buttonList;
    }


    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('create');
        $collection->add('showFacturaRectificativa', $this->getRouterIdParameter() . '/show-pdf');
        // --- RUTA AÑADIDA ---
        // Esta ruta apunta a la acción que crearemos en FacturaRectificativaCRUDController
        $collection->add('generateVerifactu', $this->getRouterIdParameter() . '/generate-verifactu');
    }
}
