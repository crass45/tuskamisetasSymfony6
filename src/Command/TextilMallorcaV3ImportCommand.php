<?php

namespace App\Command;

use App\Entity\Color;
use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Modelo;
use App\Entity\Producto;
use App\Entity\Proveedor;
use App\Entity\Tarifa;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:import-textil-mallorca-v3',
    description: 'Importa catálogo completo (1) o actualiza stock (2).'
)]
class TextilMallorcaV3ImportCommand extends Command
{
    private string $apiUrl = "https://v3.pl18group.com";
    private string $username = "informatica@tuskamisetas.com";
    private string $password = "kfTdKp@38T!a4@oBK!PQGWRwSz8";

    // Proveedor Fijo (Quien nos vende)
    private string $nombreProveedor = "Textil Mallorca";

    // IDs de marcas a procesar (1 = Fruit, 7 = Russell, etc.)
    private array $brands = [1];

    // Mapeo manual de nombres de marcas (ID API => Nombre Real)
    // Si no está aquí, usará el nombre que venga en el JSON de la API
    private array $brandNames = [
        1 => 'Fruit of the Loom',
        /*7 => 'Russell Europe',
        6 => 'Jerzees',
        13 => 'Acqua Royal',
        5 => 'Stanley Stella',
        48 => 'Joylu',*/
        // Añade aquí más IDs si los necesitas renombrar
    ];

    private EntityManagerInterface $em;
    private SluggerInterface $slugger;

    private ?string $token = null;
    private ?Proveedor $proveedorEntity = null;
    private ?Fabricante $fabricanteEntity = null; // Cambia dinámicamente

    // Cache de fotos: ['ID_MODELO' => ['ID_COLOR' => ['main' => url, 'views' => [url, url]]]]
    private array $imagenesPorModeloColor = [];

    public function __construct(EntityManagerInterface $em, SluggerInterface $slugger)
    {
        parent::__construct();
        $this->em = $em;
        $this->slugger = $slugger;
    }

