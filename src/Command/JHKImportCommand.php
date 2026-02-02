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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'ss:import_command_jhk',
    description: 'Importación total de JHK: Desactivación, Optimización de Imágenes y Precios.'
)]
class JHKImportCommand extends Command
{
    private EntityManagerInterface $em;
    private SluggerInterface $slugger;
    private ImageManager $imageManager;

    public function __construct(EntityManagerInterface $em, SluggerInterface $slugger, ImageManager $imageManager)
    {
        parent::__construct();
        $this->em = $em;
        $this->slugger = $slugger;
        $this->imageManager = $imageManager;
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

        // --- 1. GESTIÓN INICIAL DE PROVEEDOR/FABRICANTE ---
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

        // --- 2. DESACTIVACIÓN PREVIA (Limpieza del maestro) ---
        $output->writeln("<comment>Desactivando catálogo antiguo de JHK...</comment>");
        $this->em->getConnection()->executeStatement(
            "UPDATE producto p JOIN modelo m ON p.modelo = m.id SET p.activo = 0 WHERE m.proveedor = ?",
            [$proveedorId]
        );
        $this->em->getConnection()->executeStatement(
            "UPDATE modelo SET activo = 0 WHERE proveedor = ?",
            [$proveedorId]
        );

        // --- 3. PROCESAMIENTO DE PRODUCTOS Y MODELOS ---
        $output->writeln("Fase 1: Importando y activando desde $filename");
        $header = null; $rowCount = 0; $modelosEnMemoria = [];

        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($rowCsv = fgetcsv($handle, 0, ';')) !== FALSE) {
                if (!$header) { $header = array_map('trim', $rowCsv); continue; }
                $rowCount++;
                $row = array_combine($header, array_map('trim', $rowCsv));

                // Recargar entidades base tras clear()
                if (!$this->em->contains($proveedor)) {
                    $proveedor = $this->em->find(Proveedor::class, $proveedorId);
                    $fabricante = $this->em->find(Fabricante::class, $fabricanteId);
                }

                $modeloRef = $row["Referencia"] ?? null;
                $prodRef = $row["Combinacion"] ?? null;
                $colorRef = $row["RefColor"] ?? 'col';

                if (!$modeloRef || !$prodRef) continue;

                try {
                    // Gestión de Modelo
                    if (isset($modelosEnMemoria[$modeloRef])) {
                        $modelo = $modelosEnMemoria[$modeloRef];
                    } else {
                        $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloRef]);
                        if (!$modelo) {
                            $modelo = new Modelo();
                            $modelo->setReferencia($modeloRef);
                            $modelo->setProveedor($proveedor);
                            $modelo->setFabricante($fabricante);
                            $this->em->persist($modelo);
                        }
                        $modelosEnMemoria[$modeloRef] = $modelo;
                    }

                    // Actualizar datos y ACTIVAR
                    $modelo->setActivo(true);
                    $modelo->setNombre($row["Nombre"] ?? $modeloRef);

                    // Imagen del catálogo (1 por modelo)
                    if (!empty($row["URLCatalogue"]) && $row["URLCatalogue"] !== '---') {
//                        $urlImgM = str_replace("http://", "https://", $row["URLCatalogue"]);
                        $urlImgM =$row["URLCatalogue"] ?? null;
                        $modelo->setUrlImage($this->imageManager->download($urlImgM, 'jhk/modelos', $modeloRef));
                    }

                    $modelo->setComposicion($row["Composicion"] ?? '');
                    $modelo->setBox(intval($row["box"] ?? 0));
                    $modelo->setPack(intval($row["bag"] ?? 0));

