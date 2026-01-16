<?php

namespace App\Command;

use App\Entity\Proveedor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'app:actualizar-stock-stanley',
    description: 'Obtiene y actualiza el stock de Stanley Stella (Optimizado SF6).'
)]
class StanleyStellaStockCommand extends Command
{
    private static string $API_URL = 'https://api.stanleystella.com/webrequest/v2/stock/get_json';
    private static string $NOMBRE_PROVEEDOR = "Stanley Stella";
    private static string $API_USER = "tuskamisetas@gmail.com";
    private static string $API_PASSWORD = "Uni17Cam";

    private EntityManagerInterface $em;
    private CacheInterface $cache;
    private ?Proveedor $proveedor = null;

    public function __construct(EntityManagerInterface $em, CacheInterface $cache)
    {
        $this->em = $em;
        $this->cache = $cache;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '2G');

        $inicioScript = microtime(true);
        $output->writeln('<info>--- INICIO ACTUALIZACIÓN STANLEY STELLA (SF6) ---</info>');

        try {
            $this->proveedor = $this->getOrCreateProveedor();
            $output->writeln('Proveedor "' . self::$NOMBRE_PROVEEDOR . '" localizado.');

            $inicioApi = microtime(true);
            $productDataFromApi = $this->fetchDataFromApi($output);
            $finApi = microtime(true);

            $output->writeln("Tiempo API: " . round($finApi - $inicioApi, 2) . " seg.");
            $output->writeln("Referencias obtenidas: " . count($productDataFromApi));

            if (!empty($productDataFromApi)) {
                $this->updateProductsInDb($output, $productDataFromApi);
                $this->deactivateEmptyModels($output);
            } else {
                $output->writeln('<comment>No se recibieron datos de la API.</comment>');
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

    private function fetchDataFromApi(OutputInterface $output): array
    {
        $output->writeln('Descargando datos de API...');

        $jsonData = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'db_name' => "production_api",
                'password' => self::$API_PASSWORD,
                'user' => self::$API_USER
            ],
            'id' => 0
        ];

        $ch = curl_init(self::$API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $result === false) {
            throw new \Exception("Error API. Código: {$httpCode}.");
        }

        $data = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['result'])) {
            throw new \Exception("Error JSON API.");
        }

        $productMap = [];
        foreach ($data['result'] as $item) {
            if (isset($item['SKU'], $item['Available_Quantity']) && ($item['Is_Inventory'] ?? false)) {
                $productMap[$item['SKU']] = ['stock' => (int) $item['Available_Quantity']];
            }
        }

        return $productMap;
    }

    private function updateProductsInDb(OutputInterface $output, array $productDataFromApi): void
    {
        $conn = $this->em->getConnection();
        $proveedorId = $this->proveedor->getId();

        // --- PASO 1: RESETEO MASIVO (Nombres corregidos: modelo, proveedor) ---
        $output->writeln('Reseteando stock antiguo...');
        // p.modelo = FK en producto | m.proveedor = FK en modelo
        $sqlReset = 'UPDATE producto p 
                     INNER JOIN modelo m ON p.modelo = m.id 
                     SET p.stock = 0, p.activo = 0 
                     WHERE m.proveedor = :proveedorId';

        $resetCount = $conn->executeStatement($sqlReset, ['proveedorId' => $proveedorId]);
        $output->writeln("Productos reseteados: {$resetCount}");

        // --- PASO 2: MAPEAR REFERENCIAS ---
        $output->writeln('Mapeando referencias...');
        $sqlMap = 'SELECT p.id, p.referencia FROM producto p 
                   INNER JOIN modelo m ON p.modelo = m.id 
                   WHERE m.proveedor = :proveedorId AND p.precio_unidad > 0';

        $rows = $conn->fetchAllAssociative($sqlMap, ['proveedorId' => $proveedorId]);

        $productsFromDb = [];
        foreach ($rows as $row) {
            $productsFromDb[$row['referencia']] = $row['id'];
        }

        // --- PASO 3: ACTUALIZACIÓN POR LOTES ---
        $output->writeln('Escribiendo cambios...');

        $conn->beginTransaction();
        $batchSize = 500;
        $i = 0;
        $updatedCount = 0;
        $notFoundCount = 0;

        try {
            // Nota: aquí NO cambiamos nada porque 'stock', 'stock_futuro' son columnas normales
            $stmt = $conn->prepare('UPDATE producto SET stock = :stock, stock_futuro = :futuro, activo = 1 WHERE id = :id');

            foreach ($productDataFromApi as $sku => $productInfo) {
                if (isset($productsFromDb[$sku])) {
                    $productId = $productsFromDb[$sku];

                    $stmt->executeQuery([
                        'stock' => $productInfo['stock'],
                        'futuro' => 'CONSULTAR',
                        'id' => $productId
                    ]);

                    $updatedCount++;
                    $i++;

                    if (($i % $batchSize) === 0) {
                        $conn->commit();
                        $conn->beginTransaction();
                        $output->writeln("Lote procesado: {$i}");
                    }
                } else {
                    $notFoundCount++;
                }
            }

            $conn->commit();
            $output->writeln("<info>Stock actualizado correctamente. Total: {$updatedCount}</info>");

            if ($notFoundCount > 0) {
                $output->writeln("<comment>Referencias API no encontradas en DB: {$notFoundCount}</comment>");
            }

        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    private function deactivateEmptyModels(OutputInterface $output): void
    {
        $output->writeln('Desactivando modelos vacíos...');
        $conn = $this->em->getConnection();

        // SQL Ajustado: usamos 'm.proveedor' y 'p.modelo'
        $sql = 'UPDATE modelo m
                SET m.activo = 0
                WHERE m.proveedor = :proveedorId 
                AND m.activo = 1 
                AND NOT EXISTS (
                    SELECT 1 FROM producto p
                    WHERE p.modelo = m.id AND p.activo = 1
                )';

        $numUpdated = $conn->executeStatement($sql, ['proveedorId' => $this->proveedor->getId()]);

        $output->writeln("<info>Se han desactivado {$numUpdated} modelos.</info>");

        $output->writeln('Limpiando caché de la aplicación...');
        try {
            $this->cache->delete('app_models_cache');
            $output->writeln('<info>Caché limpiada (Parcial).</info>');
        } catch (\Exception $e) {
            $output->writeln('<comment>Nota sobre caché: ' . $e->getMessage() . '</comment>');
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