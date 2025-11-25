<?php

namespace App\Command;

use App\Entity\Modelo;
use App\Entity\Producto;
use App\Entity\Proveedor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'ss:download-makito-images',
    description: 'Descarga imágenes de productos y modelos de Makito que estén enlazadas a URLs externas.'
)]
class MakitoImageDownloadCommand extends Command
{
    private EntityManagerInterface $em;
    private HttpClientInterface $httpClient;
    private Filesystem $filesystem;
    private string $publicDir;
    private ?Proveedor $proveedorMakito;

    public function __construct(
        EntityManagerInterface $em,
        HttpClientInterface $httpClient,
        Filesystem $filesystem,
        KernelInterface $kernel
    ) {
        parent::__construct();
        $this->em = $em;
        $this->httpClient = $httpClient;
        $this->filesystem = $filesystem;
        $this->publicDir = $kernel->getProjectDir() . '/public';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '-1');
        $output->writeln('<info>--- INICIO DE DESCARGA DE IMÁGENES MAKITO ---</info>');

        $this->proveedorMakito = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => 'Makito']);
        if (!$this->proveedorMakito) {
            $output->writeln('<error>No se encontró el proveedor "Makito". Abortando.</error>');
            return Command::FAILURE;
        }

        $this->processModelos($output);
        $this->processProductos($output);

        $output->writeln('<info>--- FIN DE LA DESCARGA ---</info>');
        return Command::SUCCESS;
    }

    private function processModelos(OutputInterface $output)
    {
        $output->writeln('Procesando imágenes de Modelos...');
        $batchSize = 50;
        $i = 0;

        $modelos = $this->em->getRepository(Modelo::class)->findBy(['proveedor' => $this->proveedorMakito]);

        foreach ($modelos as $modelo) {
            $imageUrl = $modelo->getUrlImage();

            // Si está vacío o ya es una ruta local (no empieza con http), lo saltamos.
            if (empty($imageUrl) || !str_starts_with($imageUrl, 'http')) {
                continue;
            }

            // Definimos la nueva ruta local
            $localPath = "/uploads/images/makito/modelos/" . $modelo->getReferencia() . ".jpg";

            if ($newPath = $this->downloadImage($imageUrl, $localPath, $output)) {
                $modelo->setUrlImage($newPath);
                $this->em->persist($modelo);
                $output->writeln("  <info>OK</info> -> " . $modelo->getReferencia());
            } else {
                $output->writeln("  <error>FAIL</error> -> " . $modelo->getReferencia() . " (URL: $imageUrl)");
            }

            // Flush y clear por lotes para liberar memoria
            if (++$i % $batchSize === 0) {
                $output->writeln("...guardando lote de modelos ($i)...");
                $this->em->flush();
                $this->em->clear();
                // Recargamos el proveedor
                $this->proveedorMakito = $this->em->getRepository(Proveedor::class)->find($this->proveedorMakito->getId());
            }
        }

        $output->writeln("...guardando modelos restantes...");
        $this->em->flush();
        $this->em->clear();
    }

    private function processProductos(OutputInterface $output)
    {
        $output->writeln('Procesando imágenes de Productos (variaciones)...');
        $batchSize = 50;
        $i = 0;

        // Recargamos el proveedor por si el clear() anterior se lo llevó
        $this->proveedorMakito = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => 'Makito']);

        // Query para buscar productos del proveedor Makito
        $qb = $this->em->getRepository(Producto::class)->createQueryBuilder('p');
        $qb->join('p.modelo', 'm')
            ->where('m.proveedor = :proveedor')
            ->setParameter('proveedor', $this->proveedorMakito);

        $iterableProductos = $qb->getQuery()->toIterable();

        foreach ($iterableProductos as $producto) {
            $imageUrl = $producto->getUrlImage();

            if (empty($imageUrl) || !str_starts_with($imageUrl, 'http')) {
                continue;
            }

            $localPath = "/uploads/images/makito/productos/" . $producto->getReferencia() . ".jpg";

            if ($newPath = $this->downloadImage($imageUrl, $localPath, $output)) {
                $producto->setUrlImage($newPath);
                $this->em->persist($producto);
                $output->writeln("  <info>OK</info> -> " . $producto->getReferencia());
            } else {
                $output->writeln("  <error>FAIL</error> -> " . $producto->getReferencia() . " (URL: $imageUrl)");
            }

            if (++$i % $batchSize === 0) {
                $output->writeln("...guardando lote de productos ($i)...");
                $this->em->flush();
                $this->em->clear();
                // Recargamos el proveedor
                $this->proveedorMakito = $this->em->getRepository(Proveedor::class)->find($this->proveedorMakito->getId());
            }
        }

        $output->writeln("...guardando productos restantes...");
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * Función helper para descargar la imagen.
     */
    private function downloadImage(string $url, string $localRelativePath, OutputInterface $output): ?string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $output->writeln("<comment>URL no válida: {$url}</comment>");
            return null;
        }

        $fullSavePath = $this->publicDir . $localRelativePath;

        // Opcional: Si ya existe, no lo descargamos
        if ($this->filesystem->exists($fullSavePath)) {
            return $localRelativePath;
        }

        $directory = dirname($fullSavePath);
        if (!$this->filesystem->exists($directory)) {
            try {
                $this->filesystem->mkdir($directory);
            } catch (\Exception $e) {
                $output->writeln("<error>No se pudo crear dir: {$directory}</error>");
                return null;
            }
        }

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 20]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $this->filesystem->dumpFile($fullSavePath, $response->getContent());
            return $localRelativePath;

        } catch (\Exception $e) {
            // $output->writeln("<error>Excepción al descargar {$url}: " . $e->getMessage() . "</error>");
            return null;
        }
    }
}