    protected function configure(): void
    {
        $this->addArgument('paso', InputArgument::REQUIRED, '1 (Catálogo), 2 (Stock)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '-1');
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        $paso = (int) $input->getArgument('paso');

        try {
            $output->writeln('1. Autenticando...');
            $this->authenticate();

            // Solo preparamos el Proveedor fijo aquí.
            // El Fabricante se decide dentro del bucle de marcas.
            $this->setupProveedor();

            $output->writeln('<info>Autenticación OK.</info>');

            if ($paso === 0){
                $this->testBrandProcess($output);
            }
            if ($paso === 1) {
                $this->importCatalogProcess($output);
            } elseif ($paso === 2) {
                $this->updateStockProcess($output);
            } else {
                $output->writeln('<error>Paso inválido.</error>');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $output->writeln('<error>ERROR CRÍTICO: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>--- PROCESO FINALIZADO ---</info>');
        return Command::SUCCESS;
    }

    // =======================================================
    //      PASO 1: IMPORTACIÓN DE CATÁLOGO
    // =======================================================

    private function importCatalogProcess(OutputInterface $output): void
    {
        // 1. MODELOS
        $output->writeln('<info>=== FASE 1: PROCESANDO MODELOS Y FOTOS ===</info>');

        foreach ($this->brands as $brandId) {
            // A. Descargar datos
            $output->writeln("--- Descargando Marca ID: {$brandId} ---");
            $apiModels = $this->fetchFromApiPaginated($output, "/api/b2b/products?brandId={$brandId}", 50, "Modelos");

            if (empty($apiModels)) {
                $output->writeln("   Sin datos para marca {$brandId}");
                continue;
            }

            // B. Determinar y crear Fabricante
            // 1. Miramos si tenemos un nombre mapeado manualmente
            $nombreMarca = $this->brandNames[$brandId] ?? null;

            // 2. Si no, cogemos el nombre que viene en el primer producto de la API
            if (!$nombreMarca && isset($apiModels[0]['brand']['name'])) {
                $nombreMarca = $apiModels[0]['brand']['name'];
            }

            // 3. Si sigue null, fallback genérico
            if (!$nombreMarca) {
                $nombreMarca = "Marca {$brandId}";
            }

            // C. Establecer entidad Fabricante activa para este lote
            $output->writeln("   -> Asignando Fabricante: <comment>{$nombreMarca}</comment>");
            $this->fabricanteEntity = $this->getOrCreateFabricante($nombreMarca);

            // D. Procesar los modelos con este fabricante
            $this->processModelsBatch($output, $apiModels);
        }

        // 2. ITEMS (Variantes)
        $output->writeln('<info>=== FASE 2: PROCESANDO VARIANTES ===</info>');
        foreach ($this->brands as $brandId) {
            // Nota: En fase 2 no necesitamos setear el fabricante porque usamos el Modelo padre que ya lo tiene
            $apiItems = $this->fetchFromApiPaginated($output, "/api/b2b/items?brandId={$brandId}", 500, "Items");
            $this->processItemsBatch($output, $apiItems);
        }

        // 3. AJUSTES
        $this->ajustaPrecios($output);
    }

    private function processModelsBatch(OutputInterface $output, array $apiModels): void
    {
        $i = 0;
        $matchesEncontrados = 0;

        foreach ($apiModels as $row) {
            $i++;
            try {
                $refModelo = (string)$row['id'];

                // 1. DETALLE API
                $detail = $this->apiRequest("/api/b2b/products/{$refModelo}");
                if (!empty($detail)) $row = array_merge($row, $detail);

                $imagenesRow = $row['images'] ?? [];

                // 2. PROCESAR FOTOS API
                if (!empty($imagenesRow)) {
                    foreach ($imagenesRow as $imgData) {
                        $rawUrl = $imgData['url'] ?? '';
                        $parts = explode('?', $rawUrl);
                        $cleanUrl = $parts[0];
                        $colorId = $imgData['colorId'] ?? null;
                        $position = $imgData['position'] ?? 'front';

                        if ($cleanUrl && $colorId) {
                            $this->imagenesPorModeloColor[$refModelo][$colorId]['views'][] = $cleanUrl;
                            if ($position === 'front' || !isset($this->imagenesPorModeloColor[$refModelo][$colorId]['main'])) {
                                $this->imagenesPorModeloColor[$refModelo][$colorId]['main'] = $cleanUrl;
                            }
                        }
                    }

                    $mainImageModel = null;
                    foreach ($imagenesRow as $imgData) {
                        if (($imgData['position'] ?? '') === 'front') {
                            $parts = explode('?', $imgData['url'] ?? '');
                            $mainImageModel = $parts[0];
                            break;
                        }
                    }
                    if (!$mainImageModel && isset($imagenesRow[0]['url'])) {
                        $parts = explode('?', $imagenesRow[0]['url']);
                        $mainImageModel = $parts[0];
                    }
                    $row['mainImage'] = $mainImageModel;
                }

                // 3. FAMILIA & MODELO
                $familia = null;
                if (!empty($row['family']['name']['es'])) {
                    $slugFamilia = $this->slugger->slug($this->nombreProveedor . "-" . $row['family']['name']['es'])->lower()->toString();
                    $familia = $this->em->getRepository(Familia::class)->find($slugFamilia);
                    if (!$familia) {
                        $familia = new Familia();
                        $familia->setId($slugFamilia);
                        $familia->setNombre($row['family']['name']['es']);
                        $familia->setNombreUrl($this->slugger->slug($row['family']['name']['es'])->lower()->toString());
                        $familia->setProveedor($this->proveedorEntity);
                        $familia->setMarca($this->fabricanteEntity);
                        $this->em->persist($familia);
                    }
                }

                $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $refModelo, 'proveedor' => $this->proveedorEntity]);
                if (!$modelo) {
                    $modelo = new Modelo();
                    $modelo->setReferencia($refModelo);
                    $modelo->setProveedor($this->proveedorEntity);
                    //$modelo->setFechaAlta(new \DateTime());
                }

                $modelo->setActivo(true);
                $modelo->setFabricante($this->fabricanteEntity);
                $modelo->setFamilia($familia);
                $nombre=$row['name']['es'] ?? 'Sin Nombre';

                $descCorta = $row['description']['es'] ?? '';
                $modelo->setNombre($descCorta." ".$nombre);
                $descLarga = $row['descriptionDetail']['es'] ?? '';
                $descFinal = trim($descCorta);
                if ($descLarga && $descLarga !== $descCorta) $descFinal .= "\n\n" . $descLarga;
                if (!empty($row['grs'])) $descFinal .= "\n\nGramaje: " . $row['grs'] . "g/m²";

                $modelo->setDescripcion($descFinal);
                $modelo->setComposicion($row['fabric']['name']['es'] ?? null);

                // --- REFERENCIA PROVEEDOR (Para el Cruce) ---
                $refProveedor = !empty($row['adultReference']) ? $row['adultReference'] : ($row['kidReference'] ?? null);

                if ($refProveedor && method_exists($modelo, 'setSupplierArticleName')) {
                    $modelo->setSupplierArticleName($refProveedor);
                }

                if (!$modelo->getNombreUrl()) {
                    $slugText = $this->fabricanteEntity->getNombre() . "-" . $modelo->getNombre();
                    $modelo->setNombreUrl($this->slugger->slug($slugText)->lower()->toString());
                }

                // Imagen API
                if (!empty($row['mainImage'])) {
                    if($modelo->getUrlImage() == null) {
                        $modelo->setUrlImage($row['mainImage']);
                    }
                }

                // =================================================================
                // 4. CROSS-MATCHING: Buscar por Fabricante + Referencia Normalizada
                // =================================================================
                if ($refProveedor) {
                    // Pasamos el fabricante actual para acotar la búsqueda
                    $modeloGemelo = $this->findModelWithBetterImages($refProveedor, $this->fabricanteEntity);

                    if ($modeloGemelo) {
                        $matchesEncontrados++;
                        // Log para depurar
                        $output->writeln("      <fg=green>[MATCH]</> Gemelo encontrado: Ref '{$refProveedor}' coincide con '{$modeloGemelo->getSupplierArticleName()}' (ID: {$modeloGemelo->getId()})");

                        // 1. URL Imagen Principal
                        if ($modeloGemelo->getUrlImage()) {
                            $modelo->setUrlImage($modeloGemelo->getUrlImage());
                        }

                        // 2. Imágenes de Detalle
                        if (method_exists($modelo, 'setDetailsImages') && method_exists($modeloGemelo, 'getDetailsImages')) {
                            if ($modeloGemelo->getDetailsImages()) {
                                $modelo->setDetailsImages($modeloGemelo->getDetailsImages());
                            }
                        }

                        // 2. Imágenes de Otras
                        if (method_exists($modelo, 'setOtherImages') && method_exists($modeloGemelo, 'getOtherImages')) {
                            if ($modeloGemelo->getOtherImages()) {
                                $modelo->setOtherImages($modeloGemelo->getOtherImages());
                            }
                        }



                        // 3. Child Image
                        if (method_exists($modelo, 'setChildImage') && method_exists($modeloGemelo, 'getChildImage')) {
                            if ($modeloGemelo->getChildImage()) {
                                $modelo->setChildImage($modeloGemelo->getChildImage());
                            }
                        }

                        // 4. Views Images
                        if (method_exists($modelo, 'setViewsImages') && method_exists($modeloGemelo, 'getViewsImages')) {
                            if ($modeloGemelo->getViewsImages()) {
                                $modelo->setViewsImages($modeloGemelo->getViewsImages());
                            }
                        }
                    }
                }
                // =================================================================

                $this->em->persist($modelo);

            } catch (\Exception $e) {
                $output->writeln("<error>Error en modelo {$row['id']}: " . $e->getMessage() . "</error>");
            }

            if (($i % 20) === 0) {
                $this->em->flush();
                $output->writeln("   ... procesados {$i} modelos");
            }
        }

        $this->em->flush();
        $this->em->clear();
        $this->reloadBaseEntities();
        $output->writeln("<info>   Fase 1 completada. Gemelos encontrados: {$matchesEncontrados}</info>");
    }

    /**
     * Busca un modelo "gemelo" en la base de datos que sea de OTRO proveedor
     * y cuya referencia normalizada coincida con la que buscamos.
     */
    private function findModelWithBetterImages(string $refTextilMallorca, Fabricante $fabricante): ?Modelo
    {
        // 1. Normalizar lo que buscamos: "61082" -> "61082"
        $refLimpia = preg_replace('/[^a-zA-Z0-9]/', '', $refTextilMallorca);

        if (strlen($refLimpia) < 4) return null; // Seguridad

        // 2. Traer CANDIDATOS de la BBDD (Solo del mismo fabricante y con foto)
        // Esto es muy rápido porque filtramos por fabricante (Fruit, Russell...)
        $candidatos = $this->em->getRepository(Modelo::class)->createQueryBuilder('m')
            ->where('m.fabricante = :fab')
            ->andWhere('m.proveedor != :currentProv')
            ->andWhere('m.urlImage IS NOT NULL AND m.urlImage != :empty')
            ->andWhere('m.supplierArticleName IS NOT NULL') // Solo los que tengan ref de proveedor
            ->setParameter('fab', $fabricante)
            ->setParameter('currentProv', $this->proveedorEntity)
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        // 3. Comparar en PHP (Donde podemos quitar guiones fácilmente)
        foreach ($candidatos as $candidato) {
            $refExterna = $candidato->getSupplierArticleName();

            // Normalizar: "61-082-0" -> "610820"
            $refExternaLimpia = preg_replace('/[^a-zA-Z0-9]/', '', $refExterna);

            // Comparación de contenido (bidireccional)
            // ¿61082 está en 610820? SI.
            if (str_contains($refExternaLimpia, $refLimpia) || str_contains($refLimpia, $refExternaLimpia)) {
                return $candidato;
            }
        }

        return null;
    }

    private function processItemsBatch(OutputInterface $output, array $apiItems): void
    {
        // 1. Aseguramos que las entidades base estén conectadas al empezar el lote
        $this->reloadBaseEntities();

        $i = 0;
        foreach ($apiItems as $item) {
            $i++;
            try {
                $modelRef = $item['productId'] ?? null;
                $sku = $item['id'] ?? null;

                if (!$modelRef || !$sku) continue;

                // Buscamos el padre
                $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modelRef, 'proveedor' => $this->proveedorEntity]);
                if (!$modelo) continue;

                // COLOR
                $color = null;
                $colorIdApi = $item['color']['id'] ?? null;

                if (!empty($item['color']['name']['es'])) {
                    $slugColor = $this->slugger->slug($this->nombreProveedor . "-" . $item['color']['name']['es'])->lower()->toString();
                    $color = $this->em->getRepository(Color::class)->find($slugColor);

                    if (!$color) {
                        $color = new Color();
                        $color->setId($slugColor);
                        $color->setNombre($item['color']['name']['es']);

                        // --- CORRECCIÓN ERROR PERSIST CASCADE ---
                        // Recuperamos el proveedor fresco de la BD para asegurar que está "managed"
                        // antes de asignarlo a una entidad nueva que vamos a flushear inmediatamente.
                        $provFresco = $this->em->getReference(Proveedor::class, $this->proveedorEntity->getId());
                        $color->setProveedor($provFresco);
                        // ----------------------------------------

                        $this->em->persist($color);
                        $this->em->flush(); // Guardado inmediato del color
                    }
                }

                // PRODUCTO
                $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $sku]);
                if (!$producto) {
                    $producto = new Producto();
                    $producto->setReferencia($sku);
                    $producto->setModelo($modelo);
                }

