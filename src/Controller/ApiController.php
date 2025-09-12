<?php
// src/Controller/ApiController.php

namespace App\Controller;

use App\Entity\Pais;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

// MIGRACIÓN: Se crea un prefijo /api para todos los endpoints de este controlador
#[Route('/api')]
class ApiController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * Devuelve una lista de provincias para un país determinado.
     * La URL será, por ejemplo, /api/pais/1/provincias
     */
    #[Route('/pais/{id}/provincias', name: 'api_get_country_provinces', methods: ['GET'])]
    public function getCountryProvincesAction(Pais $pais): JsonResponse
    {
        // MIGRACIÓN: Al usar el 'param converter' de Symfony, no necesitamos buscar el país
        // manualmente. Symfony lo hace por nosotros a partir del {id} de la URL.

        $responseArray = [];
        foreach ($pais->getProvincias() as $provincia) {
            $responseArray[] = [
                'id' => $provincia->getId(),
                'name' => $provincia->getNombre()
            ];
        }

        return new JsonResponse($responseArray);
    }
}

//### Resumen de los Cambios y Mejoras
//
//* **Nuevo Controlador Especializado:** Toda tu lógica de API vivirá ahora en `ApiController.php`.
//* **Ruta RESTful:** He cambiado la URL de `/listProvinciasdePaisAction?paisID=1` a una mucho más limpia y estándar: `/api/pais/1/provincias`.
//* **Param Converter de Symfony (¡Magia!):**
//    * Fíjate en la firma del nuevo método: `public function getCountryProvincesAction(Pais $pais)`.
//    * No hay ninguna línea que busque el país en la base de datos (`$em->getRepository(...)->find(...)`).
//    * Symfony es lo suficientemente inteligente como para ver que la ruta tiene un parámetro `{id}` y que el método espera un objeto `Pais`. **Automáticamente busca el país con ese `id` en la base de datos por ti**. Si no lo encuentra, devuelve una página 404. Esto hace el código mucho más corto y robusto.
//* **Consistencia:** El resto de la lógica para construir el array de respuesta se ha mantenido, pero usando los getters modernos de la entidad.

//### Próximos Pasos (¡Muy Importante!)
//
//1.  **Crea el nuevo fichero** `src/Controller/ApiController.php`.
//2.  **Actualiza tu código JavaScript:** Tendrás que buscar en tu código JavaScript (probablemente en `public/js/app-tienda.js` o similar) la llamada AJAX que usaba la URL antigua y **actualizarla para que ahora llame a la nueva URL**.
//
//    **Ejemplo en JavaScript:**
//    ```javascript
//    // Supongamos que tienes un desplegable de países con el id #pais_selector
//    $('#pais_selector').on('change', function() {
//        const paisId = $(this).val();
//
//        // MIGRACIÓN: La URL ha cambiado
//        const url = `/api/pais/${paisId}/provincias`;
//
//        // La lógica para hacer la llamada AJAX y rellenar el desplegable de provincias
//        // sigue siendo la misma.
//        $.getJSON(url, function(data) {
//            // ...
//        });
//    });

