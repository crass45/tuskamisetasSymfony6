<?php

namespace App\Command;

use App\Entity\Proveedor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:import-textil-mallorca-v3',
    description: 'Importa o actualiza el stock de Textil Mallorca por marcas (SF6).'
)]
class TextilMallorcaV3ImportCommand extends Command
{
    // --- Configuración ---
    private string $apiUrl = "https://v3.pl18group.com";
    private string $username = "informatica@tuskamisetas.com";
    private string $password = "kfTdKp@38T!a4@oBK!PQGWRwSz8";
    private string $nombreProveedor = "Textil Mallorca";

    // IDs de marcas a procesar (Configuración original)
    private array $brands = [1];

    private EntityManagerInterface $em;
    private ?Proveedor $proveedor = null;
    private ?string $token = null;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'paso',
            InputArgument::REQUIRED,
            'Acción: 2 (Actualizar Stock) o 3 (Refactorizar Referencias).'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '2G');

        $inicioScript = microtime(true);
        $output->writeln('<info>--- INICIO TEXTIL MALLORCA V3 (SF6) ---</info>');
        $paso = (int) $input->getArgument('paso');

        try {
            // 1. Autenticación
            $inicioApi = microtime(true);
            $output->writeln('1. Autenticando...');
            $this->authenticate();
            $output->writeln('<info>Autenticación OK.</info>');

            $this->proveedor = $this->getOrCreateProveedor();

            // 2. Descarga de datos
            $output->writeln('2. Descargando datos API...');
            $allItemsFromApi = [];

            foreach ($this->brands as $brandId) {
                $output->writeln("--- Descargando Marca ID: {$brandId} ---");
                $itemsForBrand = $this->fetchAllItemsFromApi($output, $brandId);
                $output->writeln('Items obtenidos: ' . count($itemsForBrand));
                $allItemsFromApi = array_merge($allItemsFromApi, $itemsForBrand);
            }

            $finApi = microtime(true);
            $output->writeln("Tiempo API total: " . round($finApi - $inicioApi, 2) . " seg.");
            $output->writeln('Total acumulado: ' . count($allItemsFromApi) . ' items.');

            if (empty($allItemsFromApi)) {
                $output->writeln('<comment>No hay datos. Fin.</comment>');
                return Command::SUCCESS;
            }

            // 3. Ejecutar acción seleccionada
            if ($paso === 2) {
                $this->updateStock($output, $allItemsFromApi);
            } elseif ($paso === 3) {
                $this->refactorReferences($output, $allItemsFromApi);
            } else {
                $output->writeln('<error>Paso inválido. Use 2 (Stock) o 3 (Refactorizar).</error>');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $output->writeln('<error>ERROR CRÍTICO: ' . $e->getMessage() . '</error>');
            if ($output->isVerbose()) {
                $output->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }

        $finScript = microtime(true);
        $output->writeln('');
        $output->writeln('<info>--- PROCESO FINALIZADO ---</info>');
        $output->writeln("Tiempo Total: " . round($finScript - $inicioScript, 2) . " seg.");

        return Command::SUCCESS;
    }

    private function updateStock(OutputInterface $output, array $stockData): void
    {
        $output->writeln("3. Actualizando stock en BBDD...");
        $this->updateProductsInDb($output, $stockData);
    }

    private function refactorReferences(OutputInterface $output, array $apiItems): void
    {
        $output->writeln("3. Refactorizando referencias...");
        $this->updateReferencesInDb($output, $apiItems);
    }

    private function updateProductsInDb(OutputInterface $output, array $stockData): void
    {
        $conn = $this->em->getConnection();
        $updatedCount = 0;
        $notFoundCount = 0;

        try {
            // --- PASO 1: Reseteo Masivo (SQL Nativo) ---
            $inicioReset = microtime(true);
            $output->writeln("   - Reseteando stock antiguo...");

            $conn->beginTransaction();
            // CORRECCIÓN: Usamos 'm.proveedor' y 'p.modelo' (sin _id)
            $resetSql = 'UPDATE producto p 
                         JOIN modelo m ON p.modelo = m.id 
                         SET p.stock = 0, p.activo = 0 
                         WHERE m.proveedor = :proveedorId';

            $stmtReset = $conn->prepare($resetSql);
            $stmtReset->bindValue('proveedorId', $this->proveedor->getId());
            $stmtReset->executeStatement();

            $conn->commit();

            $finReset = microtime(true);
            $output->writeln("   - Reseteo OK. Tiempo: " . round($finReset - $inicioReset, 2) . " seg.");

            // --- PASO 2: Actualización por lotes (SQL Nativo) ---
            $output->writeln('   - Actualizando stock y precios...');

            $updateSql = 'UPDATE producto 
                          SET stock = :stock, activo = 1, precio_unidad = :precio, precio_pack = :precio, precio_caja = :precio 
                          WHERE referencia = :sku';
            $stmtUpdate = $conn->prepare($updateSql);

            $batchSize = 200;
            $i = 0;

            $inicioLote = microtime(true);
            $conn->beginTransaction();

            foreach ($stockData as $sku => $item) {
                $i++;
                $precioCompra = 0.0;

                // Buscar precio en la estructura de la API
                if (isset($item['prices']) && is_array($item['prices'])) {
                    foreach ($item['prices'] as $precio) {
                        if (($precio['rateId'] ?? '') === "YOUR_RATE") {
                            $precioCompra = (float) ($precio['price'] ?? 0);
                            break;
                        }
                    }
                }

                $stmtUpdate->bindValue('stock', (int) ($item['stock'] ?? 0));
                $stmtUpdate->bindValue('precio', $precioCompra);
                $stmtUpdate->bindValue('sku', $sku);

                $result = $stmtUpdate->executeStatement();

                if ($result > 0) {
                    $updatedCount++;
                } else {
                    $notFoundCount++;
                }

                if (($i % $batchSize) === 0) {
                    $conn->commit();

                    $finLote = microtime(true);
                    $tiempoLote = round($finLote - $inicioLote, 2);
                    $output->writeln("     Lote {$batchSize} OK. Total: {$i}. Tiempo: {$tiempoLote} seg.");

                    $inicioLote = microtime(true);
                    $conn->beginTransaction();
                }
            }

            $conn->commit(); // Commit final
            $output->writeln('<info>Actualización completada.</info>');

        } catch (\Exception $e) {
            if ($conn->isTransactionActive()) $conn->rollBack();
            throw $e;
        }

        $output->writeln("--- RESUMEN ---");
        $output->writeln("Actualizados: {$updatedCount}");
        if ($notFoundCount > 0) {
            $output->writeln("<comment>No encontrados en DB: {$notFoundCount}</comment>");
        }
    }

    private function updateReferencesInDb(OutputInterface $output, array $apiItems): void
    {
        $conn = $this->em->getConnection();
        $updatedCount = 0;
        $notFoundCount = 0;

        $output->writeln('   - Creando mapa de productos locales...');

        // CORRECCIÓN: Nombres de tablas SQL nativas 'producto' y 'modelo'
        $sqlMap = 'SELECT p.id, m.referencia, p.talla, p.color_id as color
                   FROM producto p 
                   JOIN modelo m ON p.modelo = m.id 
                   WHERE m.proveedor = :proveedorId';

        $rows = $conn->fetchAllAssociative($sqlMap, ['proveedorId' => $this->proveedor->getId()]);

        $dbMap = [];
        foreach ($rows as $product) {
            // Clave única para emparejar: Modelo-Color-Talla
            $key = "{$product['referencia']}-{$product['color']}-{$product['talla']}";
            $dbMap[$key] = $product['id'];
        }

        try {
            $output->writeln('   - Actualizando referencias...');
            $updateSql = 'UPDATE producto SET referencia = :newRef WHERE id = :id';
            $stmtUpdate = $conn->prepare($updateSql);

            $batchSize = 200;
            $i = 0;

            $conn->beginTransaction();

            foreach ($apiItems as $apiId => $item) {
                $i++;
                // Construir clave según lógica del script original
                // OJO: Asegúrate de que $item['color']['id'] y $item['size']['id'] coinciden con tus IDs de BBDD
                $colorId = $item['color']['id'] ?? '';
                $sizeId = $item['size']['id'] ?? '';
                $productId = $item['productId'] ?? '';

                $apiKey = "{$productId}-{$colorId}-{$sizeId}";

                if (isset($dbMap[$apiKey])) {
                    $productIdInDb = $dbMap[$apiKey];

                    $stmtUpdate->bindValue('newRef', $apiId); // La ID de la API pasa a ser nuestra REFERENCIA
                    $stmtUpdate->bindValue('id', $productIdInDb);
                    $stmtUpdate->executeStatement();

                    $updatedCount++;
                } else {
                    $notFoundCount++;
                }

                if (($i % $batchSize) === 0) {
                    $conn->commit();
                    $output->writeln("     Lote procesado. Total: {$i}");
                    $conn->beginTransaction();
                }
            }

            $conn->commit();
            $output->writeln('<info>Refactorización completada.</info>');

        } catch (\Exception $e) {
            if ($conn->isTransactionActive()) $conn->rollBack();
            throw $e;
        }

        $output->writeln("--- RESUMEN REFACTORIZACIÓN ---");
        $output->writeln("Referencias cambiadas: {$updatedCount}");
        if ($notFoundCount > 0) {
            $output->writeln("<comment>Items API sin correspondencia local: {$notFoundCount}</comment>");
        }
    }

    // --- MÉTODOS AUXILIARES ---

    private function fetchAllItemsFromApi(OutputInterface $output, int $brandId): array
    {
        $page = 0;
        $allItems = [];
        $keepCalling = true;

        while ($keepCalling) {
            $output->writeln("... Pagina {$page} ...");
            $itemsOnPage = $this->apiRequest("/api/b2b/items?brandId={$brandId}&page={$page}&pageSize=500");

            if (empty($itemsOnPage) || !is_array($itemsOnPage)) {
                $keepCalling = false;
                continue;
            }

            foreach ($itemsOnPage as $item) {
                if (!empty($item['id'])) {
                    $allItems[$item['id']] = $item;
                }
            }
            $page++;
        }
        return $allItems;
    }

    private function authenticate(): void
    {
        $response = $this->apiRequest('/api/b2b/auth', 'POST', [], true);
        $this->token = $response['apiToken'] ?? null;

        if (!$this->token) {
            throw new \Exception("Fallo al obtener token de autenticación.");
        }
    }

    private function apiRequest(string $endpoint, string $method = 'GET', array $payload = [], bool $isAuth = false)
    {
        $url = $this->apiUrl . $endpoint;
        $ch = curl_init($url);

        $headers = ['Content-Type: application/json'];
        if (!$isAuth) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // OJO: Solo para pruebas/migración rápida
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            if ($isAuth) {
                curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 300 || $result === false) {
            if ($httpCode === 404 && strpos($endpoint, '/api/b2b/items') !== false) {
                return [];
            }
            throw new \Exception("Error API ({$endpoint}). HTTP: {$httpCode}.");
        }

        return json_decode($result, true);
    }

    private function getOrCreateProveedor(): Proveedor
    {
        $repo = $this->em->getRepository(Proveedor::class);
        $proveedor = $repo->findOneBy(['nombre' => $this->nombreProveedor]);

        if (!$proveedor) {
            $proveedor = new Proveedor();
            $proveedor->setNombre($this->nombreProveedor);
            $this->em->persist($proveedor);
            $this->em->flush();
        }
        return $proveedor;
    }
}