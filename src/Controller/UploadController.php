<?php
// src/Controller/UploadController.php

//Esta clase se encarga simplemente de la subida de imagenes desde la web.

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    /**
     * MIGRACIÓN: Este método gestiona la subida de ficheros de diseño.
     * Es más seguro porque usa el objeto Request de Symfony en lugar de $_FILES.
     */
    #[Route('/uploadGalleryTKM', name: 'app_upload_design', methods: ['POST'])]
    public function uploadDesignAction(Request $request): JsonResponse
    {
        // El nombre 'design_file' es un ejemplo. Debes asegurarte de que el JavaScript
        // que sube el fichero lo envía con este nombre de campo.
        $uploadedFile = $request->files->get('design_file');

        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No se ha subido ningún fichero.'], 400);
        }

        // Generamos un nombre de fichero nuevo y seguro para evitar colisiones
        $newFilename = uniqid() . '.' . $uploadedFile->guessExtension();

        // MIGRACIÓN: Usamos un parámetro para definir la carpeta de subidas, es más flexible.
        $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/designs';

        try {
            // MIGRACIÓN: Usamos el método move() del objeto UploadedFile, que es más seguro
            // que la función move_uploaded_file() de PHP.
            $uploadedFile->move(
                $uploadDirectory,
                $newFilename
            );
        } catch (FileException $e) {
            // Manejamos un posible error si no se puede mover el fichero
            return new JsonResponse(['error' => 'No se pudo guardar el fichero.'], 500);
        }

        // Devolvemos una respuesta JSON con la ruta del fichero subido
        return new JsonResponse([
            'success' => true,
            'filePath' => '/uploads/designs/' . $newFilename
        ]);
    }
}