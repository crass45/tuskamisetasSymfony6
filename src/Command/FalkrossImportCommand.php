<?php

namespace App\Command; // Namespace actualizado

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
// Entidades actualizadas
use App\Entity\Color;
use App\Entity\Fabricante;
use App\Entity\Familia; // Añadido
use App\Entity\Modelo;
use App\Entity\Producto; // Cambiado de Productos a Producto
use App\Entity\Proveedor;
use App\Entity\ModeloAtributo;
// Servicios
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface; // Para rutas
use Symfony\Component\String\Slugger\SluggerInterface; // Para slugs

// Eliminado Ddeboer\DataImport\Reader\CsvReader
// Eliminado PrecioProductoCantidad (no se usaba)
// Eliminado Utiles

#[AsCommand(
    name: 'ss:import_command_falkross',
    description: 'Importa en base de datos el excel de productos de Falk&Ross'
)]
class FalkrossImportCommand extends Command // Extiende de Command
{
    private EntityManagerInterface $em;
    private SluggerInterface $slugger;
    private string $projectDir;
    private string $publicDir;

    public function __construct(
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        KernelInterface $kernel
    ) {
        parent::__construct();
        $this->em = $em;
        $this->slugger = $slugger;
        $this->projectDir = $kernel->getProjectDir(); // Ruta raíz del proyecto
        $this->publicDir = $kernel->getProjectDir() . '/public'; // Ruta a /public
    }

    //region --- MÉTODOS AUXILIARES (Funciones de ayuda) ---

    // Nueva función para leer CSV (reemplaza CsvReader)
    private function readCsv(string $filename, OutputInterface $output, string $keyColumn, string $delimiter = ';'): ?array
    {
        if (!file_exists($filename)) {
            $output->writeln("<error>El fichero $filename no existe.</error>");
            return null;
        }
        $header = null; $data = []; $lineNum = 0;
        $f = fopen($filename, 'r');
        if ($f === FALSE) { $output->writeln("<error>No se pudo abrir el archivo $filename</error>"); return null; }
        $bom = "\xEF\xBB\xBF";
        if (fgets($f, 4) !== $bom) { rewind($f); } // Manejo de BOM

        while (($rowCsv = fgetcsv($f, 0, $delimiter)) !== FALSE) {
            $lineNum++;
            if ( (count($rowCsv) === 1 && $rowCsv[0] === null) || empty(array_filter($rowCsv, function($val) { return $val !== null && $val !== ''; })) ) { continue; }
            if (!$header) {
                $header = array_map(function($key) { $key = preg_replace('/^\xEF\xBB\xBF/', '', $key); return trim($key, " \t\n\r\0\x0B\""); }, $rowCsv);
                if (!in_array($keyColumn, $header)) {
                    $output->writeln("<error>La cabecera requerida '{$keyColumn}' no se encuentra en $filename. Cabeceras detectadas: ".implode(', ', $header)."</error>");
                    fclose($f); return null;
                }
            } else {
                if (count($header) == count($rowCsv)) {
                    $rowData = @array_combine($header, $rowCsv);
                    if ($rowData === false) { $output->writeln("<warning>Línea $lineNum: Error al combinar cabeceras. Saltando.</warning>"); continue; }
                    $data[] = array_map('trim', $rowData);
                } else { $output->writeln("<warning>Línea $lineNum: número de columnas incorrecto (" . count($rowCsv) . " vs " . count($header) ."). Saltando.</warning>"); }
            }
        }
        fclose($f);
        return $data;
    }

    // Tu función original, ahora privada
    private function formatArticleNumberFixed($number)
    {
        $numStr = (string)$number; $length = strlen($numStr);
        $part1 = '0'; $part2 = '';
        if ($length > 2) {
            $part1 = substr($numStr, 0, $length - 2); $part2 = substr($numStr, -2);
        } else { $part2 = str_pad($numStr, 2, '0', STR_PAD_LEFT); }
        $formattedPart1 = str_pad($part1, 3, '0', STR_PAD_LEFT);
        return $formattedPart1 . '.' . $part2;
    }