                $producto->setActivo(true);
                $producto->setColor($color);
                $talla = $item['size']['description'] ?? $item['size']['numericId'] ?? 'UNICA';
                $producto->setTalla($talla);

                // FOTOS
                $datosFotos = $this->imagenesPorModeloColor[$modelRef][$colorIdApi] ?? null;

                if ($datosFotos) {
//                    if (!empty($datosFotos['main'])) {
                        $producto->setUrlImage($datosFotos['main']);
//                    }
//                    if (!empty($datosFotos['views']) && is_array($datosFotos['views'])) {
                        $uniqueViews = array_unique($datosFotos['views']);
                        $stringViews = implode(',', $uniqueViews);
                        if (method_exists($producto, 'setViewsImages')) {
                            $producto->setViewsImages($stringViews);
                        }
//                    }
                }

                // PRECIOS / STOCK / EAN
                // --- PRECIOS (Seleccionar el MÁS BAJO) ---
                $precioMinimo = null; // Empezamos en null para detectar el primero

                if (!empty($item['prices']) && is_array($item['prices'])) {
                    foreach ($item['prices'] as $p) {
                        $precioActual = (float)($p['price'] ?? 0);

                        // Si es el primer precio que vemos O es menor que el guardado
                        if ($precioMinimo === null || $precioActual < $precioMinimo) {
                            $precioMinimo = $precioActual;
                        }
                    }
                }

