<?php
// src/Uploader/UploadListener.php

namespace App\Uploader;

use Oneup\UploaderBundle\Event\PostPersistEvent;

/**
 * Esta clase "escucha" el evento de subida de ficheros y se asegura de que
 * la respuesta JSON que se envía al navegador es la correcta.
 */
class UploadListener
{
    public function onUpload(PostPersistEvent $event): void
    {
        // 1. Obtenemos el array de la respuesta del evento.
        $response = $event->getResponse();
        $file = $event->getFile();

        // 2. Esta es la estructura JSON exacta que el plugin jQuery File Upload espera.
        $fileData = [
            'name' => $file->getBasename(),
            'size' => $file->getSize(),
            'url' => '/uploads/gallery/' . $file->getBasename(),
            'thumbnailUrl' => '/uploads/gallery/' . $file->getBasename(),
        ];

        // 3. Añadimos nuestros datos al array de la respuesta.
        // El bundle se encargará de convertir este array a JSON.
        $response['files'] = [$fileData];
    }
}