    // Tu función original, ahora privada
    private function tofloat($num)
    {
        $dotPos = strrpos($num, '.'); $commaPos = strrpos($num, ',');
        $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos : ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);
        if (!$sep) { return floatval(preg_replace("/[^0-9]/", "", $num)); }
        return floatval(preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . '.' . preg_replace("/[^0-9]/", "", substr($num, $sep + 1, strlen($num))));
    }

    // ... (resto de funciones html2rgb, distancel2, color_mkwebsafe como 'private') ...
    private function html2rgb($color) { /* ... (código original) ... */
        if ($color[0] == '#') $color = substr($color, 1);
        if (strlen($color) == 6) list($r, $g, $b) = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);
        elseif (strlen($color) == 3) list($r, $g, $b) = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
        else return false;
        $r = hexdec($r); $g = hexdec($g); $b = hexdec($b);
        return array($r, $g, $b);
    }
    private function distancel2(array $color1, array $color2) { /* ... (código original) ... */
        return sqrt(pow($color1[0] - $color2[0], 2) + pow($color1[1] - $color2[1], 2) + pow($color1[2] - $color2[2], 2));
    }
    private function color_mkwebsafe($in) { /* ... (código original) ... */
        $vals['r'] = hexdec(substr($in, 1, 2)); $vals['g'] = hexdec(substr($in, 3, 2)); $vals['b'] = hexdec(substr($in, 5, 2));
        $out = "";
        foreach ($vals as $val) { $val = (round($val / 51) * 51); $out .= str_pad(dechex($val), 2, '0', STR_PAD_LEFT); }
        return $out;
    }
    //endregion

    protected function configure()
    {
        $this->addArgument('filename', InputArgument::REQUIRED, 'Nombre del archivo CSV principal (ej: falk2019.csv)');
        $this->addArgument('multipart', InputArgument::OPTIONAL, '0: Importa principal. 2: Importa traducciones, imágenes, atributos, etc.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int // Devuelve int
    {
        ini_set('memory_limit', '-1');
        $filename = $input->getArgument('filename');
        $multiparte = $input->getArgument('multipart') ?? 0;

        // $em = ... // $this->em ya está disponible
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null); // Buena optimización

        $nombreProveedor = "Falk_Ross";
        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);
        if (null === $proveedor) {
            $proveedor = new Proveedor(); $proveedor->setNombre($nombreProveedor);
            $this->em->persist($proveedor); $this->em->flush();
        }
        $proveedorId = $proveedor->getId(); // Guardar ID para usar después de 'clear'

        // Construir ruta absoluta al archivo principal
        $mainCsvPath = $this->projectDir . '/' . $filename;

        if (file_exists($mainCsvPath)) {
            if ($multiparte == 0) {
                $output->writeln("Modo 'multipart' 0: Desactivando productos antiguos del proveedor...");
                // Corregido: Usar _id para nombres de columna SQL
                $this->em->getConnection()->prepare('UPDATE modelo SET activo = 0 WHERE proveedor = :pid')->executeStatement(['pid' => $proveedorId]);
                $this->em->getConnection()->prepare('UPDATE producto p JOIN modelo m ON p.modelo = m.id SET p.activo = 0 WHERE m.proveedor = :pid')->executeStatement(['pid' => $proveedorId]);
                $output->writeln("Productos desactivados.");
            }

            // Leer CSV principal
            $data = $this->readCsv($mainCsvPath, $output, 'article number short'); // Clave principal es 'article number short'
            if ($data === null) {
                $output->writeln("<error>No se pudo leer el archivo principal $mainCsvPath</error>");
                return Command::FAILURE;
            }

            $output->writeln("Iniciando importación del fichero principal...");
            $this->cargaFichero($data, $proveedorId, $output); // Pasar solo ID
            $output->writeln("<info>SE TERMINA DE IMPORTAR EL FICHERO PRINCIPAL</info>");

        } else if ($multiparte == 2) {
            $output->writeln("<comment>El fichero principal no existe, pero se procede con multiparte 2.</comment>");
        } else {
            $output->writeln("<error>El fichero " . $mainCsvPath . " no existe</error>");
            return Command::FAILURE;
        }

        // --- Lógica para la segunda parte (traducciones, imágenes, etc.) ---
        if ($multiparte == 2) {
            // Pasar IDs a los métodos
            $this->procesarTraducciones($output, $proveedorId);
            $this->procesarImagenes($output, $proveedorId);
            $this->procesarFichasTecnicas($output, $proveedorId);
            $this->procesarAtributos($output, $proveedorId);
            $this->procesarRelacionados($output, $proveedorId);
            $this->procesarCertificados($output, $proveedorId);
            $this->ajustarPreciosFinales($output, $proveedorId);
        }

        return Command::SUCCESS;
    }

    // Refactorizado para usar ID y cargar entidades internamente
    private function cargaFichero(array $data, int $proveedorId, OutputInterface $output)
    {
        $modelosCache = []; $fabricantesCache = []; $coloresCache = [];
        $i = 0; $batchSize = 100;

        foreach ($data as $row) {
            $i++;
            $modeloReferencia = $row["article number short"] ?? null;
            if (empty($modeloReferencia)) { /* ... skip ... */ continue; }

            try {
                // --- Recargar entidades base ---
                $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                if (!$proveedor) throw new \RuntimeException("Proveedor $proveedorId no encontrado.");
                // --- Fin recarga ---

                // --- Modelo ---
                if (!isset($modelosCache[$modeloReferencia])) {
                    $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloReferencia, 'proveedor' => $proveedor]);
                    if (null === $modelo) { $modelo = new Modelo(); $modelo->setReferencia($modeloReferencia); $modelo->setProveedor($proveedor); }
                    $modelosCache[$modeloReferencia] = $modelo;
                } else { $modelo = $modelosCache[$modeloReferencia]; }
                // --- Fin Modelo ---

                // --- Validación y Producto ---
                if (empty($row["color_name"]) || !isset($row["color_code"]) || $row["color_code"] === '') {
                    // $output->writeln('<comment>Saltando fila ' . $i . ' (ref: ' . ($row["article number long"] ?? 'N/A') . ') por no tener color.</comment>');
                    continue;
                }
                $productoReferencia = $row["article number long"] ?? null;
                if (!$productoReferencia) { continue; }
                $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $productoReferencia]);
                if (null === $producto) { $producto = new Producto(); $producto->setReferencia($productoReferencia); }
                // --- Fin Producto ---

                // --- Color ---
                $colorId = $modelo->getFabricante()->getNombreUrl() . "-" . $this->slugger->slug($row["color_name"])->lower(); // Slug para ID
                if (!isset($coloresCache[$colorId])) {
                    $color = $this->em->find(Color::class, $colorId); // Buscar por ID
                    if ($color === null) {
                        $color = new Color(); $color->setId($colorId); $color->setNombre($row["color_name"]);
                        $color->setProveedor($proveedor); // Asignar proveedor gestionado
                    }
                    $coloresCache[$colorId] = $color;
                }
                $color = $coloresCache[$colorId];
                $color->setCodigoColor($row["color_code"]);
                $producto->setColor($color);
                $this->em->persist($color);
                // --- Fin Color ---

                $producto->setActivo(true);
                $producto->setTalla($row["size_name"] ?? null);
                if (!empty($row["Pictogram"])) $producto->setUrlImage("/resources/Piktogramme/" . $row["Pictogram"]);

                // --- Fabricante ---
                if (!empty($row["supplier_name"])) {
                    $nombreFabricante = $row['supplier_name'];
                    if (!isset($fabricantesCache[$nombreFabricante])) {
                        $fabricante = $this->em->getRepository(Fabricante::class)->findOneBy(['nombre' => $nombreFabricante]);
                        if (null === $fabricante) {
                            $fabricante = new Fabricante(); $fabricante->setNombre($nombreFabricante);
                            $this->em->persist($fabricante); // Persistir nuevo fabricante
                        }
                        $fabricantesCache[$nombreFabricante] = $fabricante;
                    }
                    $modelo->setFabricante($fabricantesCache[$nombreFabricante]);
                } else { continue; } // Saltar si no hay fabricante
                // --- Fin Fabricante ---

                $modelo->setActivo(true);
                // $modelo->setProveedor($proveedor); // Ya asignado al crear
                // Corregido: Usar addProducto (de App\Entity\Modelo)
                if (method_exists($modelo, 'addProducto') && !$modelo->getProductos()->contains($producto)) {
                    $modelo->addProducto($producto);
                }
                $producto->setModelo($modelo); // Asegurar bidireccionalidad

                $modelo->setSupplierArticleName($row["supplier_article_name"] ?? null);
                $modelo->setNombre($row["article_name"] ?? null);
                if ($modelo->getNombreUrl() === null && $modelo->getNombre() !== null && $modelo->getFabricante() !== null) {
                    $modelo->setNombreUrl($this->slugger->slug($modelo->getFabricante()->getNombre() . "-" . $modelo->getNombre())->lower());
                }
                $modelo->setDescripcion($row["article_description"] ?? null);
                $modelo->setIsNovelty(($row["new article"] ?? 0) != 0);
                $modelo->setPack($row["Pieces_in_Pack"] ?? null);
                $modelo->setBox($row["Pieces_in_Karton"] ?? null);

                // --- Precios ---
                // Corregido: Usar 'customer_price /1-3/' del código original
                $precio = 0.0;
                if (!empty($row["customer_price /1-3/"])) {
                    $precio = $this->tofloat($row["customer_price /1-3/"]);
                } elseif (!empty($row["10box_price"])) { // Fallback al otro campo de precio
                    $precio = $this->tofloat($row["10box_price"]);
                }
                $producto->setPrecioCaja($precio); $producto->setPrecioUnidad($precio); $producto->setPrecioPack($precio);
                if($precio <= 0) $producto->setActivo(false);
                // --- Fin Precios ---

                $this->em->persist($modelo); $this->em->persist($producto);

                if (($i % $batchSize) === 0) {
                    $output->writeln("Procesados $i productos... guardando lote...");
                    $this->em->flush(); $this->em->clear();
                    // Limpiar cachés locales
                    $modelosCache = []; $fabricantesCache = []; $coloresCache = [];
                }

            } catch (\Exception $e) {
                $output->writeln('<error>Excepción al guardar (fila ' . $i . '): ' . $e->getMessage() . '</error>');
                if (!$this->em->isOpen()) {
                    $output->writeln('<error>EntityManager cerrado, intentando reiniciar...</error>');
                    // $this->em = $this->em->create($this->em->getConnection(), $this->em->getConfiguration()); // Método deprecado
                    // En Symfony moderno, si el EM se cierra, es un error fatal.
                    throw $e; // Relanzar
                }
                $this->em->clear(); // Limpiar en caso de error
                $modelosCache = []; $fabricantesCache = []; $coloresCache = []; // Limpiar cachés
            }
        } // Fin foreach
    }

    //region --- MÉTODOS PRIVADOS PARA MULTIPARTE 2 ---

    private function procesarTraducciones(OutputInterface $output, int $proveedorId)
    {
        $archivo = $this->projectDir . "/falkTraduccion.csv";
        $data = $this->readCsv($archivo, $output, 'article number short with point');
        if (!$data) { $output->writeln("<comment>No existe o no se pudo leer el fichero de traducciones.</comment>"); return; }
        $output->writeln("Procesando traducciones...");

        $rowCount = 0; $batchSize = 100;
        foreach ($data as $row) {
            $rowCount++;
            if (strtolower($row["language"]) === "spanish" && !empty($row["article number short with point"])) {
                $ref = $row["article number short with point"];
                $modeloBBDD = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $ref]);
                if ($modeloBBDD) {
                    $modeloBBDD->setNombre(trim($row["article_name"]));
                    $modeloBBDD->setDescripcion(trim($row["article_description"]));
                    // $modeloBBDD->mergeNewTranslations(); // Descomentar si KNP está configurado
                    $this->em->persist($modeloBBDD);
                    if ($rowCount % $batchSize === 0) { $this->em->flush(); $this->em->clear(); }
                }
            }
        }
        $this->em->flush(); $this->em->clear();
        $output->writeln("<info>Traducciones terminadas.</info>");
    }

    private function procesarFichasTecnicas(OutputInterface $output, int $proveedorId)
    {
        $archivo = $this->projectDir . "/falkFichasTecnicas.csv";
        $data = $this->readCsv($archivo, $output, 'article number short with point');
        if (!$data) { $output->writeln("<comment>No existe fichero de fichas técnicas.</comment>"); return; }
        $output->writeln("Procesando fichas tecnicas...");

        $rowCount = 0; $batchSize = 100;
        $proveedor = $this->em->find(Proveedor::class, $proveedorId); // Cargar una vez
        if(!$proveedor) { $output->writeln("<error>Proveedor no encontrado para Fichas</error>"); return; }

        foreach ($data as $row) {
            $rowCount++;
            if (!empty($row["article number short with point"])) {
                $ref = $row["article number short with point"];
                // Buscar por referencia Y proveedor para asegurar
                $modeloBBDD = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $ref, 'proveedor' => $proveedor]);
                if ($modeloBBDD) {
                    $modeloBBDD->setUrlFichaTecnica("/resources/sizespecs/" . trim($row["filename"]));
                    $this->em->persist($modeloBBDD);
                    if ($rowCount % $batchSize === 0) {
                        $this->em->flush(); $this->em->clear();
                        $proveedor = $this->em->find(Proveedor::class, $proveedorId); // Recargar
                        if (!$proveedor) throw new \RuntimeException("Proveedor perdido Fichas");
                    }
                }
            }
        }
        $this->em->flush(); $this->em->clear();
        $output->writeln("<info>Fichas Tecnicas terminadas.</info>");
    }

    private function procesarRelacionados(OutputInterface $output, int $proveedorId)
    {
        $archivo = $this->projectDir . "/falkRelacionados.csv";
        $data = $this->readCsv($archivo, $output, 'article number short with point');
        if (!$data) { $output->writeln("<comment>No existe fichero de relacionados.</comment>"); return; }
        $output->writeln("Procesando productos relacionados...");

        $modelosCache = [];
        foreach ($data as $row) {
            if (!empty($row["article number short with point"])) {
                $ref = $row["article number short with point"];
                $refRelacionado = $this->formatArticleNumberFixed($row["link to"]); // Usar la función de formato

                if (!isset($modelosCache[$ref])) {
                    $modelosCache[$ref] = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $ref]);
                }
                if (!isset($modelosCache[$refRelacionado])) {
                    $modelosCache[$refRelacionado] = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $refRelacionado]);
                }

                $modeloBBDD = $modelosCache[$ref] ?? null;
                $modeloBBDDRel = $modelosCache[$refRelacionado] ?? null;

                if ($modeloBBDD && $modeloBBDDRel) {
                    // Usar SQL nativo para INSERT IGNORE es eficiente
                    $this->em->getConnection()->executeStatement(
                        "INSERT IGNORE INTO modelo_modeloRelacionado (modelo_id, RELACIONADO_ID) VALUES (:modelo_id, :modelorel_id)",
                        ['modelo_id' => $modeloBBDD->getId(), 'modelorel_id' => $modeloBBDDRel->getId()]
                    );
                }
            }
        }
        // No es necesario flush/clear para SQL nativo si no se modifican entidades gestionadas
        $output->writeln("<info>Relacionados terminados.</info>");
    }

    private function procesarCertificados(OutputInterface $output, int $proveedorId)
    {
        $archivo = $this->projectDir . "/falkCertificados.csv";
        $data = $this->readCsv($archivo, $output, 'article number short with point');
        if (!$data) { $output->writeln("<comment>No existe fichero de certificados.</comment>"); return; }
        $output->writeln("Procesando certificados...");

        // Almacenar temporalmente los certificados por referencia de modelo
        $certificadosPorModelo = [];
        foreach ($data as $row) {
            if (!empty($row["article number short with point"]) && !empty($row["file"])) {
                $ref = $row["article number short with point"];
                $certificadoUrl = "/resources/certificates/" . trim($row["file"]);

                if (!isset($certificadosPorModelo[$ref])) {
                    $certificadosPorModelo[$ref] = [];
                }
                // Añadir solo si no está ya (evita duplicados del CSV)
                if (!in_array($certificadoUrl, $certificadosPorModelo[$ref])) {
                    $certificadosPorModelo[$ref][] = $certificadoUrl;
                }
            }
        }

        // Ahora, actualizar la BBDD en un solo bucle
        $proveedor = $this->em->find(Proveedor::class, $proveedorId);
        if(!$proveedor) { $output->writeln("<error>Proveedor no encontrado para Certificados</error>"); return; }

        $rowCount = 0; $batchSize = 100;
        foreach ($certificadosPorModelo as $ref => $certificados) {
            $rowCount++;
            $modeloBBDD = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $ref, 'proveedor' => $proveedor]);
            if ($modeloBBDD) {
                // Unir los certificados encontrados con comas
                $modeloBBDD->setCertificados(implode(',', $certificados));
                $this->em->persist($modeloBBDD);

                if ($rowCount % $batchSize === 0) {
                    $this->em->flush(); $this->em->clear();
                    $proveedor = $this->em->find(Proveedor::class, $proveedorId); // Recargar
                    if (!$proveedor) throw new \RuntimeException("Proveedor perdido Certificados");
                }
            }
        }
        $this->em->flush(); $this->em->clear();
        $output->writeln("<info>Certificados procesados.</info>");
    }

    // REEMPLAZA ESTE MÉTODO EN src/Command/FalkrossImportCommand.php

    private function procesarImagenes(OutputInterface $output, int $proveedorId)
    {
        $archivo = $this->projectDir . "/falkImagenes.csv";
        // Usar 'article number short with point' como clave para leer el CSV
        $data = $this->readCsv($archivo, $output, 'article number short with point');
        if (!$data) {
            $output->writeln("<comment>No existe fichero de imágenes.</comment>");
            return;
        }
        $output->writeln("Procesando imágenes...");

        $modelosCache = [];
        $modelAdditionalImagesCache = []; // Caché para imágenes adicionales del modelo
        $rowCount = 0;
        $batchSize = 100; // Haremos flush/clear cada 100 modelos procesados

        // --- PASO 1: Iterar sobre el CSV y aplicar la lógica ---

        // Agrupar filas por referencia de modelo para procesar eficientemente
        $imagenesPorModelo = [];
        foreach ($data as $row) {
            if (!empty($row["article number short with point"]) && !empty($row["file"])) {
                $ref = $row["article number short with point"];
                $imagenesPorModelo[$ref][] = $row;
            }
        }

        // Cargar el proveedor una vez
        $proveedor = $this->em->find(Proveedor::class, $proveedorId);
        if(!$proveedor) { $output->writeln("<error>Proveedor no encontrado Imágenes</error>"); return; }

        foreach ($imagenesPorModelo as $ref => $rows) {
            $rowCount++;

            // --- Cargar el Modelo ---
            // (No usamos caché aquí porque necesitamos el objeto gestionado fresco en cada lote)
            $modeloBBDD = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $ref, 'proveedor' => $proveedor]);
            if (!$modeloBBDD) {
                // $output->writeln("<comment>Imágenes: Modelo $ref no encontrado.</comment>");
                continue; // Saltar si el modelo no existe
            }

            // --- Lógica de Inicialización (del método antiguo) ---
            // Resetea 'otherImages' y prepara la caché para este modelo
            $modeloBBDD->setOtherImages("");
            $modelAdditionalImagesCache[$ref] = '';
            // --- Fin Inicialización ---

            // --- Iterar sobre todas las imágenes de ESTE modelo ---
            foreach ($rows as $row) {
                $colorNumber = $row["colour number"] ?? '';
                $imagenUrl = ("/resources/images/" . trim($row["file"])) ?? null;
                if (empty($imagenUrl)) continue;

                // Si es una imagen genérica del modelo (no por color)
                if ($colorNumber == "-" || $colorNumber == "") {
                    $imageType = $row["image type"] ?? '';

                    // Si es "principal" (no 'b', 'sl', 'sr', 'f')
                    if (!in_array($imageType, ['b', 'sl', 'sr', 'f'])) {
                        $modeloBBDD->setUrlImage($imagenUrl);
                        // Añadir a 'otherImages' para consistencia
                        $currentOtherImages = $modeloBBDD->getOtherImages();
                        $newOtherImages = empty($currentOtherImages) ? $imagenUrl : $currentOtherImages . "," . $imagenUrl;
                        $modeloBBDD->setOtherImages($newOtherImages);
                    } else {
                        // Es una imagen adicional ('b', 'sl', 'sr'). La acumulamos en la caché.
                        // (Se excluye 'f' (frontal) según tu lógica original)
                        if (!in_array($imageType, ['f'])) {
                            $currentAdditionalImages = $modelAdditionalImagesCache[$ref];
                            $newAdditionalImages = empty($currentAdditionalImages) ? $imagenUrl : $currentAdditionalImages . "," . $imagenUrl;
                            $modelAdditionalImagesCache[$ref] = $newAdditionalImages;
                        }
                    }
                    $this->em->persist($modeloBBDD);

                } else {
                    // Si es una imagen de un producto específico (por color)
                    // (Usamos getProductos() de la entidad App\Entity\Modelo)
                    foreach ($modeloBBDD->getProductos() as $producto) {
                        if ($producto->getColor() && $producto->getColor()->getCodigoColor() == $colorNumber) {
                            // 1. Asignar imagen de color como principal del producto
                            $producto->setUrlImage($imagenUrl);

                            // 2. Recuperar imágenes adicionales del modelo (acumuladas en la caché)
                            $additionalImages = $modelAdditionalImagesCache[$ref] ?? '';

                            // 3. Combinar imagen principal del producto + adicionales del modelo
                            $allViewImages = $imagenUrl;
                            if (!empty($additionalImages)) {
                                $allViewImages .= "," . $additionalImages;
                            }

                            // 4. Asignar cadena completa a setViewsImages
                            $producto->setViewsImages($allViewImages);
                            $this->em->persist($producto);
                        }
                    }
                }
            } // Fin bucle foreach $rows

            // --- Lógica de Limpieza (del método antiguo) ---
            // Limpiar duplicados de 'otherImages' al final del procesamiento de CADA modelo
            $urlPrincipal = $modeloBBDD->getUrlImage();
            $todasLasOtrasUrls = $modeloBBDD->getOtherImages();
            if (!empty($urlPrincipal) && !empty($todasLasOtrasUrls)) {
                $arrayDeOtrasUrls = explode(',', $todasLasOtrasUrls);
                $arrayFiltrado = array_diff($arrayDeOtrasUrls, [$urlPrincipal]); // Quitar principal
                $arrayUnico = array_unique($arrayFiltrado); // Quitar duplicados
                $cadenaFinal = implode(',', $arrayUnico);
                $modeloBBDD->setOtherImages($cadenaFinal);
                $this->em->persist($modeloBBDD);
            }
            // --- Fin Lógica Limpieza ---

            // --- Batch processing ---
            if ($rowCount % $batchSize === 0) {
                $output->writeln("...imágenes procesadas para $rowCount modelos...");
                $this->em->flush();
                $this->em->clear();
                // Recargar proveedor (necesario para el próximo findOneBy)
                $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                if (!$proveedor) throw new \RuntimeException("Proveedor perdido en ProcesarImagenes");
                $modelAdditionalImagesCache = []; // Limpiar caché de imágenes
            }
        } // Fin bucle principal (foreach $imagenesPorModelo)

        $this->em->flush();
        $this->em->clear();
        $output->writeln("<info>Imágenes procesadas y limpiadas.</info>");
    }

    private function procesarAtributos(OutputInterface $output, int $proveedorId)
    {
        $archivo = $this->projectDir . "/falkAtributos.csv";
        $data = $this->readCsv($archivo, $output, 'article number short with point');
        if (!$data) { $output->writeln("<comment>No existe fichero de atributos.</comment>"); return; }
        $output->writeln("Procesando atributos...");

        // Limpieza previa
        $this->em->getConnection()->executeStatement("DELETE FROM modelo_modeloatributos WHERE modelo_id IN (SELECT id FROM modelo WHERE proveedor = ?)", [$proveedorId]);
        $output->writeln("Atributos antiguos borrados.");

        $proveedor = $this->em->find(Proveedor::class, $proveedorId); // Cargar
        if(!$proveedor) { $output->writeln("<error>Proveedor no encontrado para Atributos</error>"); return; }

        $atributosCache = []; // Caché para entidades Atributo
        $rowCount = 0; $batchSize = 100;

        foreach ($data as $row) {
            $rowCount++;
            if (strtolower($row['language']) == 'es' && !empty($row["article number short with point"])) {
                $ref = $row["article number short with point"];
                $modeloBBDD = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $ref, 'proveedor' => $proveedor]);

                if ($modeloBBDD && !empty($row["article attribute"])) {
                    $atributoNombre = trim($row["article attribute"]);
                    $atributoTipo = trim($row['type of article attribute'] ?? 'General');

                    if (!isset($atributosCache[$atributoNombre])) {
                        $atributo = $this->em->getRepository(ModeloAtributo::class)->findOneBy(['valor' => $atributoNombre]);
                        if (!$atributo) {
                            $atributo = new ModeloAtributo();
                            $atributo->setValor($atributoNombre); // Asumiendo que existe setValor
                            $this->em->persist($atributo);
                        }
                        $atributosCache[$atributoNombre] = $atributo;
                    }
                    $atributo = $atributosCache[$atributoNombre];
                    $atributo->setNombre($atributoTipo); // Asumiendo setNombre es el tipo

                    if (!$modeloBBDD->getAtributos()->contains($atributo)) {
                        $modeloBBDD->addAtributo($atributo); // Asumiendo addAtributo
                        $this->em->persist($modeloBBDD);
                    }
                    $this->em->persist($atributo); // Persistir atributo (nuevo o modificado)

                    if ($rowCount % $batchSize === 0) {
                        try {
                            $this->em->flush(); $this->em->clear();
                            $proveedor = $this->em->find(Proveedor::class, $proveedorId); // Recargar
                            if (!$proveedor) throw new \RuntimeException("Proveedor perdido Atributos");
                            $atributosCache = []; // Limpiar caché de atributos
                        } catch (UniqueConstraintViolationException $e) {
                            $output->writeln("<warning>Error de duplicado en atributos, limpiando y continuando...</warning>");
                            if (!$this->em->isOpen()) { $output->writeln('<error>EntityManager cerrado.</error>'); return; }
                            $this->em->clear();
                            $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                            if (!$proveedor) throw new \RuntimeException("Proveedor perdido Atributos post-error");
                            $atributosCache = [];
                        }
                    }
                }
            }
        }
        $this->em->flush(); $this->em->clear();
        $output->writeln("<info>Atributos procesados.</info>");
    }

    private function ajustarPreciosFinales(OutputInterface $output, int $proveedorId)
    {
        $output->writeln("AJUSTANDO PRECIOS MÍNIMOS DE MODELOS Falk_Ross");
        $_proveedorInitialAjuste = $this->em->getRepository(Proveedor::class)->findOneBy(['id' => $proveedorId]);
        if ($_proveedorInitialAjuste) {
            $proveedorIdAjuste = $_proveedorInitialAjuste->getId();
            $query = $this->em->getRepository(Modelo::class)->createQueryBuilder('m')
                ->where('m.proveedor = :prov')->andWhere('m.activo = :activo')
                ->setParameter('prov', $_proveedorInitialAjuste)->setParameter('activo', true)->getQuery();
            $countAjuste = 0; $batchSizeAjuste = 100;

            foreach ($query->toIterable() as $row) {
                /** @var Modelo $modeloAjuste */
                $modeloAjuste = $row;

                try {
                    $proveedorAjuste = $this->em->find(Proveedor::class, $proveedorIdAjuste);
                    if(!$proveedorAjuste) throw new \RuntimeException("Proveedor perdido en ajuste precios");
                    if (!$this->em->contains($modeloAjuste)) {
                        $modeloAjuste = $this->em->find(Modelo::class, $modeloAjuste->getId());
                        if (!$modeloAjuste) continue;
                    }
                    $modeloAjuste->setProveedor($proveedorAjuste);

                    $precioMinimo = $modeloAjuste->getPrecioUnidad();
                    $modeloAjuste->setPrecioMin($precioMinimo ?? 0);

                    if ($modeloAjuste->getPrecioCantidadBlancas(10000) > 0) {
                        $modeloAjuste->setPrecioMinAdulto($modeloAjuste->getPrecioCantidadBlancas(10000));
                    } else {
                        if ($modeloAjuste->getPrecioCantidadBlancasNino(10000) > 0) {
                            $modeloAjuste->setPrecioMinAdulto($modeloAjuste->getPrecioCantidadBlancasNino(10000));
                        } else {
                            $modeloAjuste->setPrecioMinAdulto($modeloAjuste->getPrecioUnidad());
                        }
                    }

                    $this->em->persist($modeloAjuste); $countAjuste++;
                    if ($countAjuste % $batchSizeAjuste === 0) {
                        $output->writeln("...precios mínimos ajustados: $countAjuste...");
                        $this->em->flush(); $this->em->clear();
                    }
                } catch (\Exception $e) {
                    $output->writeln('<error>Excepcion al ajustar precio mínimo modelo ' . ($modeloAjuste->getReferencia() ?? 'ID:'.$modeloAjuste->getId()) . ': ' . $e->getMessage() . '</error>');
                    if (!$this->em->isOpen()) { $output->writeln('<error>EntityManager cerrado.</error>'); return; }
                    $this->em->clear();
                }
            }
            $output->writeln("...guardando ajustes de precios mínimos finales...");
            $this->em->flush(); $this->em->clear();
            $output->writeln("<info>AJUSTE DE PRECIOS MÍNIMOS TERMINADO.</info>");
        } else { $output->writeln("<error>Proveedor Falk_Ross no encontrado para ajuste final.</error>"); }
    }

    //endregion
}