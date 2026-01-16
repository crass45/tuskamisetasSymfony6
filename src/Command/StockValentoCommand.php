<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:actualizar-stock-valento',
    description: 'Obtiene y actualiza el stock de Valento (SF6 Optimizado).'
)]
class StockValentoCommand extends Command
{
    // --- Configuración ---
    private static string $API_URL = "https://www.valento.es/rest/stock.php";
    private static string $USERNAME = "098495";
    private static string $PASSWORD = "6abf42";
    private static int $PROVEEDOR_ID = 2997;

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '2G');

        $inicioScript = microtime(true);
        $output->writeln('<info>--- INICIO ACTUALIZACIÓN STOCK VALENTO (SF6) ---</info>');

        try {
            $inicioApi = microtime(true);
            $stockData = $this->fetchStockFromApi($output);
            $finApi = microtime(true);

            $output->writeln("Tiempo API: " . round($finApi - $inicioApi, 2) . " seg.");
            $output->writeln('Referencias obtenidas: ' . count($stockData));

            if (!empty($stockData)) {
                $this->updateStockInDb($output, $stockData);
            } else {
                $output->writeln('<comment>No se recibieron datos de stock.</comment>');
            }

        } catch (\Exception $e) {
            $output->writeln('<error>ERROR CRÍTICO: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $finScript = microtime(true);
        $output->writeln('<info>--- PROCESO FINALIZADO ---</info>');
        $output->writeln("Tiempo Total: " . round($finScript - $inicioScript, 2) . " seg.");

        return Command::SUCCESS;
    }

    private function fetchStockFromApi(OutputInterface $output): array
    {
        $output->writeln('Conectando a API Valento...');

        $ch = curl_init(self::$API_URL);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, self::$USERNAME . ":" . self::$PASSWORD);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/xml"]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TusKamisetas-Bot/SF6');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // OJO: Solo para compatibilidad rápida
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $responseData = curl_exec($ch);

        if ($responseData === false) {
            throw new \Exception("Error cURL: " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Error Auth API. HTTP: {$httpCode}.");
        }

        $output->writeln('<info>API descargada correctamente.</info>');

        // Procesamiento XML
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);

        if (!$dom->loadXML($responseData)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \Exception("XML inválido. Primer error: " . ($errors[0]->message ?? 'Desconocido'));
        }
        libxml_clear_errors();

        $stockMap = [];
        $references = $dom->getElementsByTagName('reference');

        foreach ($references as $referenceNode) {
            // Buscamos <cref> y <stock> (Lógica corregida de tu script original)
            $crefNode = $referenceNode->getElementsByTagName('cref')->item(0);
            $stockNode = $referenceNode->getElementsByTagName('stock')->item(0);

            if ($crefNode && $stockNode) {
                $referencia = trim($crefNode->nodeValue);
                $stock = (int) trim($stockNode->nodeValue);

                if (!empty($referencia)) {
                    $stockMap[$referencia] = $stock;
                }
            }
        }

        return $stockMap;
    }

    private function updateStockInDb(OutputInterface $output, array $stockData): void
    {
        $output->writeln('Iniciando actualización DB...');
        $conn = $this->em->getConnection();

        $updatedCount = 0;
        $notFoundCount = 0;
        $examplesNotFound = [];

        try {
            // --- PASO 1: Reseteo Masivo (SQL Nativo Optimizado) ---
            $inicioReset = microtime(true);
            $output->writeln('Reseteando stock antiguo...');

            $conn->beginTransaction();

            // Versión optimizada con JOIN en lugar de subconsulta IN (...)
            // Asumiendo tablas 'producto' y 'modelo'
            $resetSql = 'UPDATE producto p 
                         INNER JOIN modelo m ON p.modelo = m.id 
                         SET p.stock = 0, p.activo = 0 
                         WHERE m.proveedor = :proveedorId';

            $stmtReset = $conn->prepare($resetSql);
            $stmtReset->bindValue('proveedorId', self::$PROVEEDOR_ID);
            $stmtReset->executeStatement();

            $conn->commit();

            $finReset = microtime(true);
            $output->writeln("Reseteo OK. Tiempo: " . round($finReset - $inicioReset, 2) . " seg.");

            // --- PASO 2: Actualización por lotes ---
            $output->writeln('Actualizando stock...');

            $updateSql = 'UPDATE producto SET stock = :stock, activo = 1 WHERE referencia = :sku';
            $stmtUpdate = $conn->prepare($updateSql);

            $batchSize = 200;
            $i = 0;

            $conn->beginTransaction();
            $inicioLote = microtime(true);

            foreach ($stockData as $referencia => $stock) {
                $i++;

                $stmtUpdate->bindValue('stock', $stock);
                $stmtUpdate->bindValue('sku', $referencia);
                $result = $stmtUpdate->executeStatement();

                if ($result > 0) {
                    $updatedCount++;
                } else {
                    $notFoundCount++;
                    if (count($examplesNotFound) < 3) {
                        $examplesNotFound[] = $referencia;
                    }
                }

                if (($i % $batchSize) === 0) {
                    $conn->commit();

                    $finLote = microtime(true);
                    $tiempoLote = round($finLote - $inicioLote, 2);
                    $output->writeln("Lote {$batchSize} OK. Total: {$i}. Tiempo: {$tiempoLote} seg.");

                    $inicioLote = microtime(true);
                    $conn->beginTransaction();
                }
            }

            $conn->commit();
            $output->writeln('<info>Actualización completada.</info>');

        } catch (\Exception $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        }

        $output->writeln("--- RESUMEN ---");
        $output->writeln("Actualizados: {$updatedCount}");
        if ($notFoundCount > 0) {
            $output->writeln("<comment>No encontrados: {$notFoundCount}</comment>");
            if (!empty($examplesNotFound)) {
                $output->writeln("Ejemplos perdidos: " . implode(', ', $examplesNotFound));
            }
        }
    }
}