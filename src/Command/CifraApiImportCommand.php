<?php

namespace App\Command;

use App\Entity\Color;
use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Modelo;
use App\Entity\Producto;
use App\Entity\Proveedor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'ss:import-cifra-api',
    description: 'Importa o actualiza productos de Cifra desde su API.'
)]
class CifraApiImportCommand extends Command
{
    private const API_BASE_URL = 'https://api.cifrashop.com';
    private const NOMBRE_PROVEEDOR = 'Cifra';

    private EntityManagerInterface $em;
    private HttpClientInterface $httpClient;
    private SluggerInterface $slugger;
    private ?string $apiToken;
    private array $modelosEnLote = [];

    public function __construct(
        EntityManagerInterface $em,
        HttpClientInterface $httpClient,
        SluggerInterface $slugger
    ) {
        parent::__construct();
        $this->em = $em;
        $this->httpClient = $httpClient;
        $this->slugger = $slugger;
    }

    protected function configure(): void
    {
        $this->addArgument('lang', InputArgument::OPTIONAL, 'Código de idioma ISO 639-1 (ej: es, en, fr)', 'es');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '-1');
        $this->apiToken = "52171de9-969b-451f-ab61-bc786c5a98c1";
        $lang = $input->getArgument('lang');
        $output->writeln('<info>--- INICIO IMPORTACIÓN API CIFRA ---</info>');

