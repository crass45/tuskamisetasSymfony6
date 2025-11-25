<?php

namespace App\Command;

// Entidades
use App\Entity\Color;
use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Genero;
use App\Entity\Modelo;
use App\Entity\Producto;
use App\Entity\Proveedor;
// Servicios
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'ss:import-sols-v2',
    description: 'Importa productos de Sols desde los archivos models.csv y products.csv'
)]
class SolsImportV2Command extends Command
{
    private const NOMBRE_PROVEEDOR = 'Sols';

    private EntityManagerInterface $em;
    private SluggerInterface $slugger;

    public function __construct(EntityManagerInterface $em, SluggerInterface $slugger)
    {
        parent::__construct();
        $this->em = $em;
        $this->slugger = $slugger;
    }

    protected function configure(): void
    {
        $this->addArgument('modelsFile', InputArgument::REQUIRED, 'Ruta al archivo sologroup_models.csv');
        $this->addArgument('productsFile', InputArgument::REQUIRED, 'Ruta al archivo sologroup_products.csv');
        $this->addArgument('pricesFile', InputArgument::REQUIRED, 'Ruta al archivo CSV de tarifas especiales');
    }

    // Función auxiliar para leer CSV nativo
    // Reemplaza esta función entera en SolsImportV2Command.php
    private function readCsv(string $filename, OutputInterface $output, string $delimiter = ';'): ?array
    {
        if (!file_exists($filename)) {
            $output->writeln("<error>El fichero $filename no existe.</error>");
            return null;
        }

        $header = null;
        $data = [];
        $lineNum = 0;

        // Abrir el archivo
        $f = fopen($filename, 'r');
        if ($f === FALSE) {
            $output->writeln("<error>No se pudo abrir el archivo $filename</error>");
            return null;
        }

        // Detectar y saltar BOM (Byte Order Mark) UTF-8
        $bom = "\xEF\xBB\xBF";
        if (fgets($f, 4) !== $bom) {
            // No es BOM o no es UTF-8 BOM, rebobinar
            rewind($f);
        }

        while (($rowCsv = fgetcsv($f, 0, $delimiter)) !== FALSE) {
            $lineNum++;

            // Comprobación para saltar líneas completamente vacías
            if ( (count($rowCsv) === 1 && $rowCsv[0] === null) ||
                empty(array_filter($rowCsv, function($val) { return $val !== null && $val !== ''; })) ) {
                // Opcional: $output->writeln("<comment>Línea $lineNum: Fila vacía, saltando.</comment>");
                continue; // Saltar línea vacía
            }

            if (!$header) {
                // Limpiar cabeceras (quitar espacios Y comillas)
                $header = array_map(function($key) {
                    return trim($key, " \t\n\r\0\x0B\"");
                }, $rowCsv);

                // Salvaguarda final para el primer elemento (por si fgetcsv lo cogió)
                if (isset($header[0])) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
                }

                // Validar que las cabeceras clave existen
                if (!in_array('SKU', $header)) {
                    $output->writeln("<error>La cabecera 'SKU' no se encuentra en $filename. Cabeceras detectadas: ".implode(', ', $header)."</error>");
                    fclose($f);
                    return null; // Error fatal si SKU falta
                }

            } else {
                // Combinar cabeceras y fila
                if (count($header) == count($rowCsv)) {
                    $rowData = @array_combine($header, $rowCsv);
                    if ($rowData === false) {
                        $output->writeln("<warning>Línea $lineNum: Error al combinar cabeceras (conteo: ".count($header)." vs ".count($rowCsv)."). Saltando.</warning>");
                        continue;
                    }

                    // Trim de todos los valores antes de añadir
                    $data[] = array_map('trim', $rowData);

                } else {
                    $output->writeln("<warning>Línea $lineNum: número de columnas incorrecto (" . count($rowCsv) . " vs " . count($header) ."). Saltando.</warning>");
                }
            }
        }
        fclose($f);

