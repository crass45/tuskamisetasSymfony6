<?php

namespace App\Command;

use App\Entity\Color;
use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Modelo;
use App\Entity\Producto; // <-- Asegúrate que es App\Entity\Producto y no Productos
use App\Entity\Proveedor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;


#[AsCommand(
    name: 'ss:import_command_jhk',
    description: 'Importa en base de datos el excel de productos de JHK'
)]
class JHKImportCommand extends Command
{
    private EntityManagerInterface $em;
    private SluggerInterface $slugger;

    public function __construct(EntityManagerInterface $em, SluggerInterface $slugger)
    {
        parent::__construct();
        $this->em = $em;
        $this->slugger = $slugger;
    }

    private function tofloati($num): float { /* ... no changes ... */
        $dotPos = strrpos($num, '.');
        $commaPos = strrpos($num, ',');
        $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos :
            ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);

        if (!$sep) {
            return floatval(preg_replace("/[^0-9]/", "", $num));
        }

        return floatval(
            preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . '.' .
            preg_replace("/[^0-9]/", "", substr($num, $sep + 1, strlen($num)))
        );
    }

    protected function configure() { /* ... no changes ... */
        $this->addArgument(
            'filename',
            InputArgument::OPTIONAL,
            'Nombre del archivo CSV de productos que vamos a importar'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '-1');
        // ... (inicialización de variables $filename, $filenamePrecios, $nombreProveedor) ...
        $filename = $input->getArgument('filename');
        $filenamePrecios = "tarifaJHK.csv";
        $nombreProveedor = "JHK";

        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);

        if (!file_exists($filename)) {
            $output->writeln("<error>El fichero de productos $filename no existe</error>");
            return Command::FAILURE;
        }

        // --- Gestión Proveedor/Fabricante ---
        if ($proveedor == null) {
            $proveedor = new Proveedor();
            $proveedor->setNombre($nombreProveedor);
            $this->em->persist($proveedor);
            // Flush inicial para obtener ID si es nuevo
            $this->em->flush();
            $output->writeln("<comment>Proveedor JHK creado.</comment>");
        }
        $proveedorId = $proveedor->getId(); // Guardar ID

        $fabricante = $this->em->getRepository(Fabricante::class)->findOneBy(['nombre' => $nombreProveedor]);
        if ($fabricante == null) {
            $fabricante = new Fabricante();
            $fabricante->setNombre($nombreProveedor);
            $this->em->persist($fabricante);
            // Flush inicial para obtener ID si es nuevo
            $this->em->flush();
            $output->writeln("<comment>Fabricante JHK creado.</comment>");
        }
        $fabricanteId = $fabricante->getId(); // Guardar ID

        // --- Desactivación ---
        $output->writeln("Desactivando productos y modelos existentes para JHK...");
        $sql = "UPDATE producto p JOIN modelo m ON p.modelo = m.id SET p.activo = 0 WHERE m.proveedor = ?"; // <-- Corregido nombre columna
        $this->em->getConnection()->prepare($sql)->executeStatement([$proveedorId]);
        $sql = "UPDATE modelo SET activo = 0 WHERE proveedor = ?"; // <-- Corregido nombre columna
        $this->em->getConnection()->prepare($sql)->executeStatement([$proveedorId]);
        $output->writeln("Productos y modelos desactivados.");
        // --- Fin Desactivación ---


        // --- Lectura CSV Productos ---
        $output->writeln("Procesando archivo de productos: $filename");
        $header = null; $data = []; $lineNum = 0;
        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($rowCsv = fgetcsv($handle, 0, ';')) !== FALSE) {
                $lineNum++;
                if (!$header) {
                    $header = $rowCsv;
                } else {
                    if (count($header) == count($rowCsv)) {
                        // Trim whitespace from keys and values
                        $cleanedRow = [];
                        foreach(array_combine($header, $rowCsv) as $key => $value){
                            $cleanedRow[trim($key)] = trim($value);
                        }
                        $data[] = $cleanedRow;
                    } else {
                        $output->writeln("<warning>Línea $lineNum CSV productos: número de columnas incorrecto (" . count($rowCsv) . " vs " . count($header) ."). Saltando.</warning>");
                    }
                }
            }
            fclose($handle);
        } else { /* ... error handling ... */
            $output->writeln("<error>No se pudo abrir el archivo $filename</error>");
            return Command::FAILURE;
        }
        // --- Fin Lectura CSV Productos ---

        $output->writeln("Leídas " . count($data) . " filas de datos. Procesando...");
        $rowCount = 0;
        // Iteramos sobre $data
        foreach ($data as $row) {
            $rowCount++;
            $productoReferencia = null; // Para mensajes de error

            try {
                // --- Asegurar entidades base gestionadas ---
                // Al inicio de CADA iteración, comprobamos si están gestionadas. Si no, las buscamos por ID.
                if (!$this->em->contains($proveedor)) {
                    $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                }
                if (!$this->em->contains($fabricante)) {
                    $fabricante = $this->em->find(Fabricante::class, $fabricanteId);
                }
                // Si después de buscar no existen, es un error grave.
                if (!$proveedor || !$fabricante) {
                    throw new \Exception("Proveedor o Fabricante no encontrados tras clear.");
                }
                // --- Fin entidades base ---

                $productoReferencia = $row["Combinacion"] ?? null;
                if (empty($productoReferencia)) { /* ... skip ... */
                    $output->writeln("<comment>Fila $rowCount sin 'Combinacion', saltando.</comment>");
                    continue;
                }

                $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $productoReferencia]);
                if (!$producto) {
                    $producto = new Producto();
                    $producto->setReferencia($productoReferencia);
                }

                $modeloReferencia = $row["Referencia"] ?? null;
                if (empty($modeloReferencia)) { /* ... skip ... */
                    $output->writeln("<comment>Fila $rowCount sin 'Referencia' de modelo, saltando.</comment>");
                    continue;
                }

                $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloReferencia]);
                if (!$modelo) {
                    $modelo = new Modelo();
                    $modelo->setProveedor($proveedor); // Usar $proveedor gestionado
                    $modelo->setFabricante($fabricante); // Usar $fabricante gestionado
                    $modelo->setReferencia($modeloReferencia);
                    $this->em->persist($modelo); // Persistir modelo nuevo aquí
                }

                $producto->setModelo($modelo);
                // Asegúrate que addProducto existe y maneja la relación bidireccional
                if (method_exists($modelo, 'addProducto')) {
                    $modelo->addProducto($producto);
                } elseif (method_exists($modelo, 'addModeloHasProducto')) {
                    $modelo->addModeloHasProducto($producto);
                } else {
                    // Fallback o lanzar error si el método no existe
                    $modelo->getProductos()->add($producto); // Asumiendo que getProductos devuelve Collection
                }

                // --- Resto de campos (Nombre, Descripcion, Composicion...) ---
                if (!empty($row["Nombre"])) { /* ... setNombre, setNombreUrl con slugger ... */
                    $nombreModelo = trim($row["Nombre"]);
                    $modelo->setNombre($nombreModelo);
                    if (empty($modelo->getNombreUrl())) { // Solo si está vacío
                        $slug = $this->slugger->slug($fabricante->getNombre() . "-" . $nombreModelo)->lower();
                        $modelo->setNombreUrl($slug);
                    }
                }
                if (!empty($row["Descripcion"])) { /* ... setDescripcion ... */
                    if (empty($modelo->getDescripcion())) { // Solo si está vacío
                        $modelo->setDescripcion(html_entity_decode($row["Descripcion"]));
                        // $modelo->mergeNewTranslations(); // Descomentar si usas KNP
                    }
                }
                if (!empty($row["Composicion"])) { $modelo->setComposicion($row["Composicion"]); }
                if (!empty($row["box"])) { $modelo->setBox(intval($row["box"])); }
                if (!empty($row["bag"])) { $modelo->setPack(intval($row["bag"])); }
                // --- Fin Resto de campos ---

                // --- Familia ---
                if (!empty($row["type_product"])) {
                    $nombreFamilia = trim($row["type_product"]);
                    $familiaID = $nombreProveedor . "--" . $this->slugger->slug($nombreFamilia)->lower();

                    // Buscar primero si ya está gestionada en esta iteración
                    $familiaBBDD = $this->em->find(Familia::class, $familiaID);

                    if ($familiaBBDD == null) { // Si no está gestionada, buscar en BBDD
                        $familiaBBDD = $this->em->getRepository(Familia::class)->findOneBy(['id' => $familiaID]);

                        if ($familiaBBDD == null) { // Si tampoco está en BBDD, crearla
                            $familiaBBDD = new Familia();
                            $familiaBBDD->setId($familiaID); // Establecer ID manual
                            $familiaBBDD->setNombre($nombreFamilia);
                            // $familiaBBDD->setNombreOld($nombreFamilia); // Ajustar si es necesario
                            $slug = $this->slugger->slug($nombreFamilia . "-" . $nombreProveedor)->lower();
                            $familiaBBDD->setNombreUrl($slug);
                            $familiaBBDD->setProveedor($proveedor); // Usar $proveedor gestionado
                            $this->em->persist($familiaBBDD);
                        }
                    }
                    // Asociar siempre
                    $familiaBBDD->setMarca($fabricante); // Usar $fabricante gestionado
                    $familiaBBDD->addModelosOneToMany($modelo); // Método correcto
                    $this->em->persist($familiaBBDD); // Asegurar persistencia de la familia
                }
                // --- Fin Familia ---


                if (!empty($row["Talla"])) { $producto->setTalla($row["Talla"]); }

                // --- Color ---
                if (!empty($row["Color"])) {
                    $colorNombre = trim($row["Color"]);
                    $colorId = $nombreProveedor . "-" . $this->slugger->slug($colorNombre)->lower();

                    // Buscar primero si ya está gestionada
                    $color = $this->em->find(Color::class, $colorId);

                    if ($color == null) { // Si no, buscar en BBDD
                        $color = $this->em->getRepository(Color::class)->findOneBy(['id' => $colorId]);

                        if ($color == null) { // Si no, crear
                            $color = new Color();
                            $color->setId($colorId); // Establecer ID manual
                            $color->setNombre($colorNombre);
                            $color->setProveedor($proveedor); // Usar $proveedor gestionado
                            $this->em->persist($color);
                        }
                    }
                    $producto->setColor($color); // Asociar color gestionado/encontrado/nuevo
                }
                // --- Fin Color ---


                // --- Imágenes ---
                if (!empty($row["URLSku"])) { /* ... setUrlImage, setViewsImages ... */
                    $cadenaImagenOriginal = str_replace("http://resources.jhktshirt.com/", "https://s3-eu-west-1.amazonaws.com/resources.jhktshirt.com/", $row["URLSku"]);
                    $producto->setUrlImage($cadenaImagenOriginal);
                    $cadenaImagen = substr($cadenaImagenOriginal, 0, -4);
                    $producto->setViewsImages($cadenaImagen . ".jpg," . $cadenaImagen . "_back.jpg," . $cadenaImagen . "_side.jpg");
                }
                if (!empty($row["URLCatalogue"]) && empty($modelo->getUrlImage())) { /* ... setUrlImage ... */
                    $cadenaImagenOriginal = str_replace("http://resources.jhktshirt.com/", "https://s3-eu-west-1.amazonaws.com/resources.jhktshirt.com/", $row["URLCatalogue"]);
                    $modelo->setUrlImage($cadenaImagenOriginal);
                }
                // --- Fin Imágenes ---


                // --- Color Extractor (Comentado temporalmente para simplificar) ---
                /*
                if ($producto->getUrlImage() && $producto->getColor() && empty($producto->getColor()->getCodigoRGB())) {
                     try {
                          $imagePath = $producto->getUrlImage();
                          if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                               // Asegurarse que el color está gestionado antes de pasarlo
                               if (!$this->em->contains($producto->getColor())) {
                                    // Si no está gestionado (pudo venir de BBDD o ser nuevo y no persistido aun)
                                    // Intentamos obtener la referencia gestionada si existe, o persistimos si es nuevo
                                    $managedColor = $this->em->find(Color::class, $producto->getColor()->getId());
                                    if ($managedColor) {
                                        $producto->setColor($managedColor); // Usar la versión gestionada
                                    } else {
                                         // Si no se encuentra con find, es que es realmente nuevo
                                         $this->em->persist($producto->getColor());
                                         // No hacemos flush aquí, se hará con el lote
                                    }
                               }

                               $palette = Palette::fromFilename($imagePath, Palette::MAX_PIXEL_COUNT_IGNORE);
                               $extractor = new ColorExtractor($palette);
                               $colors = $extractor->extract(1);
                               if (!empty($colors)) {
                                    $hexColor = \League\ColorExtractor\Color::fromIntToHex($colors[0]);
                                    if (preg_match('/^#[a-f0-9]{6}$/i', $hexColor)) {
                                         $producto->getColor()->setCodigoRGB($hexColor);
                                         // $this->em->persist($producto->getColor()); // Ya está marcado para persistir si es necesario
                                    } else { // ... warning ... }
                               }
                          } else { // ... warning ... }
                     } catch (\Exception $e) { // ... error ... }
                }
                */
                // --- Fin Color Extractor ---

                // --- Precios Base ---
                if (isset($row["Picking"]) && $row["Picking"] !== '') {
                    $precioBase = $this->tofloati($row["Picking"]);
                    $producto->setPrecioCaja($precioBase);
                    $producto->setPrecioPack($precioBase);
                    $producto->setPrecioUnidad($precioBase);
                }
                // --- Fin Precios Base ---


                $producto->setActivo(!empty($producto->getUrlImage()));
                $modelo->setActivo(true); // Modelo activo si tiene al menos un producto procesado

                // Persistir explícitamente el producto (modelo ya se persiste si es nuevo)
                $this->em->persist($producto);

                // --- Batch Processing ---
                if ($rowCount % 100 === 0) {
                    $output->writeln("Procesados $rowCount productos... guardando lote...");
                    try {
                        $this->em->flush(); // Guarda todo lo pendiente (modelos, productos, colores, familias nuevas)
                        $this->em->clear(); // Vacía el Identity Map
                        $output->writeln("Lote guardado y memoria liberada.");
                        // Las entidades $proveedor y $fabricante se recargarán al inicio de la siguiente iteración
                    } catch (\Exception $flushException) {
                        $output->writeln("<error>Error flushing batch at row $rowCount: " . $flushException->getMessage() . "</error>");
                        throw $flushException; // Relanzar para detener el proceso o manejar recuperación
                    }
                }
                // --- Fin Batch Processing ---

            } catch (\Exception $e) {
                $output->writeln('<error>Excepción general en fila ' . $rowCount . ' (Ref: ' . ($productoReferencia ?? 'N/A') . '): ' . $e->getMessage() . '</error>');
                if (!$this->em->isOpen()) { /* ... error fatal ... */
                    $output->writeln("<error>EntityManager cerrado. Abortando.</error>");
                    return Command::FAILURE;
                }
                // En caso de error en una fila, limpiamos para evitar estado inconsistente
                $this->em->clear();
                // No es necesario recargar proveedor/fabricante aquí, se hará al inicio de la siguiente iteración
                continue; // Saltar a la siguiente fila
            }
        } // Fin foreach $data

        $output->writeln("Fin procesamiento archivo productos. Guardando cambios finales...");
        $this->em->flush(); // Guardar el último lote
        $this->em->clear();
        $output->writeln("<info>IMPORTACIÓN DE PRODUCTOS TERMINADA CORRECTAMENTE</info>");


        // --- ACTUALIZACIÓN DE PRECIOS ---
        if (file_exists($filenamePrecios)) {
            // ... (La lógica de actualización de precios con lectura CSV nativa y batch processing es similar) ...
            $output->writeln("ACTUALIZANDO PRECIOS desde $filenamePrecios");

            $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);
            if (!$proveedor) { /* ... error ... */ return Command::FAILURE; }
            $proveedorId = $proveedor->getId();

            $output->writeln("Desactivando productos JHK antes de actualizar precios...");
            $sql = "UPDATE producto p JOIN modelo m ON p.modelo = m.id SET p.activo = 0 WHERE m.proveedor = ?"; // <-- Corregido
            $this->em->getConnection()->prepare($sql)->executeStatement([$proveedorId]);


            // --- Lectura CSV Precios ---
            $headerPrecios = null; $dataPrecios = []; $lineNumPrecios = 0;
            if (($handlePrecios = fopen($filenamePrecios, 'r')) !== FALSE) {
                while (($rowCsvPrecios = fgetcsv($handlePrecios, 0, ';')) !== FALSE) {
                    $lineNumPrecios++;
                    if (!$headerPrecios) {
                        $headerPrecios = $rowCsvPrecios;
                    } else {
                        if (count($headerPrecios) == count($rowCsvPrecios)) {
                            $cleanedRow = [];
                            foreach(array_combine($headerPrecios, $rowCsvPrecios) as $key => $value){
                                $cleanedRow[trim($key)] = trim($value);
                            }
                            $dataPrecios[] = $cleanedRow;
                        } else { /* ... warning ... */
                            $output->writeln("<warning>Línea $lineNumPrecios CSV precios: número de columnas incorrecto. Saltando.</warning>");
                        }
                    }
                }
                fclose($handlePrecios);
            } else { /* ... warning ... */
                $output->writeln("<error>No se pudo abrir el archivo de precios $filenamePrecios</error>");
                // Considerar si continuar o fallar
            }
            // --- Fin Lectura CSV Precios ---

            $output->writeln("Leídas " . count($dataPrecios) . " filas de precios. Actualizando...");
            $rowCountPrecios = 0; $updatedCount = 0;
            foreach ($dataPrecios as $row) {
                $rowCountPrecios++;
                $productoReferencia = $row["SKU"] ?? null;
                if (empty($productoReferencia) || !isset($row["PRECIO"]) || $row["PRECIO"] === '') {
                    $output->writeln("<comment>Fila $rowCountPrecios en archivo de precios sin SKU o PRECIO.</comment>");
                    continue;
                }

                try {
                    $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $productoReferencia]);

                    if ($producto) {
                        // Asegurarse que el producto y su modelo están gestionados
                        if (!$this->em->contains($producto)) {
                            $producto = $this->em->find(Producto::class, $producto->getId()); // Recargar producto por ID si no está
                            if(!$producto) continue; // Si no se encuentra, saltar
                        }
                        if ($producto->getModelo() && !$this->em->contains($producto->getModelo())) {
                            $modeloRecargado = $this->em->find(Modelo::class, $producto->getModelo()->getId());
                            if ($modeloRecargado) {
                                $producto->setModelo($modeloRecargado);
                            } else {
                                $output->writeln("<warning>Modelo para producto {$productoReferencia} no encontrado tras clear. Saltando actualización de precio.</warning>");
                                continue;
                            }
                        }

                        $precio = $this->tofloati($row["PRECIO"]);

                        if ($precio >= 0) {
                            $producto->setPrecioUnidad($precio);
                            $producto->setPrecioCaja($precio);
                            $producto->setPrecioPack($precio);
                            $producto->setActivo($precio > 0); // Activo si precio > 0

                            if ($producto->isActivo() && $producto->getModelo() && !$producto->getModelo()->isActivo()) {
                                $producto->getModelo()->setActivo(true);
                                $this->em->persist($producto->getModelo());
                            }
                            $this->em->persist($producto);
                            $updatedCount++;

                            if ($updatedCount % 100 === 0) {
                                $output->writeln("...precios actualizados: $updatedCount...");
                                $this->em->flush();
                                $this->em->clear();
                                // No necesitamos recargar proveedor explícitamente aquí
                            }
                        } else { /* ... warning ... */ }
                    } else {
                        // $output->writeln("<comment>SKU {$productoReferencia} no encontrado en BBDD.</comment>");
                    }
                } catch (\Exception $e) {
                    $output->writeln("<error>Error actualizando precio fila $rowCountPrecios (Ref: $productoReferencia): ".$e->getMessage()."</error>");
                    if (!$this->em->isOpen()) { /* ... error fatal ... */ return Command::FAILURE; }
                    $this->em->clear(); // Limpiar en caso de error
                    continue; // Saltar a la siguiente fila
                }
            } // Fin foreach dataPrecios
            $output->writeln("...guardando precios restantes...");
            $this->em->flush();
            $this->em->clear();
            $output->writeln("<info>ACTUALIZACIÓN DE PRECIOS TERMINADA. $updatedCount productos actualizados.</info>");
        } else { /* ... warning ... */ }
        // --- Fin ACTUALIZACIÓN DE PRECIOS ---


        // --- AJUSTE FINAL DE PRECIOS MÍNIMOS ---
        $output->writeln("AJUSTANDO PRECIOS MÍNIMOS DE MODELOS JHK");
        // Es importante hacer esto *después* de que todos los precios de productos estén actualizados
        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);
        if ($proveedor) {
            $proveedorId = $proveedor->getId();
            // Usar iterableResult para no cargar todos los modelos en memoria
            $query = $this->em->getRepository(Modelo::class)
                ->createQueryBuilder('m')
                ->where('m.proveedor = :prov')
                ->andWhere('m.activo = :activo')
                ->setParameter('prov', $proveedor)
                ->setParameter('activo', true)
                ->getQuery();

            $count = 0;
            $batchSize = 100; // Ajustar tamaño del lote si es necesario
            foreach ($query->toIterable() as $modelo) {
                try {
                    // getPrecioUnidad() debe calcular el mínimo de sus productos *activos*
                    $precioMinimo = $modelo->getPrecioUnidad();
                    $modelo->setPrecioMin($precioMinimo ?? 0); // Usar 0 si no hay productos activos
                    $modelo->setPrecioMinAdulto($precioMinimo ?? 0); // Simplificado por ahora

                    $this->em->persist($modelo);
                    $count++;

                    if ($count % $batchSize === 0) {
                        $output->writeln("...precios mínimos ajustados: $count...");
                        $this->em->flush();
                        $this->em->clear(); // Limpiar EM
                        // Recargar proveedor para la próxima iteración (necesario tras clear)
                        $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                        if(!$proveedor){
                            $output->writeln("<error>Error recargando proveedor en ajuste final de precios. Abortando.</error>");
                            return Command::FAILURE;
                        }
                    }
                } catch (\Exception $e) {
                    $output->writeln('<error>Excepcion al ajustar precio mínimo modelo ' . ($modelo->getReferencia() ?? 'ID:'.$modelo->getId()) . ': ' . $e->getMessage() . '</error>');
                    if (!$this->em->isOpen()) { /* ... error fatal ... */ return Command::FAILURE; }
                    $this->em->clear();
                    // Recargar proveedor tras el clear por error
                    $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                    if(!$proveedor){
                        $output->writeln("<error>Error recargando proveedor tras error ajuste precios. Abortando.</error>");
                        return Command::FAILURE;
                    }
                }
            }
            $output->writeln("...guardando ajustes de precios mínimos finales...");
            $this->em->flush(); // Guardar el último lote
            $this->em->clear();
            $output->writeln("<info>AJUSTE DE PRECIOS MÍNIMOS TERMINADO.</info>");
        } else { /* ... error ... */ }
        // --- Fin AJUSTE FINAL ---

        return Command::SUCCESS;
    }
}