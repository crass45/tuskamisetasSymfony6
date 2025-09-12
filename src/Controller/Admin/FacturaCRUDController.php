<?php
// src/Controller/Admin/FacturaCRUDController.php

namespace App\Controller\Admin;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Response;

final class FacturaCRUDController extends CRUDController
{
    public function showFacturaFacturaAction(int $id): Response
    {
        $factura = $this->admin->getSubject();

        // ... Tu lógica para generar y mostrar el PDF de la factura ...

        return new Response('Aquí se mostraría el PDF de la factura ' . $factura->getNombre());
    }

    public function creaRectificativaAction(int $id): Response
    {
        $factura = $this->admin->getSubject();

        // ... Tu lógica para crear la factura rectificativa ...

        return new Response('Aquí se crearía la factura rectificativa para ' . $factura->getNombre());
    }
}