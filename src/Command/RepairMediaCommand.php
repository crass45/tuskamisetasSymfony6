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
    description: 'Restores missing reference files using exhaustive brute-force search of wide thumbnails.',
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
        $io->title('Repairing images (Smart Name Search)...');

        $medias = $this->em->getRepository(Media::class)->findAll();
        $baseDir = $this->params->get('kernel.project_dir') . '/public/uploads/media/';
        $slugger = new AsciiSlugger();

        $stats = ['restored' => 0, 'missing' => 0];
        $firstErrorShown = false;

        foreach ($medias as $media) {
            if ($media->getProviderName() !== 'sonata.media.provider.image') {
                continue;
            }

            $provider = $this->mediaPool->getProvider($media->getProviderName());

            // 1. Expected location of ORIGINAL (Reference)
            $pathRef = $provider->generatePrivateUrl($media, 'reference');
            $absRef = $baseDir . $pathRef;

            if (file_exists($absRef)) {
                continue;
            }

            // --- CANDIDATE GENERATION ---
            $candidates = [];

            // Clean up the name: remove extension if present
            $nameOriginal = $media->getName();
            $nameClean = preg_replace('/\.[^.]+$/', '', $nameOriginal); // Remove .jpg, .png etc from end

            // Variations of the name
            $nameVariations = [
                $nameClean,                                 // Exact name (e.g. Camisetas-full-print)
                $slugger->slug($nameClean)->toString(),     // Slugified (Camisetas-full-print)
                $slugger->slug($nameClean)->lower()->toString(), // Lowercase slug (camisetas-full-print)
                str_replace('-', ' ', $nameClean)           // Spaces instead of dashes
            ];
            $nameVariations = array_unique($nameVariations);

            // Extensions to try
            $extDb = pathinfo($media->getProviderReference(), PATHINFO_EXTENSION);
            $extensions = array_unique([$extDb, 'jpeg', 'jpg', 'png']);

            // Contexts to check (folders and suffixes)
            $contexts = array_unique([
                $media->getContext(), // DB context
                'producto',
                'product',
                'default'
            ]);

            // Determine the ID folder structure (e.g. 0001/01) from the reference path
            $pathParts = explode('/', $pathRef);
            $idFolder = (count($pathParts) >= 4) ? $pathParts[1] . '/' . $pathParts[2] : '';

            foreach ($contexts as $ctxFolder) {
                foreach ($contexts as $ctxSuffix) {
                    foreach ($extensions as $ext) {
                        foreach ($nameVariations as $name) {
                            // Strategy 1: Context in filename (e.g. Name_producto_wide.jpg)
                            $filenameA = sprintf('%s_%s_wide.%s', $name, $ctxSuffix, $ext);
                            $candidates[] = $baseDir . $ctxFolder . '/' . $idFolder . '/' . $filenameA;

                            // Strategy 2: No context in filename (e.g. Name_wide.jpg)
                            $filenameB = sprintf('%s_wide.%s', $name, $ext);
                            $candidates[] = $baseDir . $ctxFolder . '/' . $idFolder . '/' . $filenameB;
                        }
                    }
                }
            }

            // --- SEARCH ---
            $foundPath = null;
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $foundPath = $candidate;
                    break;
                }
            }

            if ($foundPath) {
                // FOUND! Restore it
                $destDir = dirname($absRef);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0775, true);
                }

                copy($foundPath, $absRef);
                $io->text(sprintf("RESTORED: %s \n   Source: %s", $media->getName(), basename($foundPath)));
                $stats['restored']++;
            } else {
                $stats['missing']++;

                // DEBUG: Show first failure details
                if (!$firstErrorShown) {
                    $io->warning("DEBUG FAILURE - Tested paths for: " . $media->getName());
                    // Show first 10 candidates
                    $io->listing(array_slice($candidates, 0, 10));
                    $firstErrorShown = true;
                }
            }
        }

        $io->success(sprintf('FINISHED. Restored: %d. Missing: %d', $stats['restored'], $stats['missing']));
        return Command::SUCCESS;
    }
}