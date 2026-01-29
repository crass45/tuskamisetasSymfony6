<?php

namespace App\Command;

use App\Entity\Modelo;
use App\Entity\Producto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'ss:optimize-old-images',
    description: 'Optimiza imágenes JPG/PNG a WebP en carpetas específicas de modelos y productos.'
)]
class OptimizeExistingImagesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $projectDir,
        private Filesystem $filesystem
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>--- OPTIMIZACIÓN DE RUTA DE RECURSOS Y UPLOADS ---</info>');

        // IMPORTANTE: Usamos 'urlImage' (nombre de la propiedad en la entidad), NO 'url_image'

        // 1. Modelos
        $this->processTable(
            Modelo::class,
            ['/resources/images/', '/uploads/media/modelo/'],
            'urlImage',
            $output
        );

        // 2. Productos
        $this->processTable(
            Producto::class,
            ['/uploads/media/producto/'],
            'urlImage',
            $output
        );

        $output->writeln("\n<info>--- PROCESO COMPLETADO ---</info>");
        return Command::SUCCESS;
    }

    private function processTable(string $entityClass, array $targetPaths, string $field, OutputInterface $output): void
    {
        $entityName = (new \ReflectionClass($entityClass))->getShortName();
        $output->writeln("\n<comment>> Revisando $entityName...</comment>");

        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from($entityClass, 'e');

        $orStatements = $qb->expr()->orX();
        foreach ($targetPaths as $key => $path) {
            $orStatements->add($qb->expr()->like("e.$field", ":path$key"));
            $qb->setParameter("path$key", $path . '%');
        }

        $qb->andWhere($orStatements)
            // Corregido: Usamos un parámetro para evitar el error de sintaxis del '%'
            ->andWhere($qb->expr()->notLike("e.$field", ":ext"))
            ->setParameter('ext', '%.webp');

        $results = $qb->getQuery()->getResult();
        $count = 0;

        foreach ($results as $item) {
            // Convertimos urlImage a getUrlImage y setUrlImage correctamente
            $getter = 'get' . ucfirst($field);
            $setter = 'set' . ucfirst($field);

            $relativePath = $item->$getter();

            if (!$relativePath) continue;

            $fullPath = $this->projectDir . '/public' . $relativePath;

            if ($this->filesystem->exists($fullPath)) {
                $newRelativePath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $relativePath);
                $newFullPath = $this->projectDir . '/public' . $newRelativePath;

                if ($this->convertAndSave($fullPath, $newFullPath)) {
                    $item->$setter($newRelativePath);

                    // Solo eliminamos el original si la ruta es distinta
                    if ($fullPath !== $newFullPath) {
                        $this->filesystem->remove($fullPath);
                    }

                    $count++;

                    if ($count % 25 === 0) {
                        $this->em->flush();
                        $output->writeln("  Procesadas $count imágenes...");
                    }
                }
            }
        }

        $this->em->flush();
        $output->writeln("<info>  [OK] $count imágenes optimizadas en $entityName.</info>");
    }

    private function convertAndSave(string $source, string $destination): bool
    {
        $info = @getimagesize($source);
        if (!$info) return false;

        $image = match($info['mime'] ?? '') {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png'  => @imagecreatefrompng($source),
            'image/webp' => @imagecreatefromwebp($source),
            default      => null,
        };

        if (!$image) return false;

        if (imagesx($image) > 1200) {
            $image = imagescale($image, 1200);
        }

        $success = imagewebp($image, $destination, 80);
        imagedestroy($image);

        return $success;
    }
}