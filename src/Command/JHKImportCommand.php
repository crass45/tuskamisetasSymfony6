<?php

namespace App\Command;

use App\Entity\Color;
use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Modelo;
use App\Entity\Producto;
use App\Entity\Proveedor;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'ss:import_command_jhk',
    description: 'Importación total JHK: Familias, Imágenes optimizadas, Precios y Auto-recuperación.'
)]
class JHKImportCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(
        private ManagerRegistry  $mr,
        private SluggerInterface $slugger,
        private ImageManager     $imageManager
    ) {
        parent::__construct();
        $this->em = $mr->getManager();
    }

    private function tofloati($num): float
    {
        if (is_null($num) || $num === '') return 0.0;
        $num = str_replace(',', '.', $num);
        return floatval(preg_replace("/[^-0-9.]/", "", $num));
    }

    protected function configure(): void
    {
        $this->addArgument(
            'filename',
            InputArgument::OPTIONAL,
            'Ruta al archivo jhk2026.csv',
            'jhk2026.csv'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '-1');

        $filename = $input->getArgument('filename');
        $filenamePrecios = "tarifajhk20262.csv";
        $nombreProveedor = "JHK";

        if (!file_exists($filename)) {
            $output->writeln("<error>El fichero de productos $filename no existe</error>");
            return Command::FAILURE;
        }

        // --- 1. GESTIÓN DE ENTIDADES BASE ---
        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);
        if (!$proveedor) {
            $proveedor = new Proveedor();
            $proveedor->setNombre($nombreProveedor);
            $this->em->persist($proveedor);
            $this->em->flush();
        }
        $proveedorId = $proveedor->getId();

        $fabricante = $this->em->getRepository(Fabricante::class)->findOneBy(['nombre' => $nombreProveedor]);
        if (!$fabricante) {
            $fabricante = new Fabricante();
            $fabricante->setNombre($nombreProveedor);
            $this->em->persist($fabricante);
            $this->em->flush();
        }
        $fabricanteId = $fabricante->getId();

        // --- 2. DESACTIVACIÓN PREVIA ---
        $output->writeln("<comment>Limpiando catálogo antiguo de JHK...</comment>");
        $this->em->getConnection()->executeStatement(
            "UPDATE producto p JOIN modelo m ON p.modelo = m.id SET p.activo = 0 WHERE m.proveedor = ?",
            [$proveedorId]
        );
        $this->em->getConnection()->executeStatement(
            "UPDATE modelo SET activo = 0 WHERE proveedor = ?",
            [$proveedorId]
        );

        // --- 3. FASE 1: IMPORTACIÓN MAESTRA ---
        $output->writeln("Fase 1: Procesando modelos, familias y productos...");
        $header = null; $rowCount = 0; $modelosEnMemoria = [];

        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($rowCsv = fgetcsv($handle, 0, ';')) !== FALSE) {
                if (!$header) { $header = array_map('trim', $rowCsv); continue; }

                try {
                    // Auto-recuperación del EntityManager si se cerró por un error previo
                    if (!$this->em->isOpen()) {
                        $this->em = $this->mr->resetManager();
                        $modelosEnMemoria = [];
                    }

                    $rowCount++;
                    $row = array_combine($header, array_map('trim', $rowCsv));

                    $modeloRef = $row["Referencia"] ?? null;
                    $prodRef = $row["Combinacion"] ?? null;
                    $colorRef = $row["RefColor"] ?? 'col';

                    if (!$modeloRef || !$prodRef) continue;

                    // A. Gestión de Modelo
                    if (isset($modelosEnMemoria[$modeloRef])) {
                        $modelo = $modelosEnMemoria[$modeloRef];
                    } else {
                        $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloRef]);
                        if (!$modelo) {
                            $modelo = new Modelo();
                            $modelo->setReferencia($modeloRef);
                            $modelo->setProveedor($this->em->find(Proveedor::class, $proveedorId));
                            $modelo->setFabricante($this->em->find(Fabricante::class, $fabricanteId));
                            $this->em->persist($modelo);
                        }
                        $modelosEnMemoria[$modeloRef] = $modelo;
                    }

                    // B. Actualización de datos del Modelo y Slugs de seguridad
                    $modelo->setActivo(true);
                    $nombreModelo = !empty($row["Nombre"]) ? $row["Nombre"] : $modeloRef;
                    $modelo->setNombre($nombreModelo);
                    $slugModelo = $this->slugger->slug($nombreProveedor . "-" . $nombreModelo)->lower();
                    $modelo->setNombreUrl($slugModelo ?: $this->slugger->slug($modeloRef)->lower());

                    if (!empty($row["URLCatalogue"]) && $row["URLCatalogue"] !== '---') {
//                        $urlM = str_replace("http://", "https://", $row["URLCatalogue"]);
                        $urlM = $row["URLCatalogue"] ?? null;
                        $modelo->setUrlImage($this->imageManager->download($urlM, 'jhk/modelos', $modeloRef));
                    }
                    if (!empty($row["Descripcion"])) { $modelo->setDescripcion(html_entity_decode($row["Descripcion"])); }
                    $modelo->setComposicion($row["Composicion"] ?? '');
                    $modelo->setBox(intval($row["box"] ?? 0));
                    $modelo->setPack(intval($row["bag"] ?? 0));

                    // C. Gestión de Familias (type_product) - RECUPERADO
                    if (!empty($row["type_product"])) {
                        $nombreFam = trim($row["type_product"]);
                        $famID = $nombreProveedor . "--" . $this->slugger->slug($nombreFam)->lower();

                        $familia = $this->em->find(Familia::class, $famID) ?: $this->em->getRepository(Familia::class)->find($famID);
                        if (!$familia) {
                            $familia = new Familia();
                            $familia->setId($famID);
                            $familia->setNombre($nombreFam);
                            $slugFam = $this->slugger->slug($nombreFam . "-" . $nombreProveedor)->lower();
                            $familia->setNombreUrl($slugFam ?: $this->slugger->slug($famID)->lower());
                            $familia->setProveedor($this->em->find(Proveedor::class, $proveedorId));
                            $this->em->persist($familia);
                        }
                        $familia->setMarca($this->em->find(Fabricante::class, $fabricanteId));
                        if (method_exists($familia, 'addModelosOneToMany')) {
                            $familia->addModelosOneToMany($modelo);
                        }
                        $this->em->persist($familia);
                    }

                    // D. Gestión de Producto
                    $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $prodRef]) ?? new Producto();
                    $producto->setReferencia($prodRef);
                    $producto->setModelo($modelo);
                    $producto->setTalla($row["Talla"] ?? 'U');

                    // E. Gestión de Color (ID Único por proveedor)
                    if (!empty($row["Color"])) {
                        $colorId = $nombreProveedor . "-" . $this->slugger->slug($row["Color"])->lower();
                        $color = $this->em->find(Color::class, $colorId) ?: $this->em->getRepository(Color::class)->find($colorId);
                        if (!$color) {
                            $color = new Color();
                            $color->setId($colorId);
                            $color->setNombre($row["Color"]);
                            $color->setProveedor($this->em->find(Proveedor::class, $proveedorId));
                            $this->em->persist($color);
                            $this->em->flush(); // Flush inmediato para que esté disponible en la siguiente fila
                        }
                        $producto->setColor($color);
                    }

                    // F. Imagen de Producto OPTIMIZADA (Nombre: Modelo-Color)
                    if (!empty($row["URLSku"]) && $row["URLSku"] !== '---') {
//                        $urlP = str_replace("http://", "https://", $row["URLSku"]);
                        $urlP = $row["URLSku"] ?? null;
                        $producto->setUrlImage($this->imageManager->download($urlP, 'jhk/productos', $modeloRef . "-" . $colorRef));
                    }

                    $pPick = $this->tofloati($row["Picking"] ?? 0);
                    $producto->setPrecioUnidad($pPick);
                    $producto->setPrecioCaja($pPick);
                    $producto->setPrecioPack($pPick);
                    $producto->setActivo($pPick > 0);

                    $this->em->persist($producto);

                    // G. Batch Processing
                    if ($rowCount % 50 === 0) {
                        $this->em->flush();
                        $this->em->clear();
                        $modelosEnMemoria = [];
                        $output->writeln("Procesados $rowCount registros...");
                    }
                } catch (\Exception $e) {
                    $output->writeln("<error>Fila $rowCount ({$prodRef}): {$e->getMessage()}</error>");
                    if ($this->em->isOpen()) { $this->em->clear(); }
                }
            }
            fclose($handle);
        }
        $this->em->flush();
        $this->em->clear();

        // --- 4. FASE 2: ACTUALIZACIÓN DE PRECIOS ESPECÍFICOS ---
        if (file_exists($filenamePrecios)) {
            $output->writeln("Fase 2: Aplicando tarifas específicas...");
            if (($handleP = fopen($filenamePrecios, 'r')) !== FALSE) {
                $headerP = null; $pCount = 0;
                while (($rowP = fgetcsv($handleP, 0, ';')) !== FALSE) {
                    if (!$headerP) { $headerP = array_map('trim', $rowP); continue; }
                    try {
                        if (!$this->em->isOpen()) $this->em = $this->mr->resetManager();
                        $pCount++;
                        $dataP = array_combine($headerP, array_map('trim', $rowP));
                        $skuP = $dataP["SKU"] ?? null;
                        if (!$skuP) continue;

                        $productoP = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $skuP]);
                        if ($productoP) {
                            $precioP = $this->tofloati($dataP["PRECIO"]);
                            $productoP->setPrecioUnidad($precioP);
                            $productoP->setPrecioCaja($precioP);
                            $productoP->setPrecioPack($precioP);
                            $productoP->setActivo($precioP > 0);
                            if ($productoP->getModelo()) $productoP->getModelo()->setActivo(true);
                            $this->em->persist($productoP);
                        }
                        if ($pCount % 100 === 0) { $this->em->flush(); $this->em->clear(); }
                    } catch (\Exception $e) { continue; }
                }
                fclose($handleP);
            }
        }

        // --- 5. FASE 3: AJUSTE DE PRECIOS MÍNIMOS ---
        $output->writeln("Fase 3: Recalculando precios mínimos...");
        if (!$this->em->isOpen()) $this->em = $this->mr->resetManager();
        $modelosFin = $this->em->getRepository(Modelo::class)->findBy(['proveedor' => $proveedorId, 'activo' => true]);
        foreach ($modelosFin as $idx => $mf) {
            $pMin = $mf->getPrecioUnidad();
            $mf->setPrecioMin($pMin ?? 0);
            $mf->setPrecioMinAdulto($pMin ?? 0);
            if ($idx % 50 === 0) $this->em->flush();
        }
        $this->em->flush();

        $output->writeln("<info>IMPORTACIÓN JHK FINALIZADA CORRECTAMENTE CON TODAS LAS FUNCIONALIDADES</info>");
        return Command::SUCCESS;
    }
}