                // Si no se encontró ninguno, ponemos 0.0
                $precioFinal = $precioMinimo ?? 0.0;
                $producto->setPrecioUnidad($precioFinal);
                $producto->setPrecioPack($precioFinal);
                $producto->setPrecioCaja($precioFinal);
                $producto->setStock((int)($item['stock'] ?? 0));

                $eanRaw = $item['providerSku'] ?? 0;
                if (method_exists($producto, 'setEancode')) $producto->setEancode((int)$eanRaw);
                elseif (method_exists($producto, 'setEan')) $producto->setEan((int)$eanRaw);

                $this->em->persist($producto);

            } catch (\Exception $e) {
                // Opcional: ver error
                // $output->writeln("Error item: " . $e->getMessage());
            }

            if (($i % 100) === 0) {
                $this->em->flush();
                $this->em->clear();
                $this->reloadBaseEntities(); // Recarga vital tras el clear
                $output->writeln("   ... items procesados: {$i}");
            }
        }
        $this->em->flush();
        $this->em->clear();
        $this->reloadBaseEntities(); // Recarga final por si hay más marcas
        $output->writeln("<info>   Fase 2 (Marca actual) completada: {$i} items.</info>");
    }

    private function updateStockProcess(OutputInterface $output): void
    {
        $output->writeln('2. Descargando Stock API...');
        $allItems = [];
        foreach ($this->brands as $brandId) {
            $items = $this->fetchFromApiPaginated($output, "/api/b2b/items?brandId={$brandId}", 500, "Stock");
            foreach ($items as $it) $allItems[$it['id']] = $it;
        }

        if (empty($allItems)) {
            $output->writeln('<comment>No hay datos.</comment>');
            return;
        }

        $conn = $this->em->getConnection();
        $updatedCount = 0;
        try {
            $output->writeln('   - Actualizando BBDD (SQL Nativo)...');
            $conn->beginTransaction();
            $sql = 'UPDATE producto SET stock = :stock, activo = 1, precio_unidad = :p, precio_pack = :p, precio_caja = :p WHERE referencia = :sku';
            $stmt = $conn->prepare($sql);
            $i = 0;
            foreach ($allItems as $sku => $item) {
                $i++;
                $precio = 0.0;
                if (!empty($item['prices'])) foreach ($item['prices'] as $p) { $precio = (float)$p['price']; break; }
                $stmt->bindValue('stock', (int)($item['stock'] ?? 0));
                $stmt->bindValue('p', $precio);
                $stmt->bindValue('sku', $sku);
                $stmt->executeStatement();
                if ($i % 500 === 0) { $conn->commit(); $conn->beginTransaction(); }
                $updatedCount++;
            }
            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
        $output->writeln("Stock actualizado: {$updatedCount}");
    }

    // =======================================================
    //      HELPERS Y ENTIDADES
    // =======================================================

    private function fetchFromApiPaginated(OutputInterface $output, string $baseEndpoint, int $pageSize, string $context): array
    {
        $page = 0; $allData = []; $keep = true;
        $separator = (strpos($baseEndpoint, '?') !== false) ? '&' : '?';

        while ($keep) {
            $url = "{$baseEndpoint}{$separator}page={$page}&pageSize={$pageSize}";
            $output->write(".");
            $res = $this->apiRequest($url);
            if (empty($res)) { $keep = false; continue; }
            foreach ($res as $r) $allData[] = $r;
            if (count($res) < $pageSize) $keep = false;
            $page++;
        }
        $output->writeln("");
        return $allData;
    }

    private function setupProveedor(): void
    {
        // Solo gestionamos el Proveedor "Textil Mallorca"
        $this->proveedorEntity = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $this->nombreProveedor]);
        if (!$this->proveedorEntity) {
            $this->proveedorEntity = new Proveedor();
            $this->proveedorEntity->setNombre($this->nombreProveedor);
            if (method_exists(Proveedor::class, 'setNombreUrl'))
                $this->proveedorEntity->setNombreUrl($this->slugger->slug($this->nombreProveedor)->lower()->toString());
            $this->em->persist($this->proveedorEntity);
            $this->em->flush();
        }
    }

    // Nuevo Helper para Fabricante Dinámico
    private function getOrCreateFabricante(string $nombre): Fabricante
    {
        // Buscamos si ya existe por nombre
        $fabricante = $this->em->getRepository(Fabricante::class)->findOneBy(['nombre' => $nombre]);

        if (!$fabricante) {
            $fabricante = new Fabricante();
            $fabricante->setNombre($nombre);
            if (method_exists(Fabricante::class, 'setNombreUrl')) {
                $fabricante->setNombreUrl($this->slugger->slug($nombre)->lower()->toString());
            }
            $this->em->persist($fabricante);
            $this->em->flush(); // Necesario para obtener ID si es nuevo
        }
        return $fabricante;
    }

    private function reloadBaseEntities(): void
    {
        // Recargar Proveedor tras un clear()
        if ($this->proveedorEntity) {
            $this->proveedorEntity = $this->em->find(Proveedor::class, $this->proveedorEntity->getId());
        }
        // El fabricante se gestiona dinámicamente, no necesitamos recargarlo globalmente aquí
        // pero reseteamos la variable para que el bucle pida el nuevo.
        $this->fabricanteEntity = null;
    }

    private function ajustaPrecios(OutputInterface $output): void
    {
        // Ajustamos precios para el proveedor global
        if ($this->proveedorEntity) {
            $this->proveedorEntity = $this->em->find(Proveedor::class, $this->proveedorEntity->getId());
            $q = $this->em->createQuery("UPDATE App\Entity\Modelo m SET m.precioMin = (SELECT MIN(p.precioUnidad) FROM App\Entity\Producto p WHERE p.modelo = m.id) WHERE m.proveedor = :prov");
            $q->setParameter('prov', $this->proveedorEntity);
            $q->execute();
            $output->writeln('   Precios ajustados.');
        }
    }

    private function authenticate(): void
    {
        $response = $this->apiRequest('/api/b2b/auth', 'POST', [], true);
        $this->token = $response['apiToken'] ?? null;
    }

    private function apiRequest(string $endpoint, string $method = 'GET', array $payload = [], bool $isAuth = false)
    {
        $ch = curl_init($this->apiUrl . $endpoint);
        $headers = ['Content-Type: application/json'];
        if (!$isAuth) $headers[] = 'Authorization: Bearer ' . $this->token;
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            if ($isAuth) curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
            else curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true);
    }

    // Sustituye temporalmente el contenido de importCatalogProcess por esto:
    private function testBrandProcess(OutputInterface $output): void
    {
        $output->writeln('<info>--- INICIANDO ESCANEO DE MARCAS (IDs 1 al 50) ---</info>');

        // Probamos los primeros 50 IDs
        for ($i = 1; $i <= 50; $i++) {

            // Pedimos SOLO 1 producto de esa marca para ver el nombre
            // pageSize=1 para que sea ultra rápido
            $url = "/api/b2b/products?brandId={$i}&page=0&pageSize=1";
            $res = $this->apiRequest($url);

            if (!empty($res) && isset($res[0]['brand']['name'])) {
                $nombreMarca = $res[0]['brand']['name'];
                $output->writeln("<comment>ID {$i}:</comment> <info>{$nombreMarca}</info>");

                // Si encontramos la que buscas, avisa
                if (stripos($nombreMarca, 'STANLEY') !== false || stripos($nombreMarca, 'STELLA') !== false) {
                    $output->writeln("<error>¡¡ENCONTRADO!! Stanley/Stella es el ID: {$i}</error>");
                }
            } else {
                // $output->writeln("ID {$i}: (Sin datos o vacío)");
            }
        }

        $output->writeln('<info>--- Escaneo finalizado ---</info>');
    }
}