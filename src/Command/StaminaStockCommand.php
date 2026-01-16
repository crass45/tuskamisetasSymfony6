<?php

namespace App\Command;

use App\Entity\Proveedor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:actualizar-stock-stamina',
    description: 'Obtiene y actualiza el stock de Stamina desde CSV remoto (SF6).'
)]
class StaminaStockCommand extends Command
{
    // --- Configuración API ---
    private static string $API_URL = "https://stamina-shop.com/mvc/Download/StockStm?user=tuskamisetas@gmail.com&pass=hb2GxMgQ&store=01";
    private static string $NOMBRE_PROVEEDOR = "Stamina"; // Asegúrate que coincide con tu BBDD

    private EntityManagerInterface $em;
    private ?Proveedor $proveedor = null;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '1G'); // Memoria extra para CSV grandes

        $inicioScript = microtime(true);
        $output->writeln('<info>--- INICIO ACTUALIZACIÓN STOCK STAMINA (SF6) ---</info>');

        try {
            // 1. Obtener Proveedor
            $this->proveedor = $this->getOrCreateProveedor();
            $output->writeln("Proveedor detectado: " . $this->proveedor->getNombre());

            // 2. Descargar CSV
            $output->writeln('Descargando fichero CSV...');
            $inicioDescarga = microtime(true);
            $fileHandle = $this->getCleanRemoteFileStream(self::$API_URL);
            $finDescarga = microtime(true);

            if (!$fileHandle) {
                $output->writeln('<error>Error descargando CSV.</error>');
                return Command::FAILURE;
            }

            $output->writeln("Descarga OK en " . round($finDescarga - $inicioDescarga, 2) . " seg.");

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

    /**
     * Descarga y limpia el fichero CSV en memoria
     * @return resource|false
     */
    private function getCleanRemoteFileStream(string $url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $fileContents = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($fileContents === false || $httpCode !== 200) {
            return false;
        }

        // Limpieza de saltos de línea (Windows/Unix)
        $lines = preg_split('/\\r\\n|\\r|\\n/', $fileContents);
        $cleanLines = array_filter($lines, fn($line) => !empty(trim($line)));
        $cleanContents = implode("\n", $cleanLines);

        $cleanFileHandle = fopen('php://temp', 'r+');
        fwrite($cleanFileHandle, $cleanContents);
        rewind($cleanFileHandle);

        return $cleanFileHandle;
    }

    private function processCsv(OutputInterface $output, $fileHandle): void
    {
        $conn = $this->em->getConnection();

        // --- PASO 1: Reseteo Previo ---
        $output->writeln('Reseteando stock antiguo...');
        // Usamos JOIN nativo para mayor velocidad
        $sqlReset = 'UPDATE producto p 
                     INNER JOIN modelo m ON p.modelo = m.id 
                     SET p.stock = 0, p.stock_futuro = NULL 
                     WHERE m.proveedor = :proveedorId';

        $conn->executeStatement($sqlReset, ['proveedorId' => $this->proveedor->getId()]);
        $output->writeln('Reseteo completado.');

        // --- PASO 2: Actualización ---
        $output->writeln('Procesando CSV y actualizando BBDD...');

        // Sentencia UPDATE preparada
        $sql = "UPDATE producto SET stock = :stock, stock_futuro = :stockFuturo WHERE referencia = :referencia";
        $stmt = $conn->prepare($sql);

        $lineaProcesada = 0;
        $nActualizaciones = 0;
        $batchSize = 500;
        $inicioLote = microtime(true);

        $conn->beginTransaction();

        try {
            // Descomentar la siguiente línea si el CSV tiene cabecera
            // fgetcsv($fileHandle, 0, ";");

            while (($data = fgetcsv($fileHandle, 0, ";")) !== false) {

                // Validar estructura básica (Ref; Desc; Stock; StockFuturo)
                if (count($data) < 4 || empty(trim($data[0]))) {
                    continue;
                }

                $productoReferencia = trim($data[0]);
                $stock = (int) $data[2];
                $stockFuturo = trim($data[3]);

                $stmt->bindValue('stock', $stock);
                $stmt->bindValue('stockFuturo', $stockFuturo);
                $stmt->bindValue('referencia', $productoReferencia);

                $result = $stmt->executeStatement();

                if ($result > 0) {
                    $nActualizaciones++;
                }

                $lineaProcesada++;

                // Commit por lotes
                if (($lineaProcesada % $batchSize) === 0) {
                    $conn->commit();

                    $finLote = microtime(true);
                    $tiempoLote = round($finLote - $inicioLote, 2);
                    $prodSeg = round($batchSize / $tiempoLote, 2);
                    $output->writeln("Lote procesado. Total: {$lineaProcesada}. Vel: ~{$prodSeg} prod/seg.");

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
        $output->writeln("Productos actualizados: {$nActualizaciones}");
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