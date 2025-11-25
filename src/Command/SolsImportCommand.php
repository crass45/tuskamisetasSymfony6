<?php

namespace App\Command; // Namespace actualizado

// use Ddeboer\DataImport\Reader\CsvReader; // Eliminado
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;
// Entidades actualizadas
use App\Entity\Color;
use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Genero;      // Añadido
use App\Entity\Modelo;      // Añadido
use App\Entity\Producto;    // Añadido (Asumiendo singular)
// use App\Entity\ModeloAtributo; // Comentado
use App\Entity\Proveedor;
// Servicios
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

// Eliminados use no necesarios (PrecioProductoCantidad)

#[AsCommand(
    name: 'ss:import_command_sols',
    description: 'Importa en base de datos el CSV de productos de Sols'
)]
class SolsImportCommand extends Command
{
    private EntityManagerInterface $em;
    private SluggerInterface $slugger;

    public function __construct(EntityManagerInterface $em, SluggerInterface $slugger)
    {
        parent::__construct();
        $this->em = $em;
        $this->slugger = $slugger;
    }

    // Mantenemos tofloati como método privado
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

    protected function configure()
    {
        $this->addArgument(
            'filename',
            InputArgument::OPTIONAL, // Cambiado a Opcional, ya que el original lo tenía así
            'Nombre del archivo CSV que vamos a importar a la base de datos'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '-1');
        $filename = $input->getArgument('filename');
        $nombreProveedor = "Sols";

        if (!$filename || !file_exists($filename)) {
            $output->writeln("<error>El fichero $filename no existe o no se proporcionó.</error>");
            return Command::FAILURE;
        }

        // --- Obtener/Crear Proveedor y Fabricante ---
        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);
        if (!$proveedor) {
            $proveedor = new Proveedor(); $proveedor->setNombre($nombreProveedor);
            $this->em->persist($proveedor); $this->em->flush();
            $output->writeln("Proveedor $nombreProveedor creado.");
        }
        $proveedorId = $proveedor->getId();

        $fabricante = $this->em->getRepository(Fabricante::class)->findOneBy(['nombre' => $nombreProveedor]);
        if (!$fabricante) {
            $fabricante = new Fabricante(); $fabricante->setNombre($nombreProveedor);
            $this->em->persist($fabricante); $this->em->flush();
            $output->writeln("Fabricante $nombreProveedor creado.");
        }
        $fabricanteId = $fabricante->getId();
        // --- Fin Proveedor/Fabricante ---

        // --- Desactivar ---
        $output->writeln("Desactivando productos y modelos existentes para $nombreProveedor...");
        try {
            $sqlProd = "UPDATE producto p JOIN modelo m ON p.modelo = m.id SET p.activo = 0 WHERE m.proveedor = ?";
            $this->em->getConnection()->prepare($sqlProd)->executeStatement([$proveedorId]);
            $sqlMod = "UPDATE modelo SET activo = 0 WHERE proveedor = ?";
            $this->em->getConnection()->prepare($sqlMod)->executeStatement([$proveedorId]);
            $output->writeln("Productos y modelos desactivados.");
        } catch (\Exception $e) { $output->writeln('<error>Error al desactivar: '.$e->getMessage().'</error>'); return Command::FAILURE; }
        // --- Fin Desactivar ---

