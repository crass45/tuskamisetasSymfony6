<?php

namespace App\Command;

use App\Entity\Proveedor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:actualizar-stock-cifra',
    description: 'Actualiza stock (productos) y pack/caja (modelos) de Cifra (SF6).'
)]
class CifraStockCommand extends Command
{
    // --- Configuración ---
    private static string $API_URL = "https://www.cifra.es/downloads/csv/products.csv";
    private static string $NOMBRE_PROVEEDOR = "Cifra";

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
        $output->writeln('<info>--- INICIO ACTUALIZACIÓN CIFRA (SF6) ---</info>');

        try {
            // 1. Obtener Proveedor
            $this->proveedor = $this->getOrCreateProveedor();
            $output->writeln("Proveedor detectado: " . $this->proveedor->getNombre());

            // 2. Descargar CSV
            $output->writeln('Descargando y limpiando CSV...');
            $inicioDescarga = microtime(true);

            $fileHandle = $this->getCleanRemoteFileStream(self::$API_URL);

            $finDescarga = microtime(true);
            $output->writeln("Descarga OK en " . round($finDescarga - $inicioDescarga, 2) . " seg.");

            if (!$fileHandle) {
                $output->writeln('<error>Error descargando CSV.</error>');
                return Command::FAILURE;
            }

            // 3. Procesar CSV
            $this->processCsv($output, $fileHandle);

            fclose($fileHandle);

        } catch (\Exception $e) {
            $output->writeln('<error>ERROR CRÍTICO: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $finScript = microtime(true);
        $output->writeln('<info>--- PROCESO FINALIZADO ---</info>');
        $output->writeln("Tiempo Total: " . round($finScript - $inicioScript, 2) . " seg.");

        return Command::SUCCESS;
    }

    private function getCleanRemoteFileStream(string $url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $fileContents = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($fileContents === false || $httpCode !== 200) {
            return false;
        }

        // Limpieza de saltos de línea y espacios
        $lines = preg_split('/\\r\\n|\\r|\\n/', $fileContents);
        $cleanLines = array_filter($lines, 'trim'); // Elimina líneas vacías
        $cleanContents = implode("\n", $cleanLines);

        $cleanFileHandle = fopen('php://temp', 'r+');
        fwrite($cleanFileHandle, $cleanContents);
        rewind($cleanFileHandle);

        return $cleanFileHandle;
    }

    private function processCsv(OutputInterface $output, $fileHandle): void
    {
        $conn = $this->em->getConnection();

        // --- PASO 1: Reseteo Previo (Solo Stock de Productos) ---
        $output->writeln('Reseteando stock antiguo...');
        $sqlReset = 'UPDATE producto p 
                     INNER JOIN modelo m ON p.modelo = m.id 
                     SET p.stock = 0, p.stock_futuro = NULL 
                     WHERE m.proveedor = :proveedorId';

        $conn->executeStatement($sqlReset, ['proveedorId' => $this->proveedor->getId()]);
        $output->writeln('Reseteo completado.');

        // --- PASO 2: Actualización Masiva ---
        $output->writeln('Procesando datos (Productos y Modelos)...');

        // Preparar consultas
        $sqlProducto = "UPDATE producto SET stock = :stock, stock_futuro = :futuro WHERE referencia = :ref";
        $stmtProducto = $conn->prepare($sqlProducto);

        $sqlModelo = "UPDATE modelo SET pack = :pack, box = :box WHERE referencia = :ref";
        $stmtModelo = $conn->prepare($sqlModelo);

        $lineaProcesada = 0;
        $nProdUpdated = 0;
        $nModUpdated = 0;
        $batchSize = 200;
        $inicioLote = microtime(true);
        $delimiter = "\t"; // IMPORTANTE: Cifra usa tabuladores

        $conn->beginTransaction();

        try {
            fgetcsv($fileHandle, 0, $delimiter); // Ignorar cabecera

            while (($s = fgetcsv($fileHandle, 0, $delimiter)) !== false) {
                $lineaProcesada++;

                // A. Actualizar PRODUCTO (Col 0: Ref, Col 7: Stock, Col 22: Futuro)
                if (isset($s[0], $s[7]) && !empty(trim($s[0]))) {
                    $prodRef = trim($s[0]);
                    $stock = (int) $s[7];
                    $futuro = isset($s[22]) ? trim($s[22]) : '';

                    $stmtProducto->bindValue('stock', $stock);
                    $stmtProducto->bindValue('futuro', $futuro);
                    $stmtProducto->bindValue('ref', $prodRef);

                    $res = $stmtProducto->executeStatement();
                    if ($res > 0) $nProdUpdated++;
                }

                // B. Actualizar MODELO (Col 1: Ref, Col 13: Pack, Col 14: Box)
                if (isset($s[1], $s[13], $s[14]) && !empty(trim($s[1]))) {
                    $modRef = trim($s[1]);
                    $pack = (int) $s[13];
                    $box = (int) $s[14];

                    $stmtModelo->bindValue('pack', $pack);
                    $stmtModelo->bindValue('box', $box);
                    $stmtModelo->bindValue('ref', $modRef);

                    $res = $stmtModelo->executeStatement();
                    if ($res > 0) $nModUpdated++;
                }

                // Commit por lotes
                if (($lineaProcesada % $batchSize) === 0) {
                    $conn->commit();

                    $finLote = microtime(true);
                    $tiempoLote = round($finLote - $inicioLote, 2);
                    $prodSeg = round($batchSize / $tiempoLote, 2);
                    $output->writeln("Lote procesado. Total líneas: {$lineaProcesada}. Vel: ~{$prodSeg} lineas/seg.");

                    $inicioLote = microtime(true);
                    $conn->beginTransaction();
                }
            }

            $conn->commit(); // Commit final
            $output->writeln("Transacción final completada.");

        } catch (\Exception $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        }

        $output->writeln("--- RESUMEN ---");
        $output->writeln("Líneas procesadas: {$lineaProcesada}");
        $output->writeln("Productos actualizados: {$nProdUpdated}");
        $output->writeln("Modelos actualizados (Pack/Box): {$nModUpdated}");
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