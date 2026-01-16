<?php

namespace App\Command;

use App\Entity\Proveedor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:actualizar-stock-jhk',
    description: 'Actualiza el stock de JHK sumando almacenes Madrid y Cubo (SF6).'
)]
class JhkStockCommand extends Command
{
    // --- Configuración ---
    private static string $URL_MADRID = 'https://www.jhktshirt.com/stock/es/depositos/downloadstockfiles?customer=CL011410&code=apSw05W8q9yXbG%2BkjmCkiZWVcGtvvuXdosao6g%3D%3D';
    private static string $URL_CUBO = 'https://www.jhktshirt.com/stock/es/depositos/downloadstockfiles?customer=CL011410&code=Z4uwoJfNm%2BKgk5nTq5StkqSwcGJmjqeWbpKd4qqgo9C5';

    private static string $NOMBRE_PROVEEDOR = "JHK"; // Asegúrate de que coincide con tu BBDD

    private EntityManagerInterface $em;
    private ?Proveedor $proveedor = null;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '1G');

        $inicioScript = microtime(true);
        $output->writeln('<info>--- INICIO ACTUALIZACIÓN STOCK JHK (SF6) ---</info>');

        try {
            // 1. Obtener Proveedor
            $this->proveedor = $this->getOrCreateProveedor();
            $output->writeln("Proveedor detectado: " . $this->proveedor->getNombre());

            // 2. Descargar y Combinar Datos
            $output->writeln('Descargando y procesando CSVs...');
            $inicioDescarga = microtime(true);

            $stockCombinado = $this->fetchAndCombineStock($output);

            $finDescarga = microtime(true);
            $output->writeln("Procesamiento de datos completado en " . round($finDescarga - $inicioDescarga, 2) . " seg.");
            $output->writeln("Referencias únicas encontradas: " . count($stockCombinado));

            if (empty($stockCombinado)) {
                $output->writeln('<comment>No se encontraron datos de stock para procesar.</comment>');
                return Command::SUCCESS;
            }

            // 3. Actualizar BBDD
            $this->updateDatabase($output, $stockCombinado);

        } catch (\Exception $e) {
            $output->writeln('<error>ERROR CRÍTICO: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $finScript = microtime(true);
        $output->writeln('<info>--- PROCESO FINALIZADO ---</info>');
        $output->writeln("Tiempo Total: " . round($finScript - $inicioScript, 2) . " seg.");

        return Command::SUCCESS;
    }

    private function fetchAndCombineStock(OutputInterface $output): array
    {
        // Descargar ambos CSVs
        $csvMadrid = $this->getRemoteFileContents(self::$URL_MADRID);
        $csvCubo = $this->getRemoteFileContents(self::$URL_CUBO);

        if ($csvMadrid === false || $csvCubo === false) {
            throw new \Exception("Fallo al descargar uno o ambos ficheros CSV de JHK.");
        }

        $stockCombinado = [];

        // Procesar Madrid
        $this->processCsvContent($csvMadrid, $stockCombinado);

        // Procesar Cubo (sumando al anterior)
        $this->processCsvContent($csvCubo, $stockCombinado);

        return $stockCombinado;
    }

    private function getRemoteFileContents(string $url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $httpCode !== 200) {
            return false;
        }
        return $data;
    }

    private function processCsvContent(string $csvContent, array &$stockCombinado): void
    {
        $lines = preg_split('/\\r\\n|\\r|\\n/', $csvContent);

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $s = str_getcsv($line, ";");

            // JHK usa columnas separadas para la referencia (Col 1, 2 y 3)
            if (count($s) > 4) {
                $referencia = trim(($s[1] ?? '') . ($s[2] ?? '') . ($s[3] ?? ''));

                if (!empty($referencia)) {
                    $stock = (int) ($s[4] ?? 0);

                    // Sumamos al stock existente si ya estaba la referencia
                    $stockCombinado[$referencia] = ($stockCombinado[$referencia] ?? 0) + $stock;
                }
            }
        }
    }

    private function updateDatabase(OutputInterface $output, array $stockCombinado): void
    {
        $conn = $this->em->getConnection();

        // --- PASO 1: Reseteo Previo (SQL Nativo Optimizado) ---
        $output->writeln('Reseteando stock antiguo...');

        $sqlReset = 'UPDATE producto p 
                     INNER JOIN modelo m ON p.modelo = m.id 
                     SET p.stock = 0, p.activo = 0 
                     WHERE m.proveedor = :proveedorId';

        $conn->executeStatement($sqlReset, ['proveedorId' => $this->proveedor->getId()]);
        $output->writeln('Reseteo completado.');

        // --- PASO 2: Actualización Masiva ---
        $output->writeln('Escribiendo cambios en BBDD...');

        $sql = "UPDATE producto SET stock = :stock, activo = 1 WHERE referencia = :referencia";
        $stmt = $conn->prepare($sql);

        $i = 0;
        $updatedCount = 0;
        $batchSize = 200;

        $conn->beginTransaction();

        try {
            foreach ($stockCombinado as $referencia => $stockTotal) {
                $i++;

                $stmt->bindValue('stock', $stockTotal);
                $stmt->bindValue('referencia', $referencia);

                $result = $stmt->executeStatement();

                if ($result > 0) {
                    $updatedCount++;
                }

                if (($i % $batchSize) === 0) {
                    $conn->commit();
                    $output->writeln("Lote procesado: {$i}");
                    $conn->beginTransaction();
                }
            }

            $conn->commit();
            $output->writeln("<info>Actualización completada. Total actualizados: {$updatedCount}</info>");

        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    private function getOrCreateProveedor(): Proveedor
    {
        $repo = $this->em->getRepository(Proveedor::class);
        $proveedor = $repo->findOneBy(['nombre' => self::$NOMBRE_PROVEEDOR]);

        if (!$proveedor) {
            $proveedor = new Proveedor();
            $proveedor->setNombre(self::$NOMBRE_PROVEEDOR);
            $this->em->persist($proveedor);
            $this->em->flush();
        }
        return $proveedor;
    }
}