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
    description: 'Restaura archivos reference perdidos buscando por nombre antiguo (Slug) y promoviendo el Wide.',
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
        $io->title('Reparando imágenes (Búsqueda inteligente por nombre)...');

        $medias = $this->em->getRepository(Media::class)->findAll();
        $baseDir = $this->params->get('kernel.project_dir') . '/public/uploads/media/';
        $slugger = new AsciiSlugger();

        $stats = ['restored' => 0, 'moved' => 0, 'missing' => 0];

        foreach ($medias as $media) {
            if ($media->getProviderName() !== 'sonata.media.provider.image') {
                continue;
            }

            $provider = $this->mediaPool->getProvider($media->getProviderName());
            $context = $media->getContext();

            // Ruta esperada del ORIGINAL (Según BBDD actual)
            $pathRef = $provider->generatePrivateUrl($media, 'reference');
            $absRef = $baseDir . $pathRef;
            $dir = dirname($absRef); // Carpeta contenedora (ej: .../producto/0002/08)

            // Si ya existe el original, saltamos
            if (file_exists($absRef)) {
                continue;
            }

            // --- ESTRATEGIA DE BÚSQUEDA DE CANDIDATOS ---
            $candidates = [];
            $ext = pathinfo($media->getProviderReference(), PATHINFO_EXTENSION); // jpg, png...

            // 1. Búsqueda por nombre estándar Sonata: thumb_ID_wide.ext
            $pathStandard = $provider->generatePrivateUrl($media, 'wide');
            $candidates[] = $baseDir . $pathStandard;

            // 2. Búsqueda por NOMBRE DEL MEDIA (Slug): Nombre-producto_contexto_wide.ext
            // Tu caso: Camisetas-full-print_producto_wide.jpeg
            $nameSlug = $slugger->slug($media->getName())->lower(); // camisetas-full-print
            $filenameLegacy = sprintf('%s_%s_wide.%s', $nameSlug, $context, $ext);
            $candidates[] = $dir . '/' . $filenameLegacy;

            // 3. Variaciones (por si acaso la extensión cambia o falta el contexto)
            $candidates[] = $dir . '/' . sprintf('%s_wide.%s', $nameSlug, $ext);

            // --- INTENTO DE RESTAURACIÓN ---
            $found = false;
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    // ¡ENCONTRADO! Lo copiamos al sitio del Reference
                    if (!is_dir($dir)) mkdir($dir, 0775, true);

                    copy($candidate, $absRef);
                    $io->text("RECUPERADO: " . $media->getName() . " -> " . basename($candidate));
                    $stats['restored']++;
                    $found = true;
                    break; // Dejamos de buscar
                }
            }

            if (!$found) {
                // $io->warning("No encontrado: " . $media->getName());
                $stats['missing']++;
            }
        }

        $io->success(sprintf('Fin. Recuperados: %d. Perdidos: %d', $stats['restored'], $stats['missing']));
        return Command::SUCCESS;
    }
}