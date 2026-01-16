<?php

namespace App\Command;

use App\Entity\Producto;
use App\Entity\Proveedor;
use App\Entity\Modelo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:actualizar-stock-toptex',
    description: 'Actualiza el stock de TopTex (API V3) para Symfony 6.'
)]
class TopTexStockCommand extends Command
{
    // Credenciales (RECOMENDACIÓN: Mover a .env en el futuro)
    private static string $API_URL = "https://api.toptex.io";
    private static string $API_KEY = "qHWMb9ppfz3xdLHqPCBnZ1ZaSdX8fKru8ciHVgKN";
    private static string $NOMBRE_PROVEEDOR = "TopTex";
    private static string $USERNAME = "toes_tuskamisetasorgin";
    private static string $PASSWORD = "TusKamisetas474!";

    private EntityManagerInterface $em;
    private ?string $token = null;
    private ?Proveedor $proveedor = null;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inicioScript = microtime(true);
        $output->writeln('<info>--- INICIO ACTUALIZACIÓN STOCK TOPTEX (SF6) ---</info>');

        try {
            // 1. Obtener Proveedor
            $this->proveedor = $this->getOrCreateProveedor();
            $output->writeln('Proveedor "TopTex" localizado/creado.');

            // 2. Autenticación API
            $inicioApi = microtime(true);
            $output->writeln('Conectando a API TopTex...');
            $this->authenticate();
            $output->writeln('<info>Autenticación OK.</info>');

            // 3. Descarga de datos
            $stockDataFromApi = $this->fetchAllStockFromApi($output);

            $finApi = microtime(true);
            $output->writeln("API descargada en " . round($finApi - $inicioApi, 2) . " seg.");
            $output->writeln("Productos encontrados en API: " . count($stockDataFromApi));

            // 4. Actualizar Base de Datos
            $this->updateStockInDb($output, $stockDataFromApi);

        } catch (\Exception $e) {
            $output->writeln('<error>ERROR CRÍTICO: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $finScript = microtime(true);
        $output->writeln('<info>--- PROCESO FINALIZADO ---</info>');
        $output->writeln("Tiempo Total: " . round($finScript - $inicioScript, 2) . " seg.");

        return Command::SUCCESS;
    }

    private function authenticate(): void
    {
        $payload = json_encode(['password' => self::$PASSWORD, 'username' => self::$USERNAME]);

        $ch = curl_init(self::$API_URL . '/v3/authenticate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'x-api-key: ' . self::$API_KEY,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $result === false) {
            throw new \Exception('Error Auth API: ' . $result);
        }

        $data = json_decode($result, true);
        $this->token = $data['token'] ?? null;

        if (!$this->token) {
            throw new \Exception('No se recibió token de la API.');
        }
    }

    private function fetchAllStockFromApi(OutputInterface $output): array
    {
        $output->writeln('Descargando inventario (paginado)...');
        $stockData = [];
        $pageNumber = 1;
        $pageSize = 10000; // API permite hasta 10k
        $keepCalling = true;

        while ($keepCalling) {
            $url = self::$API_URL . '/v3/products/inventory?modified_since=all&page_number=' . $pageNumber . '&page_size=' . $pageSize;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'x-api-key: ' . self::$API_KEY,
                'x-toptex-authorization: ' . $this->token
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || $result === false) {
                throw new \Exception("Error descargando página {$pageNumber}.");
            }

            $data = json_decode($result, true);
            $items = $data['items'] ?? [];

            if (empty($items)) {
                $output->writeln("... Página {$pageNumber} vacía. Fin.");
                $keepCalling = false;
                continue;
            }

            foreach ($items as $item) {
                $stockTotal = 0;
                // Sumar stock solo del almacén central 'toptex'
                foreach ($item['warehouses'] ?? [] as $warehouse) {
                    if (($warehouse['id'] ?? '') === 'toptex') {
                        $stockTotal += (int) ($warehouse['stock'] ?? 0);
                    }
                }
                $stockData[$item['sku']] = $stockTotal;
            }

            $output->writeln("... Página {$pageNumber} procesada.");
            $pageNumber++;
        }

        return $stockData;
    }

    private function updateStockInDb(OutputInterface $output, array $stockDataFromApi): void
    {
        $output->writeln('Actualizando base de datos...');
        $batchSize = 200;
        $i = 0;
        $updatedCount = 0;
        $proveedorId = $this->proveedor->getId(); // Guardar ID para recargar después del clear

        // --- PASO 1: RESETEAR STOCK A 0 ---
        // Ponemos a 0 el stock de todos los productos de este proveedor antes de actualizar
        $output->writeln('Reseteando stock antiguo...');
        $this->em->getConnection()->beginTransaction();
        try {
            $q = $this->em->createQuery(
                'UPDATE App\Entity\Producto p SET p.stock = 0, p.activo = 0
                 WHERE p.modelo IN (SELECT m.id FROM App\Entity\Modelo m WHERE m.proveedor = :proveedor)'
            )->setParameter('proveedor', $this->proveedor);

            $resetCount = $q->execute();
            $this->em->getConnection()->commit();
            $output->writeln("Productos reseteados: {$resetCount}");
        } catch (\Exception $e) {
            $this->em->getConnection()->rollBack();
            throw $e;
        }

        // --- PASO 2: MAPEAR REFERENCIAS (Optimizacion memoria) ---
        // Obtenemos solo ID y REFERENCIA para no cargar objetos pesados
        $output->writeln('Mapeando referencias locales...');
        $q = $this->em->createQuery(
            'SELECT p.id, p.referencia FROM App\Entity\Producto p
             JOIN p.modelo m WHERE m.proveedor = :proveedor'
        )->setParameter('proveedor', $this->proveedor);

        $productsFromDb = [];
        foreach ($q->toIterable() as $row) {
            $productsFromDb[$row['referencia']] = $row['id'];
        }

        // --- PASO 3: ACTUALIZAR POR LOTES ---
        $output->writeln('Escribiendo cambios...');

        $this->em->getConnection()->beginTransaction();
        try {
            foreach ($stockDataFromApi as $sku => $stock) {
                // Si la referencia de la API existe en nuestra BD
                if (isset($productsFromDb[$sku])) {
                    $productId = $productsFromDb[$sku];

                    // Usamos getReference para evitar una SELECT extra por producto
                    $productToUpdate = $this->em->getReference(Producto::class, $productId);

                    $productToUpdate->setStock($stock);

                    // Comprobación por si el método setStockFuturo no existe en la nueva entidad
                    if (method_exists($productToUpdate, 'setStockFuturo')) {
                        $productToUpdate->setStockFuturo('CONSULTAR');
                    }

                    $productToUpdate->setActivo(true);

                    $updatedCount++;
                    $i++;

                    // Commit por lotes para no saturar RAM
                    if (($i % $batchSize) === 0) {
                        $this->em->flush();
                        $this->em->clear();

                        // Recargamos el proveedor porque $this->proveedor se desconectó al hacer clear()
                        $this->proveedor = $this->em->getReference(Proveedor::class, $proveedorId);

                        $this->em->getConnection()->commit();
                        $output->writeln("Lote procesado. Acumulado: {$i}");
                        $this->em->getConnection()->beginTransaction();
                    }
                }
            }

            $this->em->flush();
            $this->em->clear();
            $this->em->getConnection()->commit();
            $output->writeln("<info>Stock actualizado correctamente. Total: {$updatedCount}</info>");

        } catch (\Exception $e) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->getConnection()->rollBack();
            }
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