        return $data;
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '-1');
        $modelsFile = $input->getArgument('modelsFile');
        $productsFile = $input->getArgument('productsFile');
        $pricesFile = $input->getArgument('pricesFile');

        // --- 1. Obtener/Crear Proveedor y Fabricante ---
        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => self::NOMBRE_PROVEEDOR]);
        if (!$proveedor) {
            $proveedor = new Proveedor(); $proveedor->setNombre(self::NOMBRE_PROVEEDOR);
            $this->em->persist($proveedor); $this->em->flush();
            $output->writeln('Proveedor ' . self::NOMBRE_PROVEEDOR . ' creado.');
        }
        $proveedorId = $proveedor->getId();

        $fabricante = $this->em->getRepository(Fabricante::class)->findOneBy(['nombre' => self::NOMBRE_PROVEEDOR]);
        if (!$fabricante) {
            $fabricante = new Fabricante(); $fabricante->setNombre(self::NOMBRE_PROVEEDOR);
            $this->em->persist($fabricante); $this->em->flush();
            $output->writeln('Fabricante ' . self::NOMBRE_PROVEEDOR . ' creado.');
        }
        $fabricanteId = $fabricante->getId();
        // --- Fin Proveedor/Fabricante ---

        // --- 2. Desactivar productos existentes ---
        $output->writeln('Desactivando productos y modelos existentes para ' . self::NOMBRE_PROVEEDOR . '...');
        try {
            $sqlProd = "UPDATE producto p JOIN modelo m ON p.modelo = m.id SET p.activo = 0 WHERE m.proveedor = ?";
            $this->em->getConnection()->prepare($sqlProd)->executeStatement([$proveedorId]);
            $sqlMod = "UPDATE modelo SET activo = 0 WHERE proveedor = ?";
            $this->em->getConnection()->prepare($sqlMod)->executeStatement([$proveedorId]);
            $output->writeln('Productos y modelos desactivados.');
        } catch (\Exception $e) { $output->writeln('<error>Error al desactivar: '.$e->getMessage().'</error>'); return Command::FAILURE; }
        // --- Fin Desactivar ---

        // --- 3. Procesar sologroup_models.csv ---
        $output->writeln("Procesando archivo de Modelos: $modelsFile");
        $modelsData = $this->readCsv($modelsFile, $output);
        if ($modelsData === null) return Command::FAILURE;

        $output->writeln(count($modelsData) . " modelos encontrados. Creando/actualizando Modelos...");
        $rowCount = 0; $batchSize = 100;
        foreach ($modelsData as $row) {
            $rowCount++;
            $modeloReferencia = null;

            try {
                // Recargar entidades base tras clear
                $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                $fabricante = $this->em->find(Fabricante::class, $fabricanteId);
                if (!$proveedor || !$fabricante) throw new \RuntimeException("Proveedor o Fabricante perdidos.");

                $modeloReferenciaCsv = $row['SKU'] ?? null; // SKU de modelo es la referencia

                if (empty($modeloReferenciaCsv)) {
                    $output->writeln("<comment>Fila $rowCount (Modelos) sin 'SKU', saltando.</comment>"); continue;
                }

                $modeloReferencia = $proveedor->getNombre() . "_" . $modeloReferenciaCsv; // Prefijo SOLS_
                $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloReferencia, 'proveedor' => $proveedor]);
                if (!$modelo) {
                    $modelo = new Modelo();
                    $modelo->setProveedor($proveedor);
                    $modelo->setFabricante($fabricante);
                    $modelo->setReferencia($modeloReferencia);
                }
                $modelo->setActivo(true); // Se activa, los productos hijos lo mantendrán activo si tienen precio

                $modelo->setNombre(trim($row['Product Name'] ?? $modeloReferencia));
                if (empty($modelo->getNombreUrl())) {
                    $slug = $this->slugger->slug($fabricante->getNombre() . "-" . $modelo->getNombre())->lower();
                    $modelo->setNombreUrl($slug);
                }
                $detailsImages="";
                if (!empty($row['Short description'])) $modelo->setDescripcion($row['Short description']);
                if (!empty($row['Quality'])) $modelo->setComposicion($row['Quality']);
                if (isset($row['Pieces per polybag']) && $row['Pieces per polybag'] !== '') $modelo->setPack(intval($row['Pieces per polybag']));
                if (isset($row['Pieces per box']) && $row['Pieces per box'] !== '') $modelo->setBox(intval($row['Pieces per box']));
                if (!empty($row['technical sheet'])) $modelo->setUrlFichaTecnica(trim($row['technical sheet']));
                if (!empty($row['main picture'])) $modelo->setUrlImage(trim($row['main picture']));
