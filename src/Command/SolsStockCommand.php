<?php

namespace App\Command;

use App\Entity\Proveedor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:actualizar-stock-sols',
    description: 'Actualiza el stock y stock futuro de SOLS vía FTP (SF6).'
)]
class SolsStockCommand extends Command
{
    // --- Configuración FTP ---
    private static string $FTP_SERVER = 'csv.sols.es';
    private static string $FTP_USER = 'integraciones';
    private static string $FTP_PASS = '**Int3gr4c10n3s++'; // Revisa si esta es la correcta
    private static string $FILE_STOCK = 'stocks/stocks.csv';
    private static string $FILE_FUTURE = 'stocks/shipments.csv';

    private static string $NOMBRE_PROVEEDOR = "Sols"; // Ajusta según tu BBDD

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
        $output->writeln('<info>--- INICIO ACTUALIZACIÓN STOCK SOLS (SF6) ---</info>');

        try {
            // 1. Obtener Proveedor
            $this->proveedor = $this->getOrCreateProveedor();
            $output->writeln("Proveedor detectado: " . $this->proveedor->getNombre());

            // 2. Conexión y Descarga FTP
            $output->writeln('Conectando a FTP...');
            $inicioDescarga = microtime(true);

            [$stockContent, $futureContent] = $this->downloadFromFtp($output);

            $finDescarga = microtime(true);
            $output->writeln("Descarga FTP completada en " . round($finDescarga - $inicioDescarga, 2) . " seg.");

            if (!$stockContent || !$futureContent) {
                $output->writeln('<error>Error: Fallo al descargar ficheros.</error>');
                return Command::FAILURE;
            }

            // 3. Procesamiento en Memoria (Combinar Stock + Futuro)
            $output->writeln('Procesando y combinando datos...');
            $productosData = $this->parseAndCombineCsv($stockContent, $futureContent);
            $output->writeln("Productos únicos a procesar: " . count($productosData));

            // 4. Actualización en BBDD
            $this->updateDatabase($output, $productosData);

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
     * Descarga ambos ficheros del FTP
     * @return array [contenido_stock, contenido_futuro]
     */
    private function downloadFromFtp(OutputInterface $output): array
    {
        $connId = ftp_connect(self::$FTP_SERVER, 21, 300);
        if (!$connId) {
            throw new \Exception("No se pudo conectar al servidor FTP " . self::$FTP_SERVER);
        }

        if (!ftp_login($connId, self::$FTP_USER, self::$FTP_PASS)) {
            ftp_close($connId);
            throw new \Exception("Credenciales FTP incorrectas.");
        }

        ftp_pasv($connId, true);

        $output->writeln('Descargando ' . self::$FILE_STOCK . '...');
        $stockContent = $this->getFtpFileContents($connId, self::$FILE_STOCK);

        $output->writeln('Descargando ' . self::$FILE_FUTURE . '...');
        $futureContent = $this->getFtpFileContents($connId, self::$FILE_FUTURE);

        ftp_close($connId);

        return [$stockContent, $futureContent];
    }

    private function getFtpFileContents($connId, string $serverFile)
    {
        $tempHandle = fopen('php://temp', 'r+');
        if (!$tempHandle) return false;

        if (ftp_fget($connId, $tempHandle, $serverFile, FTP_ASCII)) {
            rewind($tempHandle);
            $contents = stream_get_contents($tempHandle);
            fclose($tempHandle);
            return $contents;
        }

        fclose($tempHandle);
        return false;
    }

    private function parseAndCombineCsv(string $stockCsv, string $futureCsv): array
    {
        $productosData = [];

        // 1. Procesar Stock Actual
        $lines = preg_split('/\\r\\n|\\r|\\n/', $stockCsv);
        $isFirstLine = true;
        foreach ($lines as $line) {
            if ($isFirstLine || empty(trim($line))) {
                $isFirstLine = false;
                continue;
            }

            $data = str_getcsv($line, ",");
            if (count($data) >= 3) {
                $ref = trim($data[0]);
                if (!empty($ref)) {
                    $stock = (int)($data[1] ?? 0) + (int)($data[2] ?? 0);
                    $productosData[$ref] = ['stock' => $stock, 'stock_futuro' => ''];
                }
            }
        }

        // 2. Procesar Stock Futuro
        $lines = preg_split('/\\r\\n|\\r|\\n/', $futureCsv);
        $isFirstLine = true;
        foreach ($lines as $line) {
            if ($isFirstLine || empty(trim($line))) {
                $isFirstLine = false;
                continue;
            }

            $data = str_getcsv($line, ",");
            if (count($data) >= 3) {
                $ref = trim($data[0]);
                if (!empty($ref)) {
                    $stockFuturo = trim($data[2]);

                    if (isset($productosData[$ref])) {
                        $productosData[$ref]['stock_futuro'] = $stockFuturo;
                    } else {
                        // Si no existe en stock actual, lo creamos con stock 0
                        $productosData[$ref] = ['stock' => 0, 'stock_futuro' => $stockFuturo];
                    }
                }
            }
        }

        return $productosData;
    }

    private function updateDatabase(OutputInterface $output, array $productosData): void
    {
        $conn = $this->em->getConnection();

        // --- Reseteo Previo (Opcional) ---
        $output->writeln('Reseteando stock antiguo...');
        $sqlReset = 'UPDATE producto p 
                     INNER JOIN modelo m ON p.modelo = m.id 
                     SET p.stock = 0, p.stock_futuro = NULL 
                     WHERE m.proveedor = :proveedorId';

        $conn->executeStatement($sqlReset, ['proveedorId' => $this->proveedor->getId()]);

        // --- Actualización Masiva ---
        $output->writeln('Escribiendo cambios en BBDD...');

        $sql = "UPDATE producto SET stock = :stock, stock_futuro = :stockFuturo WHERE referencia = :referencia";
        $stmt = $conn->prepare($sql);

        $i = 0;
        $updatedCount = 0;
        $batchSize = 200;

        $conn->beginTransaction();

        try {
            foreach ($productosData as $referencia => $info) {
                $i++;

                $stmt->bindValue('stock', $info['stock']);
                $stmt->bindValue('stockFuturo', $info['stock_futuro']);
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