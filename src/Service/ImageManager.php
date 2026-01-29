<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImageManager
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private Filesystem $filesystem,
        private string $projectDir
    ) {}

    public function download(?string $url, string $folder, string $filename): ?string
    {
        if (!$url || !str_starts_with($url, 'http')) return $url;

        $relativePath = "/uploads/images/$folder/" . $filename . ".webp";
        $fullPath = $this->projectDir . '/public' . $relativePath;

        if (file_exists($fullPath)) return $relativePath;

        try {
            if (!is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0775, true);
            }

            // --- CAMBIO CRÍTICO: buffer en false para no usar RAM ---
            $response = $this->httpClient->request('GET', $url, [
                'buffer' => false
            ]);

            if ($response->getStatusCode() === 200) {
                $tempFile = $fullPath . '.tmp';

                // Abrimos el archivo en modo escritura
                $fileHandler = fopen($tempFile, 'w');

                // Leemos la respuesta por trozos (chunks) y escribimos al disco
                foreach ($this->httpClient->stream($response) as $chunk => $payload) {
                    fwrite($fileHandler, $payload->getContent());
                }
                fclose($fileHandler);

                // Procesar y convertir (ahora el archivo ya está en disco, no en RAM)
                $info = @getimagesize($tempFile);
                if (!$info) {
                    if (file_exists($tempFile)) unlink($tempFile);
                    return $url;
                }

                $image = match($info['mime'] ?? '') {
                    'image/jpeg' => @imagecreatefromjpeg($tempFile),
                    'image/png'  => @imagecreatefrompng($tempFile),
                    'image/webp' => @imagecreatefromwebp($tempFile),
                    default      => null,
                };

                if ($image) {
                    if (imagesx($image) > 1200) {
                        $image = imagescale($image, 1200);
                    }
                    imagewebp($image, $fullPath, 80);
                    imagedestroy($image);
                }

                if (file_exists($tempFile)) unlink($tempFile);
                return $relativePath;
            }
        } catch (\Exception $e) {
            if (isset($tempFile) && file_exists($tempFile)) unlink($tempFile);
        }

        return $url;
    }
}