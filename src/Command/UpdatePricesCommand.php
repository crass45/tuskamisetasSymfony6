<?php

namespace App\Command;

use App\Entity\Producto;
use App\Entity\Modelo;
use App\Entity\Proveedor;
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
    description: 'Importa precios de un CSV y ajusta precios mínimos de modelos',
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
            ->addArgument('file', InputArgument::REQUIRED, 'Nombre del archivo CSV (ej: tarifajhk2026.csv)')
            ->addArgument('proveedorId', InputArgument::REQUIRED, 'ID del proveedor para el ajuste final');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fileName = $input->getArgument('file');
        $proveedorId = (int) $input->getArgument('proveedorId');
        $filePath = $this->projectDir . '/' . $fileName;

        if (!file_exists($filePath)) {
            $io->error("Archivo no encontrado: $filePath");
            return Command::FAILURE;
        }

        // --- FASE 1: IMPORTACIÓN DESDE CSV ---
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle, 0, ";");
        if (!$header) {
            $io->error("El archivo está vacío o mal formateado");
            return Command::FAILURE;
        }

        $header = array_map(fn($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $header);
        $refIndex = array_search('REF', $header);
        $precioIndex = array_search('PRECIO', $header);

        if ($refIndex === false || $precioIndex === false) {
            $io->error("No se han encontrado las columnas 'REF' y 'PRECIO'");
            return Command::FAILURE;
        }

        $io->section('Paso 1: Importando precios desde CSV...');

        $updated = 0;
        $count = 0;
        $productoRepo = $this->em->getRepository(Producto::class);

        while (($data = fgetcsv($handle, 0, ";")) !== false) {
            $sku = trim($data[$refIndex] ?? '');
            if (empty($sku)) continue;

            $price = (float) str_replace(',', '.', $data[$precioIndex] ?? '0');
            $producto = $productoRepo->findOneBy(['referencia' => $sku]);

            if ($producto) {
                $producto->setPrecioCaja($price);
                $producto->setPrecioUnidad($price);
                $producto->setPrecioPack($price);
                $updated++;
            }

            $count++;
            if ($count % 100 === 0) {
                $this->em->flush();
            }
        }
        $this->em->flush();
        fclose($handle);

        $io->success("Importación terminada. Filas: $count. Productos actualizados: $updated.");

        // --- FASE 2: AJUSTE DE PRECIOS MÍNIMOS ---
        $io->section('Paso 2: Ajustando precios mínimos de modelos...');
        $this->ajustarPreciosFinales($output, $proveedorId);

        return Command::SUCCESS;
    }

    private function ajustarPreciosFinales(OutputInterface $output, int $proveedorId)
    {
        $output->writeln("AJUSTANDO PRECIOS MÍNIMOS DE MODELOS...");

        $proveedor = $this->em->getRepository(Proveedor::class)->find($proveedorId);

        if ($proveedor) {
            $query = $this->em->getRepository(Modelo::class)->createQueryBuilder('m')
                ->where('m.proveedor = :prov')
                ->andWhere('m.activo = :activo')
                ->setParameter('prov', $proveedor)
                ->setParameter('activo', true)
                ->getQuery();

            $countAjuste = 0;
            $batchSizeAjuste = 100;

            foreach ($query->toIterable() as $modeloAjuste) {
                try {
                    // Recargar el proveedor si el EM se limpió en el loop anterior
                    if (!$this->em->contains($proveedor)) {
                        $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                    }

                    $modeloAjuste->setProveedor($proveedor);

                    $precioMinimo = $modeloAjuste->getPrecioUnidad();
                    $modeloAjuste->setPrecioMin($precioMinimo ?? 0);

                    // Lógica de cálculo de precio mínimo adulto
                    if ($modeloAjuste->getPrecioCantidadBlancas(10000) > 0) {
                        $modeloAjuste->setPrecioMinAdulto($modeloAjuste->getPrecioCantidadBlancas(10000));
                    } else {
                        if ($modeloAjuste->getPrecioCantidadBlancasNino(10000) > 0) {
                            $modeloAjuste->setPrecioMinAdulto($modeloAjuste->getPrecioCantidadBlancasNino(10000));
                        } else {
                            $modeloAjuste->setPrecioMinAdulto($modeloAjuste->getPrecioUnidad());
                        }
                    }

                    $this->em->persist($modeloAjuste);
                    $countAjuste++;

                    if ($countAjuste % $batchSizeAjuste === 0) {
                        $output->writeln("...precios mínimos ajustados: $countAjuste...");
                        $this->em->flush();
                        $this->em->clear();
                    }
                } catch (\Exception $e) {
                    $output->writeln('<error>Excepcion al ajustar precio mínimo: ' . $e->getMessage() . '</error>');
                    if (!$this->em->isOpen()) { return; }
                    $this->em->clear();
                }
            }
            $this->em->flush();
            $this->em->clear();
            $output->writeln("<info>AJUSTE DE PRECIOS MÍNIMOS TERMINADO.</info>");
        } else {
            $output->writeln("<error>Proveedor ID $proveedorId no encontrado para ajuste final.</error>");
        }
    }
}