                    // Gestión de Producto
                    $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $prodRef]);
                    if (!$producto) {
                        $producto = new Producto();
                        $producto->setReferencia($prodRef);
                    }
                    $producto->setModelo($modelo);
                    $producto->setTalla($row["Talla"] ?? 'U');

                    // Color
                    if (!empty($row["Color"])) {
                        $colorId = $nombreProveedor . "-" . $this->slugger->slug($row["Color"])->lower();
                        $color = $this->em->find(Color::class, $colorId);
                        if (!$color) {
                            $color = new Color();
                            $color->setId($colorId);
                            $color->setNombre($row["Color"]);
                            $color->setProveedor($proveedor);
                            $this->em->persist($color);
                        }
                        $producto->setColor($color);
                    }

                    // Imagen de Producto OPTIMIZADA (1 por color, no por talla)
                    if (!empty($row["URLSku"]) && $row["URLSku"] !== '---') {
//                        $urlImgP = str_replace("http://", "https://", $row["URLSku"]);
                        $urlImgP = $row["URLSku"] ?? null;
                        $nombreImagen = $modeloRef . "-" . $colorRef;
                        $producto->setUrlImage($this->imageManager->download($urlImgP, 'jhk/productos', $nombreImagen));
                    }

                    // Precios Picking y ACTIVAR producto
                    $pPicking = $this->tofloati($row["Picking"] ?? 0);
                    $producto->setPrecioUnidad($pPicking);
                    $producto->setPrecioCaja($pPicking);
                    $producto->setPrecioPack($pPicking);
                    $producto->setActivo($pPicking > 0);

                    $this->em->persist($producto);

                    if ($rowCount % 50 === 0) {
                        $this->em->flush();
                        $this->em->clear();
                        $modelosEnMemoria = [];
                        $output->writeln("Procesados $rowCount registros...");
                    }
                } catch (\Exception $e) {
                    $output->writeln("\n<error>Error fila $rowCount: " . $e->getMessage() . "</error>");
                }
            }
            fclose($handle);
        }
        $this->em->flush();
        $this->em->clear();

        // --- 4. ACTUALIZACIÓN DE PRECIOS DESDE TARIFAS ---
        if (file_exists($filenamePrecios)) {
            $output->writeln("Fase 2: Actualizando precios desde $filenamePrecios");
            if (($handleP = fopen($filenamePrecios, 'r')) !== FALSE) {
                $headerP = null; $pCount = 0;
                while (($rowP = fgetcsv($handleP, 0, ';')) !== FALSE) {
                    if (!$headerP) { $headerP = array_map('trim', $rowP); continue; }
                    $pCount++;
                    $dataP = array_combine($headerP, array_map('trim', $rowP));
                    $sku = $dataP["SKU"] ?? null;
                    if (!$sku) continue;

                    $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $sku]);
                    if ($producto) {
                        $precio = $this->tofloati($dataP["PRECIO"]);
                        $producto->setPrecioUnidad($precio);
                        $producto->setPrecioCaja($precio);
                        $producto->setPrecioPack($precio);
                        // Solo activar si el precio es mayor a 0
                        $producto->setActivo($precio > 0);
                        if ($producto->getModelo()) {
                            $producto->getModelo()->setActivo(true);
                        }
                        $this->em->persist($producto);
                    }
                    if ($pCount % 100 === 0) {
                        $this->em->flush();
                        $this->em->clear();
                    }
                }
                fclose($handleP);
            }
            $this->em->flush();
            $this->em->clear();
        }

        // --- 5. AJUSTE FINAL DE PRECIOS MÍNIMOS ---
        $output->writeln("Fase 3: Calculando precios mínimos por modelo...");
        $proveedor = $this->em->getRepository(Proveedor::class)->find($proveedorId);
        $modelos = $this->em->getRepository(Modelo::class)->findBy(['proveedor' => $proveedor, 'activo' => true]);
        foreach ($modelos as $idx => $m) {
            $pMin = $m->getPrecioUnidad(); // Tu función de entidad que busca el menor de sus productos
            $m->setPrecioMin($pMin ?? 0);
            $m->setPrecioMinAdulto($pMin ?? 0);
            if ($idx % 50 === 0) $this->em->flush();
        }
        $this->em->flush();

        $output->writeln("<info>IMPORTACIÓN JHK COMPLETADA CORRECTAMENTE</info>");
        return Command::SUCCESS;
    }
}