//                if (!empty($row['main picture A'])) $detailsImages = $detailsImages.trim($row['main picture A']).",";
//                if (!empty($row['main picture B'])) $detailsImages = $detailsImages.trim($row['main picture B']).",";
//                if (!empty($row['main picture C'])) $detailsImages = $detailsImages.trim($row['main picture C']).",";
//                if (!empty($row['packshot picture A'])) $detailsImages = $detailsImages.trim($row['packshot picture A']).",";
//                if (!empty($row['packshot picture B'])) $detailsImages = $detailsImages.trim($row['packshot picture B']).",";
//                if (!empty($row['packshot picture C'])) $detailsImages = $detailsImages.trim($row['packshot picture C']).",";



                $modelo->setDetailsImages($detailsImages);
                // Añadir más info a observaciones
                $observaciones = [];
                if(!empty($row['Style'])) $observaciones[] = "Estilo: ".str_replace('<br />', ', ', $row['Style']);
                if(!empty($row['Plus Product'])) $modelo->setDescripcion($modelo->getDescripcion()."<br/>".$row['Plus Product']);
                if(!empty($row['box weight'])) $observaciones[] = "Peso Caja: ".$row['box weight'];
                if(!empty($row['box size'])) $observaciones[] = "Dim Caja: ".$row['box size'];
                if(!empty($observaciones)) $modelo->setObservaciones(implode(' <br/> ', $observaciones));

                // --- Familia ---
                if (!empty($row["category"])) {
                    $nombreFamilia = trim($row["category"]);
                    if ($nombreFamilia !== '') {
                        $familiaID = self::NOMBRE_PROVEEDOR . "-" . $this->slugger->slug($nombreFamilia)->lower();
                        $familia = $this->em->find(Familia::class, $familiaID) ?? $this->em->getRepository(Familia::class)->findOneBy(['id' => $familiaID]);
                        if (!$familia) {
                            $familia = new Familia(); $familia->setId($familiaID); $familia->setNombre($nombreFamilia);
                            $slugFamilia = $this->slugger->slug("SOLS-" . $nombreFamilia)->lower();
                            $familia->setNombreUrl($slugFamilia);
                            $familia->setProveedor($proveedor); $this->em->persist($familia);
                        }
                        $familia->setMarca($fabricante);
                        if (!$familia->getModelosOneToMany()->contains($modelo)) $familia->addModelosOneToMany($modelo);
                        $this->em->persist($familia);
                    }
                }
                // --- Fin Familia ---

                // --- Genero ---
                if (!empty($row["Gender"])) {
                    $genderName = trim($row["Gender"]);
                    // Mapear "Mujer,Twin" a solo "Mujer" o lo que necesites
                    $genderName = explode(',', $genderName)[0]; // Tomar solo el primero
                    $genero = $this->em->getRepository(Genero::class)->findOneBy(['nombre' => $genderName]);
                    if (!$genero) {
                        $genero = new Genero(); $genero->setNombre($genderName);
                        $this->em->persist($genero);
                    }
                    $modelo->setGender($genero);
                }
                // --- Fin Genero ---

                $this->em->persist($modelo);

                if ($rowCount % $batchSize === 0) {
                    $output->writeln("Procesados $rowCount modelos... guardando lote...");
                    $this->em->flush(); $this->em->clear();
                }

            } catch (\Exception $e) { /* ... error handling + clear ... */
                $output->writeln('<error>Excepción general fila ' . $rowCount . ' (Modelo: ' . ($modeloReferencia ?? 'N/A') . '): ' . $e->getMessage() . '</error>');
                if (!$this->em->isOpen()) { $output->writeln('<error>EntityManager cerrado.</error>'); return Command::FAILURE; }
                $this->em->clear();
                continue;
            }
        }
        $output->writeln("Fin procesamiento modelos. Guardando cambios finales...");
        $this->em->flush(); $this->em->clear();
        $output->writeln("<info>PROCESAMIENTO MODELOS TERMINADO.</info>");
        // --- Fin Procesar Modelos ---


        // --- 4. Procesar sologroup_products.csv ---
        $output->writeln("Procesando archivo de Productos: $productsFile");
        $productsData = $this->readCsv($productsFile, $output);
        if ($productsData === null) return Command::FAILURE;

        $output->writeln(count($productsData) . " productos (variaciones) encontrados. Creando/actualizando Productos...");
        $rowCount = 0;
        foreach ($productsData as $row) {
            $rowCount++;
            $productoReferencia = null; $modeloReferencia = null;

            try {
                // Recargar proveedor
                $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                if (!$proveedor) throw new \RuntimeException("Proveedor perdido.");

                $productoReferencia = $row['SKU'] ?? null;
                $modeloReferenciaCsv = $row['Parent product'] ?? null; // Link al Modelo

                if (empty($modeloReferenciaCsv) || empty($modeloReferenciaCsv)) {
                    $output->writeln("<comment>Fila $rowCount (Productos) sin 'SKU' o 'Parent product', saltando.</comment>"); continue;
                }
                $modeloReferencia = $proveedor->getNombre() . "_" . $modeloReferenciaCsv; // Prefijo SOLS_

                // Buscar el Modelo (debe existir por el paso anterior)
                $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloReferencia, 'proveedor' => $proveedor]);
                if (!$modelo) {
                    $output->writeln("<warning>Fila $rowCount (Productos): Modelo '{$modeloReferencia}' no encontrado para SKU '{$productoReferencia}'. Saltando.</warning>");
                    continue;
                }

                // Buscar o crear Producto
                $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $productoReferencia]);
                if (!$producto) {
                    $producto = new Producto();
                    $producto->setReferencia($productoReferencia);
                }
                $producto->setModelo($modelo);

                // --- Precio ---
                $precio = 0.0;
                if (isset($row["Public price"]) && $row["Public price"] !== '') {
                    $precio = $this->tofloati($row["Public price"]); // Usar tofloati por si acaso
                }
                if ($precio > 0) {
                    $producto->setActivo(true);
                    if (!$modelo->isActivo()) $modelo->setActivo(true); // Activar modelo si un hijo tiene precio
                } else {
                    $producto->setActivo(false); // Desactivar si precio es 0
                }
                $producto->setPrecioUnidad($precio);
                $producto->setPrecioPack($precio);
                $producto->setPrecioCaja($precio);
                // --- Fin Precio ---

                // --- Talla ---
                $producto->setTalla(trim($row['Size'] ?? 'Talla Unica'));
                // --- Fin Talla ---

                // --- Color ---
                if (!empty($row["Color"]) && !empty($row["Color code"])) {
                    $colorNombre = trim($row["Color"]);
                    $colorCodigo = trim($row["Color code"]);
                    $colorId = self::NOMBRE_PROVEEDOR . "-" . $this->slugger->slug($colorNombre)->lower(); // Usar nombre para ID? O código? Usemos nombre

                    $color = $this->em->find(Color::class, $colorId) ?? $this->em->getRepository(Color::class)->findOneBy(['id' => $colorId]);
                    if (!$color) {
                        $output->writeln("Creando color: $colorNombre ($colorCodigo) ID: $colorId");
                        $color = new Color(); $color->setId($colorId); $color->setNombre($colorNombre);
                        $color->setProveedor($proveedor); $color->setCodigoColor($colorCodigo);
                        // Podríamos intentar sacar el RGB de 'Color Url' si es una imagen
                        $this->em->persist($color);
                    } elseif ($color->getCodigoColor() != $colorCodigo) {
                        $color->setCodigoColor($colorCodigo); $this->em->persist($color);
                    }
                    $producto->setColor($color);
                } else {
                    $output->writeln("<warning>Fila $rowCount (Productos) sin 'Color' o 'Color code'. Se dejará sin asignar.</warning>");
                }
                // --- Fin Color ---

                // --- Imágenes Producto ---
                $mainImg = trim($row["Main packshot"] ?? '');
                $viewImages = [];
                if ($mainImg && filter_var($mainImg, FILTER_VALIDATE_URL)) {
                    $producto->setUrlImage($mainImg);
                    $viewImages[] = $mainImg;
                }
                // Añadir todas las demás imágenes a viewsImages
                $imgKeys = ['Main picture A', 'Main picture B', 'Main picture C',
                    'Model picture A', 'Model picture B', 'Model picture C',
                    'Packshot picture A', 'Packshot picture B', 'Packshot picture C'];
                foreach($imgKeys as $key) {
                    if (!empty($row[$key]) && filter_var(trim($row[$key]), FILTER_VALIDATE_URL)) {
                        $viewImages[] = trim($row[$key]);
                    }
                }
                $producto->setViewsImages(!empty($viewImages) ? implode(',', array_unique($viewImages)) : null);
                // --- Fin Imágenes ---

                $this->em->persist($modelo); // Persistir modelo (estado activo)
                $this->em->persist($producto); // Persistir producto

                if ($rowCount % $batchSize === 0) {
                    $output->writeln("Procesados $rowCount productos... guardando lote...");
                    $this->em->flush(); $this->em->clear();
                }

            } catch (\Exception $e) { /* ... error handling + clear ... */
                $output->writeln('<error>Excepción general fila ' . $rowCount . ' (Producto: ' . ($productoReferencia ?? 'N/A') . '): ' . $e->getMessage() . '</error>');
                if (!$this->em->isOpen()) { $output->writeln('<error>EntityManager cerrado.</error>'); return Command::FAILURE; }
                $this->em->clear();
                continue;
            }
        }
        $output->writeln("Fin procesamiento productos. Guardando cambios finales...");
        $this->em->flush(); $this->em->clear();
        $output->writeln("<info>PROCESAMIENTO PRODUCTOS TERMINADO.</info>");
        // --- Fin Procesar Productos ---



        // --- 5. Procesar pricesFile (Tarifas especiales) ---
        $output->writeln("Procesando archivo de Tarifas: $pricesFile");
        $pricesData = $this->readCsv($pricesFile, $output);
        if ($pricesData === null) {
            $output->writeln("<error>No se pudo leer el archivo de tarifas. La importación está incompleta (precios no asignados).</error>");
            return Command::FAILURE;
        }

        $output->writeln(count($pricesData) . " tarifas encontradas. Actualizando precios...");
        $rowCount = 0; $updatedCount = 0;
        foreach ($pricesData as $row) {
            $rowCount++;
            $productoReferencia = $row['SKU'] ?? null;
            if (empty($productoReferencia)) {
                $output->writeln("<comment>Fila $rowCount (Precios) sin 'SKU', saltando.</comment>"); continue;
            }

            try {
                $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $productoReferencia]);

                if ($producto) {
                    // Asegurar que el producto y modelo están gestionados
                    if (!$this->em->contains($producto)) {
                        $producto = $this->em->find(Producto::class, $producto->getId());
                        if(!$producto) continue; // No encontrado
                    }
                    $modelo = $producto->getModelo();
                    if ($modelo && !$this->em->contains($modelo)) {
                        $modelo = $this->em->find(Modelo::class, $modelo->getId());
                        if ($modelo) $producto->setModelo($modelo); // Re-asociar
                        else { $output->writeln("<warning>Modelo para {$productoReferencia} no encontrado. Saltando precio.</warning>"); continue; }
                    }

                    // Asignar precios según las columnas del archivo de tarifa
                    $precioTarifa = $this->tofloati($row['TARIFA'] ?? 0);
                    $precioPack = $this->tofloati($row['TARIFA'] ?? $precioTarifa); // Fallback a TARIFA
                    $precioCaja = $this->tofloati($row['TARIFA'] ?? $precioTarifa); // Fallback a TARIFA

                    $producto->setPrecioUnidad($precioTarifa);
                    $producto->setPrecioPack($precioPack);
                    $producto->setPrecioCaja($precioCaja);

                    // Activar si el precio base es > 0
                    if ($precioTarifa > 0) {
                        $producto->setActivo(true);
                        if ($modelo && !$modelo->isActivo()) {
                            $modelo->setActivo(true);
                            $this->em->persist($modelo);
                        }
                    } else {
                        $producto->setActivo(false); // Mantener inactivo si precio es 0
                    }

                    $this->em->persist($producto);
                    $updatedCount++;

                    if ($updatedCount % $batchSize === 0) {
                        $output->writeln("...precios actualizados: $updatedCount...");
                        $this->em->flush(); $this->em->clear();
                    }
                } else {
                    $output->writeln("<warning>Fila $rowCount (Precios): SKU '{$productoReferencia}' no encontrado en la BBDD. Se ignora este precio.</warning>");
                }

            } catch (\Exception $e) { /* ... error handling + clear ... */
                $output->writeln('<error>Excepción general fila ' . $rowCount . ' (Precio SKU: ' . ($productoReferencia ?? 'N/A') . '): ' . $e->getMessage() . '</error>');
                if (!$this->em->isOpen()) { $output->writeln('<error>EntityManager cerrado.</error>'); return Command::FAILURE; }
                $this->em->clear(); continue;
            }
        }
        $output->writeln("Fin procesamiento precios. Guardando cambios finales...");
        $this->em->flush(); $this->em->clear();
        $output->writeln("<info>PROCESAMIENTO TARIFAS TERMINADO. $updatedCount productos actualizados.</info>");
        // --- Fin Procesar Precios ---



        // --- 5. Ajuste Final Precios Mínimos ---
        $output->writeln("AJUSTANDO PRECIOS MÍNIMOS DE MODELOS SOLS");
        $_proveedorInitialAjuste = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => self::NOMBRE_PROVEEDOR]);
        if ($_proveedorInitialAjuste) {
            $proveedorIdAjuste = $_proveedorInitialAjuste->getId();
            $query = $this->em->getRepository(Modelo::class)->createQueryBuilder('m')
                ->where('m.proveedor = :prov')->andWhere('m.activo = :activo')
                ->setParameter('prov', $_proveedorInitialAjuste)->setParameter('activo', true)->getQuery();
            $countAjuste = 0; $batchSizeAjuste = 100;
            foreach ($query->toIterable() as $modeloAjuste) {
                try {
                    $proveedorAjuste = $this->em->find(Proveedor::class, $proveedorIdAjuste);
                    if(!$proveedorAjuste) throw new \RuntimeException("Proveedor perdido en ajuste precios");
                    if (!$this->em->contains($modeloAjuste)) {
                        $modeloAjuste = $this->em->find(Modelo::class, $modeloAjuste->getId());
                        if (!$modeloAjuste) continue;
                    }
                    $modeloAjuste->setProveedor($proveedorAjuste);

                    $precioMinimo = $modeloAjuste->getPrecioUnidad(); // Asume que este método calcula el min de sus hijos activos
                    $modeloAjuste->setPrecioMin($precioMinimo ?? 0);
                    // Lógica original de precios min adulto (simplificada)
                    if ($precioMinimo > 0) {
                        $modeloAjuste->setPrecioMinAdulto($precioMinimo);
                    } else {
                        $modeloAjuste->setPrecioMinAdulto($modeloAjuste->getPrecioUnidad()); // Re-evaluar si es necesario
                    }
                    /* // Lógica original compleja
                    if ($modeloAjuste->getPrecioCantidadBlancas(10000) > 0) {
                        $modeloAjuste->setPrecioMinAdulto($modeloAjuste->getPrecioCantidadBlancas(10000));
                    } else { // ... etc ... }
                    */
                    $this->em->persist($modeloAjuste); $countAjuste++;
                    if ($countAjuste % $batchSizeAjuste === 0) {
                        $output->writeln("...precios mínimos ajustados: $countAjuste...");
                        $this->em->flush(); $this->em->clear();
                    }
                } catch (\Exception $e) { /* ... error handling + clear ... */
                    $output->writeln('<error>Excepcion al ajustar precio mínimo modelo ' . ($modeloAjuste->getReferencia() ?? 'ID:'.$modeloAjuste->getId()) . ': ' . $e->getMessage() . '</error>');
                    if (!$this->em->isOpen()) { $output->writeln('<error>EntityManager cerrado.</error>'); return Command::FAILURE; }
                    $this->em->clear();
                }
            }
            $output->writeln("...guardando ajustes de precios mínimos finales...");
            $this->em->flush(); $this->em->clear();
            $output->writeln("<info>AJUSTE DE PRECIOS MÍNIMOS TERMINADO.</info>");
        } else { $output->writeln("<error>Proveedor Sols no encontrado para ajuste final.</error>"); }
        // --- Fin Ajuste Final ---

        return Command::SUCCESS;
    }

    private function tofloati($num): float
    {
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
}