        // --- Obtener o crear Proveedor y Fabricante ---
        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => self::NOMBRE_PROVEEDOR]);
        if (!$proveedor) { /* ... crear y flush ... */
            $proveedor = new Proveedor(); $proveedor->setNombre(self::NOMBRE_PROVEEDOR);
            $this->em->persist($proveedor); $this->em->flush();
            $output->writeln('Proveedor Cifra creado.');
        }
        $proveedorId = $proveedor->getId();

        $fabricante = $this->em->getRepository(Fabricante::class)->findOneBy(['nombre' => self::NOMBRE_PROVEEDOR]);
        if (!$fabricante) { /* ... crear y flush ... */
            $fabricante = new Fabricante(); $fabricante->setNombre(self::NOMBRE_PROVEEDOR);
            $this->em->persist($fabricante); $this->em->flush();
            $output->writeln('Fabricante Cifra creado.');
        }
        $fabricanteId = $fabricante->getId();
        // --- Fin Proveedor/Fabricante ---

        // --- Desactivar productos existentes ---
        $output->writeln('Desactivando productos y modelos existentes para Cifra...');
        try {
            $sqlProd = "UPDATE producto p JOIN modelo m ON p.modelo = m.id SET p.activo = 0 WHERE m.proveedor = ?";
            $this->em->getConnection()->prepare($sqlProd)->executeStatement([$proveedorId]);
            $sqlMod = "UPDATE modelo SET activo = 0 WHERE proveedor = ?";
            $this->em->getConnection()->prepare($sqlMod)->executeStatement([$proveedorId]);
            $output->writeln('Productos y modelos desactivados.');
        } catch (\Exception $e) { /* ... error handling ... */ return Command::FAILURE; }
        // --- Fin Desactivar ---

        // --- Llamada a la API ---
        $apiUrl = self::API_BASE_URL . '/tariff/' . $this->apiToken . '/' . $lang;
        $output->writeln("Consultando API: $apiUrl");
        try {
            $response = $this->httpClient->request('GET', $apiUrl, ['timeout' => 120]);
            if ($response->getStatusCode() !== 200) { /* ... error handling ... */ return Command::FAILURE; }
            $productsData = $response->toArray();
            $output->writeln('Datos recibidos: ' . count($productsData) . ' variaciones de producto.');
        } catch (\Exception $e) { /* ... error handling ... */ return Command::FAILURE; }
        // --- Fin Llamada API ---

        // --- Procesamiento de Datos ---
        $output->writeln('Procesando productos...');
        $count = 0; $batchSize = 100; $this->modelosEnLote = [];

        foreach ($productsData as $itemData) {
            $count++;
            $productoReferencia = $itemData['model'] ?? null;
            $modeloReferencia = $itemData['rootmodel'] ?? null;

            if (empty($productoReferencia) || empty($modeloReferencia)) { /* ... skip ... */ continue; }

            try {
                // --- INICIO CORRECCIÓN ---
                // Al principio de CADA iteración, recargamos proveedor y fabricante
                $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                $fabricante = $this->em->find(Fabricante::class, $fabricanteId);
                if (!$proveedor || !$fabricante) {
                    throw new \RuntimeException("Error crítico: Proveedor o Fabricante no encontrados por ID después de clear/find.");
                }
                // --- FIN CORRECCIÓN ---

                // --- Modelo (Buscar o crear usando rootmodel) ---
                $modelo = null;
                if (isset($this->modelosEnLote[$modeloReferencia])) {
                    $modelo = $this->modelosEnLote[$modeloReferencia];
                    if (!$this->em->contains($modelo)) {
                        $modelo = $this->em->find(Modelo::class, $modelo->getId());
                        if (!$modelo) unset($this->modelosEnLote[$modeloReferencia]);
                        else $this->modelosEnLote[$modeloReferencia] = $modelo;
                    }
                }
                if (!$modelo) {
                    // Ahora $proveedor y $fabricante están garantizados como gestionados
                    $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloReferencia, 'proveedor' => $proveedor]);
                    if (!$modelo) {
                        $modelo = new Modelo();
                        $modelo->setProveedor($proveedor);   // <-- Asigna proveedor gestionado
                        $modelo->setFabricante($fabricante); // <-- Asigna fabricante gestionado
                        $modelo->setReferencia($modeloReferencia);
                        // ... (resto de setNombre, setNombreUrl, etc.)
                        $modelo->setNombre(trim($itemData['name'] ?? $modeloReferencia));
                        if (empty($modelo->getNombreUrl())) { $slug = $this->slugger->slug($fabricante->getNombre() . "-" . $modelo->getNombre())->lower(); $modelo->setNombreUrl($slug); }
                        if (!empty($itemData['description'])) $modelo->setDescripcion(trim($itemData['description']));
                        if (!empty($itemData['material'])) $modelo->setComposicion(trim($itemData['material']));
                        if (!empty($itemData['unacaja'])) $modelo->setBox(intval($itemData['unacaja']));
                        if (($productoReferencia === $modeloReferencia) && !empty($itemData['image'])) $modelo->setUrlImage(trim($itemData['image']));
                        $observaciones = []; // ... (código observaciones) ...
                        if(!empty($itemData['unpale'])) $observaciones[]="Palet: ".$itemData['unpale'];
                        if(!empty($itemData['pbcaja'])) $observaciones[]="PB Caja: ".$itemData['pbcaja']."kg";
                        if(!empty($itemData['pncaja'])) $observaciones[]="PN Caja: ".$itemData['pncaja']."kg";
                        if(!empty($itemData['dcaja'])) $observaciones[]="Dim Caja: ".$itemData['dcaja']."cm";
                        if(!empty($itemData['tgrabacion'])) $observaciones[]="T.Grab: ".$itemData['tgrabacion'];
                        if(!empty($itemData['mgrabacion'])) $observaciones[]="M.Grab: ".$itemData['mgrabacion'];
                        if(!empty($observaciones)) $modelo->setObservaciones(implode(' | ',$observaciones));

                        $this->em->persist($modelo); // Persistir modelo NUEVO
                    }
                    $this->modelosEnLote[$modeloReferencia] = $modelo;
                }
                $modelo->setActivo(true);
                if (empty($modelo->getUrlImage()) && ($productoReferencia === $modeloReferencia) && !empty($itemData['image'])) $modelo->setUrlImage(trim($itemData['image']));
                // --- Fin Modelo ---

                // --- Familia ---
                if (!empty($itemData['category'])) {
                    // ... (la lógica usa $proveedor y $fabricante gestionados) ...
                    $nombreFamilia = trim($itemData['category']);
                    $familiaID = self::NOMBRE_PROVEEDOR . "--" . $this->slugger->slug($nombreFamilia)->lower();
                    $familia = $this->em->find(Familia::class, $familiaID) ?? $this->em->getRepository(Familia::class)->findOneBy(['id' => $familiaID]);
                    if (!$familia) {
                        $familia = new Familia(); $familia->setId($familiaID); $familia->setNombre($nombreFamilia);
                        $slug = $this->slugger->slug($nombreFamilia . "-" . self::NOMBRE_PROVEEDOR)->lower(); $familia->setNombreUrl($slug);
                        $familia->setProveedor($proveedor); // <-- Asigna proveedor gestionado
                        $this->em->persist($familia);
                    }
                    $familia->setMarca($fabricante); // <-- Asigna fabricante gestionado
                    if (!$familia->getModelosOneToMany()->contains($modelo)) $familia->addModelosOneToMany($modelo);
                    $this->em->persist($familia); // Persistir por si acaso
                }
                // --- Fin Familia ---

                // --- Producto ---
                $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $productoReferencia]);
                if (!$producto) { $producto = new Producto(); $producto->setReferencia($productoReferencia); }
                $producto->setModelo($modelo); $producto->setActivo(true);
                if (!empty($itemData['image'])) $producto->setUrlImage(trim($itemData['image']));

                // << INICIO CÓDIGO NUEVO >>
                // Imágenes adicionales (viewsImages)
                $additionalImages = [];
                if (isset($itemData['images']) && is_array($itemData['images']) && !empty($itemData['images'])) {
                    foreach ($itemData['images'] as $imgUrl) {
                        // Validar y limpiar la URL si es necesario
                        if (is_string($imgUrl) && !empty(trim($imgUrl)) && filter_var(trim($imgUrl), FILTER_VALIDATE_URL)) {
                            $additionalImages[] = trim($imgUrl);
                        }
                    }
                }

                // Combinar con imágenes existentes (si las hubiera, aunque la desactivación las borra)
                // y con la imagen principal (si queremos incluirla también en viewsImages)
                $existingViews = $producto->getViewsImages() ? explode(',', $producto->getViewsImages()) : [];
                $mainImage = $producto->getUrlImage() ? [$producto->getUrlImage()] : []; // Incluir la principal si existe

                // Unir todas, eliminar duplicados y vacíos, y volver a unir con comas
                $allViewImages = array_filter(array_unique(array_merge($mainImage, $additionalImages, $existingViews)));
                $producto->setViewsImages(!empty($allViewImages) ? implode(',', $allViewImages) : null);
                // << FIN CÓDIGO NUEVO >>


                $precio = isset($itemData['confidential_price']) ? floatval(str_replace(',', '.', $itemData['confidential_price'])) : 0.0;
                $producto->setPrecioUnidad($precio); $producto->setPrecioPack($precio); $producto->setPrecioCaja($precio);

                // --- Color y Talla ---
                $color = null;
                $tallaCode = null;
                $colorCodeExtraido = null; // Para guardar el color extraído del model si es necesario

                // **Paso 1: Intentar obtener color de la API**
                $colorData = $itemData['color'] ?? null;
                if ($colorData && !empty($colorData['id']) && !empty($colorData['name'])) {
                    // ... (Lógica existente para buscar/crear color desde API data - SIN CAMBIOS) ...
                    $colorCodeApi = strtoupper(trim($colorData['id']));
                    $colorNameApi = trim($colorData['name']);
                    $colorId = self::NOMBRE_PROVEEDOR . "-" . $this->slugger->slug($colorCodeApi)->lower();
                    $color = $this->em->find(Color::class, $colorId) ?? $this->em->getRepository(Color::class)->findOneBy(['id' => $colorId]);

                    if (!$color) {
                        $color = new Color(); $color->setId($colorId); $color->setNombre($colorNameApi);
                        $color->setProveedor($proveedor);
                        if (!empty($colorData['rgb_hex']) && preg_match('/^[a-fA-F0-9]{6}$/', $colorData['rgb_hex'])) $color->setCodigoRGB('#' . strtoupper($colorData['rgb_hex']));
                        $this->em->persist($color);
                    } elseif ($color->getNombre() !== $colorNameApi || /* ... update RGB ... */
                        (!empty($colorData['rgb_hex']) && preg_match('/^[a-fA-F0-9]{6}$/', $colorData['rgb_hex']) && (!$color->getCodigoRGB() || strtoupper($color->getCodigoRGB()) !== '#' . strtoupper($colorData['rgb_hex']))))
                    {
                        $color->setNombre($colorNameApi);
                        if (!empty($colorData['rgb_hex']) && preg_match('/^[a-fA-F0-9]{6}$/', $colorData['rgb_hex'])) $color->setCodigoRGB('#' . strtoupper($colorData['rgb_hex']));
                        $this->em->persist($color);
                    } elseif (! $color->getCodigoRGB() && !empty($colorData['rgb_hex']) && preg_match('/^[a-fA-F0-9]{6}$/', $colorData['rgb_hex'])) {
                        $color->setCodigoRGB('#' . strtoupper($colorData['rgb_hex'])); $this->em->persist($color);
                    }
                }

                // **Paso 2: Analizar 'model' para TALLA y posible COLOR extraído**
                if ($productoReferencia !== $modeloReferencia) {
                    $diff = trim(str_replace($modeloReferencia, '', $productoReferencia), '-');
                    $parts = explode('-', $diff);

                    if (count($parts) >= 1) { // Necesitamos al menos un fragmento (color o talla+color)
                        // El último SIEMPRE es el posible código de color extraído
                        $colorCodeExtraido = strtoupper(array_pop($parts));

                        // Si quedan partes, son la talla
                        if (!empty($parts)) {
                            $tallaCode = strtoupper(implode('-', $parts)); // Ej: XXL, 38-40
                        }
                    }
                }

                // **Paso 3: Usar color extraído SOLO si el del API falló**
                if (!$color && $colorCodeExtraido) {
                    $output->writeln("<comment>Color '{$colorCodeExtraido}' extraído de la referencia para {$productoReferencia} (API color ausente).</comment>");
                    $colorId = self::NOMBRE_PROVEEDOR . "-" . $this->slugger->slug($colorCodeExtraido)->lower();
                    $color = $this->em->find(Color::class, $colorId) ?? $this->em->getRepository(Color::class)->findOneBy(['id' => $colorId]);
                    if (!$color) {
                        $color = new Color(); $color->setId($colorId); $color->setNombre($colorCodeExtraido); // Usar código como nombre
                        $color->setProveedor($proveedor);
                        $this->em->persist($color);
                    }
                }

                // **Paso 4: Asignar color (asegurando que no sea null)**
                if (!$color) {
                    // Si después de API y extracción no hay color, asignar Blanco por defecto
                    $output->writeln("<warning>Color no determinado para {$productoReferencia}. Asignando 'Blanco'.</warning>");
                    $defaultColorId = self::NOMBRE_PROVEEDOR . "-" . $this->slugger->slug('BL')->lower();
                    $color = $this->em->find(Color::class, $defaultColorId) ?? $this->em->getRepository(Color::class)->findOneBy(['id' => $defaultColorId]);
                    if (!$color) { /* ... crear color Blanco ... */
                        $color = new Color(); $color->setId($defaultColorId); $color->setNombre('Blanco');
                        $color->setCodigoRGB('#FFFFFF'); $color->setProveedor($proveedor);
                        $this->em->persist($color);
                    }
                }
                $producto->setColor($color);

                // **Paso 5: Asignar talla**