        // --- Lectura CSV Nativa ---
        $output->writeln("Procesando archivo: $filename");
        $header = null; $data = []; $lineNum = 0;
        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($rowCsv = fgetcsv($handle, 0, ';')) !== FALSE) {
                $lineNum++;
                if (!$header) {
                    // Limpiar cabeceras (quitar espacios, caracteres extraños si los hubiera)
                    $header = array_map('trim', $rowCsv);
                    // Opcional: Validar que las cabeceras esperadas existan
                } else {
                    if (count($header) == count($rowCsv)) {
                        $cleanedRow = [];
                        foreach(array_combine($header, $rowCsv) as $key => $value){
                            $cleanedRow[trim($key)] = trim($value); // Usar cabeceras limpias
                        }
                        $data[] = $cleanedRow;
                    } else {
                        $output->writeln("<warning>Línea $lineNum: número de columnas incorrecto (" . count($rowCsv) . " vs " . count($header) ."). Saltando.</warning>");
                    }
                }
            }
            fclose($handle);
        } else { $output->writeln("<error>No se pudo abrir el archivo $filename</error>"); return Command::FAILURE; }
        // --- Fin Lectura CSV Nativa ---

        $output->writeln("Leídas " . count($data) . " filas de datos. Procesando...");
        $rowCount = 0; $batchSize = 100;
        foreach ($data as $row) {
            $rowCount++;
            $productoReferencia = null; $modeloReferencia = null;

            try {
                // Recargar proveedor y fabricante
                $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                $fabricante = $this->em->find(Fabricante::class, $fabricanteId);
                if (!$proveedor || !$fabricante) throw new \RuntimeException("Error crítico: Proveedor o Fabricante no encontrados.");

                // --- Modelo ---
                $modeloReferenciaCsv = $row["MODELO"] ?? null;
                if (empty($modeloReferenciaCsv)) {
                    $output->writeln("<comment>Fila $rowCount sin 'MODELO', saltando.</comment>"); continue;
                }
                $modeloReferencia = $nombreProveedor . "_" . $modeloReferenciaCsv; // Prefijo SOLS_

                $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloReferencia, 'proveedor' => $proveedor]);
                if (!$modelo) {
                    $modelo = new Modelo();
                    $modelo->setProveedor($proveedor);
                    $modelo->setFabricante($fabricante);
                    $modelo->setReferencia($modeloReferencia);
                }
                $modelo->setActivo(true);
                // --- Fin Modelo ---

                // --- Producto ---
                $productoReferencia = $row["SKU"] ?? null;
                if (empty($productoReferencia)) {
                    $output->writeln("<comment>Fila $rowCount sin 'SKU', saltando.</comment>"); continue;
                }
                $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $productoReferencia]);
                if (!$producto) {
                    $producto = new Producto();
                    $producto->setReferencia($productoReferencia);
                }
                $producto->setModelo($modelo);
                $producto->setActivo(true);
                // Asegurar relación bidireccional (si el método existe y lo hace)
                if (method_exists($modelo, 'addProducto') && !$modelo->getProductos()->contains($producto)) {
                    $modelo->addProducto($producto);
                } elseif (method_exists($modelo, 'addModeloHasProducto') && !$modelo->getModeloHasProductos()->contains($producto)) {
                    $modelo->addModeloHasProducto($producto);
                } elseif (method_exists($modelo, 'getProductos') && !$modelo->getProductos()->contains($producto)) { // Fallback a Collection
                    $modelo->getProductos()->add($producto);
                }
                // --- Fin Producto ---

                // --- Datos Modelo ---
                if (!empty($row["NOMBRE"]) && !empty($row["DESCRIPCIÓN"])) { // Nombre viene del CSV
                    $nombreModelo = trim($row["DESCRIPCIÓN"]) . " " . trim($row["NOMBRE"]);
                    $modelo->setNombre($nombreModelo);
                    if (empty($modelo->getNombreUrl())) {
                        $slug = $this->slugger->slug($fabricante->getNombre() . "-" . $nombreModelo)->lower();
                        $modelo->setNombreUrl($slug);
                    }
                }
                if (!empty($row["DESCRICIÓN CATÁLOGO"]) && empty($modelo->getDescripcion())) {
                    $modelo->setDescripcion(trim($row["DESCRICIÓN CATÁLOGO"]));
                }
                if (!empty($row["COMPOSICIÓN CATÁLOGO"]) && empty($modelo->getComposicion())) {
                    $modelo->setComposicion(trim($row["COMPOSICIÓN CATÁLOGO"]));
                }
                if (isset($row["UNI. PACK"]) && $row["UNI. PACK"] !== '') $modelo->setPack(intval($row["UNI. PACK"]));
                if (isset($row["UNI. CAJA"]) && $row["UNI. CAJA"] !== '') $modelo->setBox(intval($row["UNI. CAJA"]));
                if (!empty($row["FICHA TÉCNICA"])) $modelo->setUrlFichaTecnica(trim($row["FICHA TÉCNICA"]));
                if (!empty($row["FOTO MODELO"]) && empty($modelo->getUrlImage())) $modelo->setUrlImage(trim($row["FOTO MODELO"]));
                // --- Fin Datos Modelo ---


                // --- Familia ---
                if (!empty($row["CATEGORIA"])) {
                    $nombreFamilia = trim($row["CATEGORIA"]);
                    if ($nombreFamilia !== '') {
                        $familiaID = $nombreProveedor . "-" . $this->slugger->slug($nombreFamilia)->lower(); // Cambiado separador a -
                        $familia = $this->em->find(Familia::class, $familiaID) ?? $this->em->getRepository(Familia::class)->findOneBy(['id' => $familiaID]);
                        if (!$familia) {
                            $familia = new Familia(); $familia->setId($familiaID); $familia->setNombre($nombreFamilia);
                            // Corregido: setNombreUrl, no setNombreUrlFromNombre
                            $slugFamilia = $this->slugger->slug("SOLS-" . $nombreFamilia)->lower();
                            $familia->setNombreUrl($slugFamilia);
                            $familia->setProveedor($proveedor);
                            $this->em->persist($familia);
                        }
                        $familia->setMarca($fabricante);
                        // Corregido: addModelosOneToMany
                        if (!$familia->getModelosOneToMany()->contains($modelo)) $familia->addModelosOneToMany($modelo);
                        $this->em->persist($familia);
                    }
                }
                // --- Fin Familia ---


                // --- Genero ---
                if (!empty($row["GENERO"])) {
                    $genderName = trim($row["GENERO"]);
                    $genero = $this->em->getRepository(Genero::class)->findOneBy(['nombre' => $genderName]);
                    if (!$genero) {
                        $genero = new Genero();
                        $genero->setNombre($genderName);
                        $this->em->persist($genero);
                    }
                    $modelo->setGender($genero); // Asumiendo que existe setGenero
                }
                // --- Fin Genero ---

                // --- Atributos (Comentado) ---
                // if (!empty($row["gender"])) { // El CSV original usaba 'gender' para atributos
                //     $genderName = $row["gender"];
                //     $atributoValor = null;
                //     if ($genderName == "Infantil") $atributoValor = "niños";
                //     if ($genderName == "Mujer") $atributoValor = "mujeres";
                //     if ($genderName == "Hombre") $atributoValor = "hombres";
                //
                //     if ($atributoValor) {
                //         $atributo = $this->em->getRepository(ModeloAtributo::class)->findOneBy(['valor' => $atributoValor]);
                //         if ($atributo != null && method_exists($modelo, 'addAtributo') && !$modelo->getAtributos()->contains($atributo)) {
                //             $modelo->addAtributo($atributo);
                //         }
                //     }
                // }
                // --- Fin Atributos ---


                // --- Datos Producto ---
                if (isset($row["TALLA"]) && $row["TALLA"] !== '') $producto->setTalla(trim($row["TALLA"]));

                // --- Color ---
                if (!empty($row["COLOR"]) && isset($row["CÓD. COLOR"])) {
                    $colorNombre = trim($row["COLOR"]);
                    $colorCodigo = trim($row["CÓD. COLOR"]);
                    $colorId = $nombreProveedor . "-" . $this->slugger->slug($colorNombre)->lower();

                    $color = $this->em->find(Color::class, $colorId) ?? $this->em->getRepository(Color::class)->findOneBy(['id' => $colorId]);

                    if (!$color) {
                        $output->writeln("Creando color: $colorNombre ($colorCodigo) ID: $colorId");
                        $color = new Color();
                        $color->setId($colorId);
                        $color->setNombre($colorNombre);
                        $color->setProveedor($proveedor);
                        $color->setCodigoColor($colorCodigo); // Asumiendo que existe setCodigoColor()
                        $this->em->persist($color);
                    } elseif ($color->getCodigoColor() != $colorCodigo) { // Actualizar código si cambió
                        $color->setCodigoColor($colorCodigo);
                        $this->em->persist($color);
                    }
                    $producto->setColor($color);

                    // --- Color Extractor ---
                    if ($producto->getUrlImage() && $color && empty($color->getCodigoRGB())) {
                        try {
                            $imagePath = $producto->getUrlImage();
                            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                                // Asegurar que el color está gestionado
                                if (!$this->em->contains($color)) {
                                    $managedColor = $this->em->find(Color::class, $color->getId());
                                    if ($managedColor) $color = $managedColor; else $this->em->persist($color);
                                }
                                $palette = Palette::fromFilename($imagePath, Palette::MAX_PIXEL_COUNT_IGNORE);
                                $extractor = new ColorExtractor($palette);
                                $colors = $extractor->extract(1);
                                if (!empty($colors)) {
                                    $hexColor = \League\ColorExtractor\Color::fromIntToHex($colors[0]);
                                    if (preg_match('/^#[a-fA-F0-9]{6}$/i', $hexColor)) {
                                        $color->setCodigoRGB($hexColor);
                                        $this->em->persist($color); // Marcar para guardar RGB
                                    } else { $output->writeln("<comment>Color extraído no válido ($hexColor) para {$productoReferencia}</comment>"); }
                                }
                            } else { $output->writeln("<comment>URL no válida para ColorExtractor: {$imagePath}</comment>"); }
                        } catch (\Exception $e) { $output->writeln('<error>Excepcion ColorExtractor para ' . $productoReferencia . ': ' . $e->getMessage() . '</error>'); }
                    }
                    // --- Fin Color Extractor ---

                } else {
                    $output->writeln("<warning>Fila $rowCount sin datos de COLOR o CÓD. COLOR. Se dejará sin asignar.</warning>");
                    // $producto->setColor(null); // O asignar color por defecto
                }
                // --- Fin Color ---

                // --- Imágenes Producto ---
                $mainImg = trim($row["FOTO FRONTAL"] ?? '');
                $backImg = trim($row["FOTO POSTERIOR"] ?? '');
                $sideImg = trim($row["FOTO LATERAL"] ?? '');
                $viewImages = [];
                if ($mainImg && filter_var($mainImg, FILTER_VALIDATE_URL)) {
                    $producto->setUrlImage($mainImg);
                    $viewImages[] = $mainImg; // Incluir principal en vistas
                }
                if ($backImg && filter_var($backImg, FILTER_VALIDATE_URL)) $viewImages[] = $backImg;
                if ($sideImg && filter_var($sideImg, FILTER_VALIDATE_URL)) $viewImages[] = $sideImg;
                $producto->setViewsImages(!empty($viewImages) ? implode(',', array_unique($viewImages)) : null);
                // --- Fin Imágenes Producto ---

                // --- Precio ---
                if (isset($row["TARIFA"]) && $row["TARIFA"] !== '') {
                    $precio = $this->tofloati($row["TARIFA"]);
                    if ($precio >= 0) {
                        $producto->setPrecioCaja($precio);
                        $producto->setPrecioPack($precio);
                        $producto->setPrecioUnidad($precio);
                        // Activar si hay precio > 0
                        $producto->setActivo(true);
                        if (!$modelo->isActivo()) {
                            $modelo->setActivo(true);
                        }
                    } else {
                        $output->writeln("<comment>Precio inválido ('{$row["TARIFA"]}') para {$productoReferencia}. Estableciendo a 0 y desactivando.</comment>");
                        $producto->setPrecioCaja(0); $producto->setPrecioPack(0); $producto->setPrecioUnidad(0);
                        $producto->setActivo(false);
                    }
                } else {
                    // Si no hay tarifa, desactivar producto?
                    $output->writeln("<warning>Fila $rowCount sin 'TARIFA'. Producto {$productoReferencia} quedará inactivo.</warning>");
                    $producto->setActivo(false);
                    // También poner precios a 0
                    $producto->setPrecioCaja(0); $producto->setPrecioPack(0); $producto->setPrecioUnidad(0);
                }
                // --- Fin Precio ---

                $this->em->persist($modelo); // Persistir modelo (puede haber cambiado estado activo, genero, etc)
                $this->em->persist($producto); // Persistir producto

                // --- Batch Processing ---
                if ($rowCount % $batchSize === 0) {
                    $output->writeln("Procesados $rowCount productos... guardando lote...");
                    $this->em->flush(); $this->em->clear();
                    $output->writeln("Lote guardado y memoria liberada.");
                    // $proveedor y $fabricante se recargarán
                }
                // --- Fin Batch Processing ---

            } catch (\Exception $e) { /* ... error handling + clear ... */
                $output->writeln('<error>Excepción general fila ' . $rowCount . ' (Mod:' . ($modeloReferencia ?? 'N/A') . ' Prod:' . ($productoReferencia ?? 'N/A') . '): ' . $e->getMessage() . '</error>');
                if (!$this->em->isOpen()) { $output->writeln('<error>EntityManager cerrado.</error>'); return Command::FAILURE; }
                $this->em->clear();
                continue;
            }
        } // Fin foreach $data

        $output->writeln("Fin procesamiento CSV. Guardando cambios finales...");
        $this->em->flush(); $this->em->clear();
        $output->writeln("<info>IMPORTACIÓN SOLS TERMINADA CORRECTAMENTE.</info>");


        // --- Ajuste Final Precios Mínimos ---
        $output->writeln("AJUSTANDO PRECIOS MÍNIMOS DE MODELOS SOLS");
        $_proveedorInitialAjuste = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);
        if ($_proveedorInitialAjuste) {
            $proveedorIdAjuste = $_proveedorInitialAjuste->getId();
            $query = $this->em->getRepository(Modelo::class)->createQueryBuilder('m')
                ->where('m.proveedor = :prov')->andWhere('m.activo = :activo')
                ->setParameter('prov', $_proveedorInitialAjuste)->setParameter('activo', true)->getQuery();
            $countAjuste = 0; $batchSizeAjuste = 100;
            foreach ($query->toIterable() as $modeloAjuste) {
                try {
                    $proveedorAjuste = $this->em->find(Proveedor::class, $proveedorIdAjuste); // Recargar
                    if(!$proveedorAjuste) throw new \RuntimeException("Proveedor perdido en ajuste precios");

                    if (!$this->em->contains($modeloAjuste)) {
                        $modeloAjuste = $this->em->find(Modelo::class, $modeloAjuste->getId());
                        if (!$modeloAjuste) continue;
                    }
                    $modeloAjuste->setProveedor($proveedorAjuste); // Re-asociar

                    $precioMinimo = $modeloAjuste->getPrecioUnidad();
                    $modeloAjuste->setPrecioMin($precioMinimo ?? 0); $modeloAjuste->setPrecioMinAdulto($precioMinimo ?? 0);
                    $this->em->persist($modeloAjuste); $countAjuste++;
                    if ($countAjuste % $batchSizeAjuste === 0) {
                        $output->writeln("...precios mínimos ajustados: $countAjuste...");
                        $this->em->flush(); $this->em->clear();
                        // Proveedor se recarga al inicio de la siguiente
                    }
                } catch (\Exception $e) { /* ... error handling + clear ... */
                    $output->writeln('<error>Excepcion al ajustar precio mínimo modelo ' . ($modeloAjuste->getReferencia() ?? 'ID:'.$modeloAjuste->getId()) . ': ' . $e->getMessage() . '</error>');
                    if (!$this->em->isOpen()) { $output->writeln('<error>EntityManager cerrado.</error>'); return Command::FAILURE; }
                    $this->em->clear();
                    // Proveedor se recarga al inicio
                }
            }
            $output->writeln("...guardando ajustes de precios mínimos finales...");
            $this->em->flush(); $this->em->clear();
            $output->writeln("<info>AJUSTE DE PRECIOS MÍNIMOS TERMINADO.</info>");
        } else { $output->writeln("<error>Proveedor Sols no encontrado para ajuste final.</error>"); }
        // --- Fin Ajuste Final ---


        return Command::SUCCESS;
    } // Fin execute
} // Fin clase