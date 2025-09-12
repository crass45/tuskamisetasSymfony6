<?php
// src/Controller/Admin/TarifaCRUDController.php

namespace App\Controller\Admin;

use App\Entity\Tarifa;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TarifaCRUDController extends CRUDController
{
    public function cloneAction(Request $request, EntityManagerInterface $em): Response
    {
        /** @var Tarifa|null $tarifaOriginal */
        $tarifaOriginal = $this->admin->getSubject();

        if (!$tarifaOriginal) {
            throw $this->createNotFoundException('No se ha encontrado la tarifa a clonar.');
        }

        // --- Lógica para clonar la Tarifa ---
        $nuevaTarifa = clone $tarifaOriginal; // PHP clona el objeto base
        $nuevaTarifa->setNombre($tarifaOriginal->getNombre() . ' (Copia)');

        // Clonar la colección de precios
        foreach ($tarifaOriginal->getPrecios() as $precioOriginal) {
            $nuevoPrecio = clone $precioOriginal;
            $nuevaTarifa->addPrecio($nuevoPrecio);
        }

        $em->persist($nuevaTarifa);
        $em->flush();

        $this->addFlash('sonata_flash_success', 'Tarifa clonada correctamente.');

        return $this->redirectToRoute('admin_app_tarifa_list'); // Redirige a la lista
    }
}