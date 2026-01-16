<?php

namespace App\Command;

use App\Entity\Proveedor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:actualizar-stock-makito',
    description: 'Actualiza stock y fechas futuras de Makito desde XML (SF6).'
)]
class MakitoStockCommand extends Command
{
    // --- Configuración ---
    // Clave y URL según tu script original
    private static string $PZ_INTERNAL = "0002676932808535201852904";
    private static string $NOMBRE_PROVEEDOR = "Makito";

    private EntityManagerInterface $em;
    private ?Proveedor $proveedor = null;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct();
    }

    private function getApiUrl(): string
    {
        return "http://print.makito.es:8080/user/xml/allstockgroupedfile.php?pszinternal=" . self::$PZ_INTERNAL;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Aumentamos memoria por si el XML es muy pesado
        ini_set('memory_limit', '1G');

        $inicioScript = microtime(true);
        $output->writeln('<info>--- INICIO ACTUALIZACIÓN STOCK MAKITO (SF6) ---</info>');

        try {
            // 1. Obtener Proveedor
            $this->proveedor = $this->getOrCreateProveedor();
            $output->writeln("Proveedor detectado: " . $this->proveedor->getNombre());

            // 2. Descargar XML
            $output->writeln('Descargando XML...');
            $inicioDescarga = microtime(true);

            $xmlContent = $this->downloadXml($this->getApiUrl());

            $finDescarga = microtime(true);
            $output->writeln("Descarga OK en " . round($finDescarga - $inicioDescarga, 2) . " seg.");

            if (!$xmlContent) {
                $output->writeln('<error>Error: Contenido XML vacío o descarga fallida.</error>');
                return Command::FAILURE;
            }

            // 3. Procesar XML con XMLReader
            $this->processXml($output, $xmlContent);

        } catch (\Exception $e) {
            $output->writeln('<error>ERROR CRÍTICO: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $finScript = microtime(true);
        $output->writeln('<info>--- PROCESO FINALIZADO ---</info>');
        $output->writeln("Tiempo Total: " . round($finScript - $inicioScript, 2) . " seg.");

        return Command::SUCCESS;
    }

    private function downloadXml(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch) || $httpCode !== 200) {
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        return $content;
    }

    private function processXml(OutputInterface $output, string $xmlContent): void
    {
        $conn = $this->em->getConnection();

        // --- PASO 1: Reseteo Previo (SQL Nativo) ---
        $output->writeln('Reseteando stock antiguo...');
        $sqlReset = 'UPDATE producto p 
                     INNER JOIN modelo m ON p.modelo = m.id 
                     SET p.stock = 0, p.stock_futuro = NULL 
                     WHERE m.proveedor = :proveedorId';

        $conn->executeStatement($sqlReset, ['proveedorId' => $this->proveedor->getId()]);
        $output->writeln('Reseteo completado.');

        // --- PASO 2: Procesamiento ---
        $output->writeln('Procesando XML y actualizando BBDD...');

        $reader = new \XMLReader();
        if (!$reader->xml($xmlContent)) {
            throw new \Exception("XML inválido.");
        }

        // Sentencias preparadas
        $sqlStock = "UPDATE producto SET stock = :stock WHERE referencia = :referencia";
        $stmtStock = $conn->prepare($sqlStock);

        $sqlStockFuturo = "UPDATE producto SET stock_futuro = :stockFuturo WHERE referencia = :referencia";
        $stmtStockFuturo = $conn->prepare($sqlStockFuturo);

        $productosProcesados = 0;
        $nActualizaciones = 0;
        $batchSize = 200;
        $commitCounter = 0;

        $conn->beginTransaction();
        $inicioLote = microtime(true);

        try {
            while ($reader->read()) {
                // Buscamos nodos <product>
                if ($reader->nodeType == \XMLReader::ELEMENT && $reader->name == 'product') {
                    // Convertimos solo este nodo a SimpleXML para manejarlo fácil
                    // Esto evita cargar todo el árbol en memoria
                    $node = simplexml_import_dom($reader->expand(new \DOMDocument()));

                    $productosProcesados++;
                    $commitCounter++;

                    $refPrincipal = trim((string) $node->matnr);
                    $refAlternativa = trim((string) $node->reftc);

                    // Iteramos sobre <infostock>
                    if (isset($node->infostocks->infostock)) {
                        foreach ($node->infostocks->infostock as $infostock) {
                            $disponibilidad = (string) $infostock->available;

                            if ($disponibilidad === "immediately") {
                                // ACTUALIZAR STOCK REAL
                                // Quitamos puntos de miles si los hubiera (ej: "1.200" -> 1200)
                                $stockVal = (int) str_replace('.', '', (string)$infostock->stock);

                                // Actualizar referencia principal
                                if (!empty($refPrincipal)) {
                                    $stmtStock->bindValue('stock', $stockVal);
                                    $stmtStock->bindValue('referencia', $refPrincipal);
                                    $stmtStock->executeStatement();
                                }

                                // Actualizar referencia alternativa (si existe)
                                if (!empty($refAlternativa)) {
                                    $stmtStock->bindValue('stock', $stockVal);
                                    $stmtStock->bindValue('referencia', $refAlternativa);
                                    $stmtStock->executeStatement();
                                }
                                $nActualizaciones++;

                            } else {
                                // ACTUALIZAR STOCK FUTURO (FECHAS)
                                $fechaFutura = $disponibilidad; // Ej: "2023-12-01"

                                if (!empty($refPrincipal)) {
                                    $stmtStockFuturo->bindValue('stockFuturo', $fechaFutura);
                                    $stmtStockFuturo->bindValue('referencia', $refPrincipal);
                                    $stmtStockFuturo->executeStatement();
                                    $nActualizaciones++;
                                }
                            }
                        }
                    }

                    // Commit por lotes
                    if (($commitCounter % $batchSize) === 0) {
                        $conn->commit();

                        $finLote = microtime(true);
                        $tiempoLote = round($finLote - $inicioLote, 2);
                        $output->writeln("Lote procesado. Total: {$productosProcesados}. Tiempo: {$tiempoLote}s");

                        $inicioLote = microtime(true);
                        $conn->beginTransaction();
                    }
                }
            }

            $conn->commit();
            $output->writeln("<info>Proceso completado.</info>");

        } catch (\Exception $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            $reader->close();
            throw $e;
        }

        $reader->close();

        $output->writeln("--- RESUMEN ---");
        $output->writeln("Productos leídos: {$productosProcesados}");
        $output->writeln("Operaciones de actualización: {$nActualizaciones}");
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