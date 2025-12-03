<?php

namespace App\Command;

use App\Entity\Sonata\Media;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand(
    name: 'app:media:repair-missing-reference',
    description: 'Restaura archivos reference perdidos usando búsqueda exhaustiva de miniaturas wide.',
)]
class RepairMediaCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private Pool $mediaPool,
        private ParameterBagInterface $params
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Reparando imágenes (Modo Fuerza Bruta)...');

        $medias = $this->em->getRepository(Media::class)->findAll();
        $baseDir = $this->params->get('kernel.project_dir') . '/public/uploads/media/';
        $slugger = new AsciiSlugger();

        $stats = ['restored' => 0, 'missing' => 0];
        $firstErrorShown = false;

        foreach ($medias as $media) {
            // Solo procesamos imágenes
            if ($media->getProviderName() !== 'sonata.media.provider.image') {
                continue;
            }

            $provider = $this->mediaPool->getProvider($media->getProviderName());

            // 1. Ruta donde DEBERÍA estar el original (Reference) según la BBDD actual
            $pathRef = $provider->generatePrivateUrl($media, 'reference');
            $absRef = $baseDir . $pathRef;

            // Si ya existe, perfecto, siguiente.
            if (file_exists($absRef)) {
                continue;
            }

            // --- GENERACIÓN DE CANDIDATOS (FUERZA BRUTA) ---
            $candidates = [];

            // Datos base
            $nameOriginal = $media->getName(); // "Camisetas full print"
            $slugLower = $slugger->slug($nameOriginal)->lower()->toString(); // "camisetas-full-print"
            $slugReal = $slugger->slug($nameOriginal)->toString(); // "Camisetas-full-print" (Respeta mayúsculas)

            // Extensiones posibles (a veces en BBDD es jpg y el archivo jpeg)
            $extDb = pathinfo($media->getProviderReference(), PATHINFO_EXTENSION);
            $extensions = array_unique([$extDb, 'jpeg', 'jpg', 'png']);

            // Contextos posibles (carpetas y sufijos)
            $contextsToCheck = array_unique([
                $media->getContext(), // El de BBDD (ej: default)
                'producto',           // El de tu servidor antiguo
                'product',            // El estándar en inglés
                'default'             // El por defecto
            ]);

            // Generamos todas las rutas posibles donde podría estar el archivo 'wide'
            // Ruta base de Sonata: ID/ID/ (ej: 0001/01)
            // OJO: La ruta relativa de generatePrivateUrl ya incluye "contexto/0001/01/nombre.ext"
            // Necesitamos extraer solo la parte numérica: "0001/01"
            $pathParts = explode('/', $pathRef);
            // pathRef suele ser: contexto/0001/01/archivo.jpg
            // Nos quedamos con las partes intermedias si la estructura es estándar
            $idFolder = '';
            if (count($pathParts) >= 4) {
                $idFolder = $pathParts[1] . '/' . $pathParts[2]; // "0001/01"
            } else {
                // Fallback simple si la estructura es rara
                $idFolder = '*/*';
            }

            foreach ($contextsToCheck as $ctxFolder) {
                foreach ($contextsToCheck as $ctxFile) {
                    foreach ($extensions as $ext) {
                        foreach ([$slugReal, $slugLower] as $slug) {
                            // Construimos ruta: public/uploads/media/{CARPETA}/0001/01/{SLUG}_{SUFIJO}_wide.{EXT}

                            // Candidato A: Nombre-producto_contexto_wide.jpg (Tu caso)
                            $filenameA = sprintf('%s_%s_wide.%s', $slug, $ctxFile, $ext);
                            $candidates[] = $baseDir . $ctxFolder . '/' . $idFolder . '/' . $filenameA;

                            // Candidato B: Nombre-producto_wide.jpg (Sin contexto duplicado)
                            $filenameB = sprintf('%s_wide.%s', $slug, $ext);
                            $candidates[] = $baseDir . $ctxFolder . '/' . $idFolder . '/' . $filenameB;
                        }
                    }
                }
            }

            // --- BÚSQUEDA ---
            $foundPath = null;
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $foundPath = $candidate;
                    break;
                }
            }

            if ($foundPath) {
                // ¡ENCONTRADO!
                // 1. Asegurar que existe la carpeta de destino del Reference
                $destDir = dirname($absRef);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0775, true);
                }

                // 2. Copiar (Promocionar Wide -> Reference)
                copy($foundPath, $absRef);
                $io->text(sprintf("RECUPERADO: %s \n   Origen: %s", $media->getName(), basename($foundPath)));
                $stats['restored']++;
            } else {
                $stats['missing']++;

                // DEBUG: Mostrar dónde buscó el primero que falló
                if (!$firstErrorShown) {
                    $io->warning("DEBUG FALLO - Ejemplo de rutas probadas para: " . $media->getName());
                    $io->listing(array_slice($candidates, 0, 10)); // Muestra las 10 primeras pruebas
                    $firstErrorShown = true;
                }
            }
        }

        $io->success(sprintf('PROCESO FINALIZADO. Recuperados: %d. Perdidos: %d', $stats['restored'], $stats['missing']));
        return Command::SUCCESS;
    }
}