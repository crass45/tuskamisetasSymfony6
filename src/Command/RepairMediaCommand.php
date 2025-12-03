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

#[AsCommand(
    name: 'app:media:repair-missing-reference',
    description: 'Restaura archivos reference perdidos usando la miniatura wide como copia.',
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
        $io->title('Reparando imágenes sin Reference...');

        $medias = $this->em->getRepository(Media::class)->findAll();
        $baseDir = $this->params->get('kernel.project_dir') . '/public/uploads/media/';
        $restored = 0;
        $missing = 0;

        foreach ($medias as $media) {
            if ($media->getProviderName() !== 'sonata.media.provider.image') {
                continue;
            }

            $provider = $this->mediaPool->getProvider($media->getProviderName());

            // Rutas relativas
            $pathReference = $provider->generatePrivateUrl($media, 'reference');
            // OJO: Sonata añade 'thumb_' y el id al nombre de las miniaturas
            // Formato estándar: contexto/0001/01/thumb_ID_FORMATO.ext
            $pathWide = $provider->generatePrivateUrl($media, 'wide');

            // Rutas absolutas
            $absRef = $baseDir . $pathReference;
            $absWide = $baseDir . $pathWide;

            // Comprobamos si falta el original
            if (!file_exists($absRef)) {

                // Buscamos si existe el 'wide' para usarlo de recambio
                if (file_exists($absWide)) {
                    copy($absWide, $absRef);
                    $io->text("Restaurado: " . $media->getName() . " (desde wide)");
                    $restored++;
                } else {
                    $io->warning("Falta original y wide para: " . $media->getName() . " (ID: " . $media->getId() . ")");
                    $missing++;
                }
            }
        }

        $io->success(sprintf('Proceso finalizado. Restaurados: %d. Irrecuperables: %d.', $restored, $missing));

        return Command::SUCCESS;
    }
}