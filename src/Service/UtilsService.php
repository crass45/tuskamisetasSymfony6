<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Servicio para pequeñas funciones de utilidad que no
 * encajan en otros servicios más grandes.
 */
class UtilsService
{

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * Convierte un string (con coma o punto) a float.
     */
    public function toFloat($value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }
        return (float)$value;
    }

    public function isUrlValidImage(string $url): bool
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        try {
            // Hacemos una petición HEAD, solo queremos las cabeceras (más rápido)
            $response = $this->httpClient->request('HEAD', $url, [
                'timeout' => 5, // 5 segundos de timeout
            ]);

            // Comprobar que la respuesta es 2xx (ej: 200 OK)
            if ($response->getStatusCode() >= 300) {
                return false;
            }

            // Comprobar que el Content-Type es una imagen
            $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
            if (str_starts_with($contentType, 'image/')) {
                return true;
            }

            return false;

        } catch (\Exception $e) {
            // Capturar timeouts, errores de DNS, SSL, etc.
            // $this->output->writeln("<comment>Warning: URL check failed for {$url}: " . $e->getMessage() . "</comment>");
            return false;
        }
    }


}