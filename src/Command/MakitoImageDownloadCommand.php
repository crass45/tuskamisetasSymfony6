<?php

namespace App\Command;

use App\Entity\Modelo;
use App\Entity\Proveedor;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ss:download-makito-images',
    description: 'Descarga TODAS las imágenes de Makito (Modelo, Detalles, Otros, Productos y Vistas) a WebP.'
)]
class MakitoImageDownloadCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private ImageManager $imageManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '1024M');
        $output->writeln('<info>--- INICIO DE DESCARGA TOTAL MAKITO (MODO WEBP) ---</info>');

        $proveedorMakito = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => 'Makito']);
        if (!$proveedorMakito) {
            $output->writeln('<error>No se encontró el proveedor Makito en la base de datos.</error>');
            return Command::FAILURE;
        }

        $qb = $this->em->createQueryBuilder()
            ->select('m')
            ->from(Modelo::class, 'm')
            ->where('m.proveedor = :prov')
            ->andWhere('m.activo = :activo')
            ->setParameter('prov', $proveedorMakito)
            ->setParameter('activo', true);

        $iterableModelos = $qb->getQuery()->toIterable();
        $batchSize = 20; // Bajamos el batch porque ahora procesamos muchas más imágenes por ciclo
        $i = 0;

        foreach ($iterableModelos as $modelo) {
            $ref = $modelo->getReferencia();
            $output->writeln("<comment>Procesando Modelo: $ref</comment>");

            // 1. Imagen Principal del Modelo
            $this->processSingleImage($modelo, 'getUrlImage', 'setUrlImage', 'makito/modelos', $ref);

            // 2. Detalles del Modelo (Campo details_images - String separado por comas)
            $this->processMultipleImages($modelo, 'getDetailsImages', 'setDetailsImages', 'makito/detalles', $ref . '_det');

            // 3. Otras Imágenes del Modelo (Campo other_images - String separado por comas)
            $this->processMultipleImages($modelo, 'getOtherImages', 'setOtherImages', 'makito/otros', $ref . '_oth');

            // 4. Procesar Productos de este modelo (Variaciones)
            foreach ($modelo->getProductos() as $producto) {
                if ($producto->isActivo()) {
                    $pRef = $producto->getReferencia();

                    // Imagen principal del producto
                    $this->processSingleImage($producto, 'getUrlImage', 'setUrlImage', 'makito/productos', $pRef);

                    // Vistas del producto (Campo views_images - String separado por comas)
                    $this->processMultipleImages($producto, 'getViewsImages', 'setViewsImages', 'makito/vistas', $pRef . '_v');
                }
            }

            $this->em->persist($modelo);

            if ((++$i % $batchSize) === 0) {
                $output->writeln("<info>Guardando lote y liberando memoria (Registro $i)...</info>");
                $this->em->flush();
                $this->em->clear();
                $proveedorMakito = $this->em->getRepository(Proveedor::class)->find($proveedorMakito->getId());
            }
        }

        $this->em->flush();
        $this->em->clear();

        $output->writeln('<info>--- PROCESO MAKITO FINALIZADO CON ÉXITO ---</info>');
        return Command::SUCCESS;
    }

    /**
     * Procesa una sola imagen (URL simple)
     */
    private function processSingleImage($entity, string $getter, string $setter, string $folder, string $filename): void
    {
        $url = $entity->$getter();
        if (!empty($url) && str_starts_with($url, 'http')) {
            $newPath = $this->imageManager->download($url, $folder, $filename);
            if (str_starts_with($newPath, '/uploads')) {
                $entity->$setter($newPath);
            }
        }
    }

    /**
     * Procesa múltiples imágenes separadas por comas
     */
    private function processMultipleImages($entity, string $getter, string $setter, string $folder, string $prefix): void
    {
        $fieldValue = $entity->$getter();
        if (!empty($fieldValue) && str_contains($fieldValue, 'http')) {
            $urls = explode(',', $fieldValue);
            $newPaths = [];
            foreach ($urls as $index => $url) {
                $url = trim($url);
                if (str_starts_with($url, 'http')) {
                    $newPaths[] = $this->imageManager->download($url, $folder, $prefix . '_' . $index);
                } else {
                    $newPaths[] = $url;
                }
            }
            $entity->$setter(implode(',', $newPaths));
        }
    }
}