//                $producto->setTalla($tallaCode ?? 'Talla Unica');
                // --- Talla (Priorizar 'attributes' array, luego 'Talla Unica') ---
                $tallaValue = 'Talla Unica'; // Valor por defecto
                if (isset($itemData['attributes']) && is_array($itemData['attributes'])) {
                    foreach ($itemData['attributes'] as $attribute) {
                        if (isset($attribute['id'], $attribute['value']) && $attribute['id'] === 'clothing_size' && !empty(trim($attribute['value']))) {
                            $tallaValue = trim($attribute['value']);
                            break; // Talla encontrada, salimos del bucle de atributos
                        }
                    }
                }
                $producto->setTalla($tallaValue);
                // --- Fin Talla ---
                // --- Fin Color y Talla ---

                // --- Medidas ---
                $medidas = [];
                if(!empty($itemData['length'])&&floatval(str_replace(',','.',$itemData['length']))>0) $medidas[]=str_replace(',','.',$itemData['length']);
                if(!empty($itemData['width'])&&floatval(str_replace(',','.',$itemData['width']))>0) $medidas[]=str_replace(',','.',$itemData['width']);
                if(!empty($itemData['height'])&&floatval(str_replace(',','.',$itemData['height']))>0) $medidas[]=str_replace(',','.',$itemData['height']);
                if(!empty($medidas)) $producto->setMedidas(implode(' x ',$medidas).' cm');
                // --- Fin Medidas ---

                $this->em->persist($producto);
                // --- Fin Producto ---


                // --- Batch Processing ---
                if ($count % $batchSize === 0) {
                    $output->writeln("Procesados $count productos... guardando lote...");
                    $this->em->flush(); $this->em->clear(); $this->modelosEnLote = [];
                    $output->writeln("Lote guardado y memoria liberada.");
                }
                // --- Fin Batch Processing ---

            } catch (\Exception $e) { /* ... error handling + clear + limpiar caché ... */
                $output->writeln('<error>Excepción procesando item ' . $count . ' (Ref Prod: ' . ($productoReferencia ?? 'N/A') . ', Ref Mod: ' . ($modeloReferencia ?? 'N/A') . '): ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . '</error>');
                if (!$this->em->isOpen()) { /* ... error fatal ... */ return Command::FAILURE; }
                $this->em->clear(); $this->modelosEnLote = [];
                continue;
            }
        } // Fin foreach $productsData

        $output->writeln("Fin procesamiento API. Guardando cambios finales...");
        $this->em->flush(); $this->em->clear();
        $output->writeln("<info>IMPORTACIÓN API CIFRA TERMINADA CORRECTAMENTE.</info>");
        // --- Fin Procesamiento ---


        // --- Ajuste Final Precios Mínimos ---
        $output->writeln("AJUSTANDO PRECIOS MÍNIMOS DE MODELOS CIFRA");
        // Usar findOneBy para obtener el proveedor inicial aquí
        $_proveedorInitial = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => self::NOMBRE_PROVEEDOR]);
        if ($_proveedorInitial) {
            $proveedorIdAjuste = $_proveedorInitial->getId(); // Guardar ID
            $query = $this->em->getRepository(Modelo::class)->createQueryBuilder('m')
                ->where('m.proveedor = :prov')->andWhere('m.activo = :activo')
                ->setParameter('prov', $_proveedorInitial)->setParameter('activo', true)->getQuery();

            $countAjuste = 0; $batchSizeAjuste = 100;
            foreach ($query->toIterable() as $modeloAjuste) {
                try {
                    // --- CORRECCIÓN: Recargar Proveedor dentro del bucle ---
                    $proveedorAjuste = $this->em->find(Proveedor::class, $proveedorIdAjuste);
                    if(!$proveedorAjuste) throw new \RuntimeException("Proveedor perdido en ajuste precios");
                    // --- FIN CORRECCIÓN ---

                    if (!$this->em->contains($modeloAjuste)) {
                        $modeloAjuste = $this->em->find(Modelo::class, $modeloAjuste->getId());
                        if (!$modeloAjuste) continue;
                    }
                    // Re-asociar proveedor gestionado por si acaso
                    $modeloAjuste->setProveedor($proveedorAjuste);

                    $precioMinimo = $modeloAjuste->getPrecioUnidad();
                    $modeloAjuste->setPrecioMin($precioMinimo ?? 0); $modeloAjuste->setPrecioMinAdulto($precioMinimo ?? 0);
                    $this->em->persist($modeloAjuste); $countAjuste++;
                    if ($countAjuste % $batchSizeAjuste === 0) {
                        $output->writeln("...precios mínimos ajustados: $countAjuste...");
                        $this->em->flush(); $this->em->clear();
                        // El proveedor se recargará al inicio de la siguiente iteración
                    }
                } catch (\Exception $e) { /* ... error handling + clear ... */
                    $output->writeln('<error>Excepcion al ajustar precio mínimo modelo ' . ($modeloAjuste->getReferencia() ?? 'ID:'.$modeloAjuste->getId()) . ': ' . $e->getMessage() . '</error>');
                    if (!$this->em->isOpen()) { /* ... error fatal ... */ return Command::FAILURE; }
                    $this->em->clear();
                    // El proveedor se recargará al inicio
                }
            }
            $output->writeln("...guardando ajustes de precios mínimos finales...");
            $this->em->flush(); $this->em->clear();
            $output->writeln("<info>AJUSTE DE PRECIOS MÍNIMOS TERMINADO.</info>");
        } else { $output->writeln("<error>Proveedor Cifra no encontrado para el ajuste final de precios.</error>"); }
        // --- Fin Ajuste Final ---

        return Command::SUCCESS;
    } // Fin execute
} // Fin clase