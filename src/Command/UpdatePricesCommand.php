<?php

namespace App\Command;

use App\Entity\Producto;
use App\Entity\Modelo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:update-prices',
    description: 'Actualiza los precios de Productos y Modelos desde un CSV (Master File).',
)]
class UpdatePricesCommand extends Command
{
    private EntityManagerInterface $em;
    private string $projectDir;

    public function __construct(EntityManagerInterface $em, KernelInterface $kernel)
    {
        parent::__construct();
        $this->em = $em;
        $this->projectDir = $kernel->getProjectDir();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Nombre del archivo CSV en la carpeta raiz', 'Master CSV File 3EUT570.xlsx - Export.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fileName = $input->getArgument('file');
        $filePath = $this->projectDir . '/' . $fileName;

        if (!file_exists($filePath)) {
            $io->error("El archivo no existe: $filePath");
            return Command::FAILURE;
        }

        $io->title('Iniciando actualización de precios desde: ' . $fileName);

        // Abrir el archivo CSV
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $io->error('No se pudo abrir el archivo.');
            return Command::FAILURE;
        }

        // Repositorios
        $productoRepo = $this->em->getRepository(Producto::class);
        $modeloRepo = $this->em->getRepository(Modelo::class);

        $batchSize = 100;
        $i = 0;
        $updatedCount = 0;

        // Leer la cabecera para saltarla
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 10000, ',')) !== false) {
            // Mapeo de columnas basado en tu archivo:
            // Col 0: SKU (Referencia)
            // Col 14: Price (Catalog_3EUT570)

            $sku = trim($data[0] ?? '');
            $priceRaw = $data[14] ?? null;

            // Si no hay SKU o el precio está vacío, saltamos
            if (empty($sku) || empty($priceRaw) || !is_numeric($priceRaw)) {
                continue;
            }

            $price = (float) $priceRaw;

            // 1. Buscar en PRODUCTO (Variantes concretas, ej: EP01-BL6)
            $producto = $productoRepo->findOneBy(['referencia' => $sku]);

            if ($producto) {
                // Actualizamos el precio del producto
                // Asumimos que el campo en tu entidad Producto es 'precio' o 'price'
                // Ajusta 'setPrecio' si tu setter se llama diferente.
                if (method_exists($producto, 'setPrecio')) {
                    $producto->setPrecio($price);
                    $updatedCount++;
                    if ($output->isVerbose()) {
                        $io->text("Producto actualizado: $sku -> $price €");
                    }
                }
            } else {
                // 2. Si no es producto, buscamos en MODELO (Padres, ej: EP01)
                // A veces el master file trae el precio base del modelo
                $modelo = $modeloRepo->findOneBy(['referencia' => $sku]);
                if ($modelo) {
                    if (method_exists($modelo, 'setPrecioMin')) {
                        $modelo->setPrecioMin($price);
                        // También solemos actualizar precio adulto si aplica
                        if (method_exists($modelo, 'setPrecioMinAdulto')) {
                            $modelo->setPrecioMinAdulto($price);
                        }
                        $updatedCount++;
                        if ($output->isVerbose()) {
                            $io->text("Modelo actualizado: $sku -> $price €");
                        }
                    }
                }
            }

            $i++;
            if (($i % $batchSize) === 0) {
                $this->em->flush();
                $this->em->clear(); // Limpiar memoria
            }
        }

        fclose($handle);

        // Flush final para los restantes
        $this->em->flush();

        $io->success("Proceso finalizado. Se han actualizado $updatedCount precios.");

        return Command::SUCCESS;
    }
}