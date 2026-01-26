<?php

namespace App\Command; // <-- Namespace actualizado

// Entidades actualizadas al namespace App
use App\Entity\AreasTecnicasEstampado;
use App\Entity\Color;
use App\Entity\Fabricante; // <-- Añadido
use App\Entity\Familia;
use App\Entity\Modelo;
use App\Entity\ModeloHasTecnicasEstampado;
use App\Entity\Personalizacion;
use App\Entity\PersonalizacionPrecioCantidad;
use App\Entity\Producto;
use App\Entity\Proveedor;
// Servicios que vamos a inyectar
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface; // <-- ¡NUEVO!

// Quitado 'Nelmio\ApiDocBundle\Util\DocCommentExtractor' (no se usaba)

#[AsCommand(
    name: 'ss:import_command_makito_web',
    description: 'Importa en base de datos los xml de Makito desde la web'
)]
class MakitoWebImportCommand extends Command // <-- Extiende de Command
{
    // Propiedades para los servicios
    private EntityManagerInterface $em;
    private SluggerInterface $slugger;

    // Inyección de dependencias en el constructor
    public function __construct(EntityManagerInterface $em, SluggerInterface $slugger)
    {
        parent::__construct();
        $this->em = $em;
        $this->slugger = $slugger;
    }

    protected function configure()
    {
        $this->addArgument(
            'paso',
            InputArgument::OPTIONAL,
            '1: Familias/Modelos, 2: Variantes/Fotos, 3: Precios Producto, 4: Técnicas (Precios), 5: Asignar Técnicas a Productos'
        );
    }


    protected function execute(InputInterface $input, OutputInterface $output): int // <-- Devuelve int
    {
        ini_set('memory_limit', '-1');

        $paso = $input->getArgument('paso');
        $output->writeln("Iniciando paso: " . (string)$paso); // <-- Cambiado var_dump por writeln

        $nombreProveedor = "Makito";
        $pzinternal = "0002676932808535201852904";

        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);
        if ($proveedor == null) {
            $proveedor = new Proveedor();
            $proveedor->setNombre($nombreProveedor);
        }
        $this->em->persist($proveedor);

        $fabricante = $this->em->getRepository(Fabricante::class)->findOneBy(
            ['nombre' => $nombreProveedor]
        );
        // (Sería bueno comprobar si $fabricante es null aquí, como en los otros comandos)
        if ($fabricante == null) {
            $fabricante = new Fabricante();
            $fabricante->setNombre($nombreProveedor);
            $this->em->persist($fabricante);
        }

        if ($paso == 1 || $paso == 0) {
            $output->writeln("PASO 1- OBTENEMOS LAS FAMILIAS");

            // Usamos executeStatement() para DML (UPDATE, DELETE, INSERT)
            $sql = "UPDATE modelo SET activo = 0 WHERE proveedor = 3320"; // Asumo que 3320 es el ID de Makito
            $stmt = $this->em->getConnection()->prepare($sql);
            $stmt->executeStatement();

            //desenlazamos los productos de las familias de makito
            $sql = "delete from familia_modelo where familia_id in (select id from familia where marca = 57902)"; // Asumo que 57902 es el ID de fabricante Makito
            $stmt = $this->em->getConnection()->prepare($sql);
            $stmt->executeStatement();

            //borramos las familias de makito que no tienen productos
            $sql = "delete from familia where id not in (select familia_id from familia_modelo)";
            $stmt = $this->em->getConnection()->prepare($sql);
            $stmt->executeStatement();

            $urlModelos = "http://print.makito.es:8080/user/xml/ItemDataFile.php?pszinternal=" . $pzinternal;
            $xmlDataFile = simplexml_load_file($urlModelos);

            $nombreFamilia = "";
            foreach ($xmlDataFile->product as $modelo) {
                try {
                    for ($i = 1; $i < 6; $i++) {
                        switch ($i) {
                            case 2: $value = $modelo->categories->category_name_2; break;
                            case 3: $value = $modelo->categories->category_name_3; break;
                            case 4: $value = $modelo->categories->category_name_4; break;
                            case 5: $value = $modelo->categories->category_name_5; break;
                            default: $value = $modelo->categories->category_name_1; break;
                        }
                        if ($value != null && $value != "") {
                            $nombreFamilia = (string)$value;
                            // ¡CAMBIO! Usamos el Slugger
                            $familiaID = $nombreProveedor . "--" . $this->slugger->slug($nombreFamilia)->lower();

                            // Usamos sintaxis ::class
                            $familiaBBDD = $this->em->getRepository(Familia::class)->findOneBy(['id' => $familiaID]);

                            $nuevaFamilia = false;
                            if ($familiaBBDD == null) {
                                $familiaBBDD = new Familia();
                                $familiaBBDD->setId($familiaID);
                                $nuevaFamilia = true;
                            }
                            $familiaBBDD->setPromocional(true);
                            $familiaBBDD->setNombre($nombreFamilia);
//                            $familiaBBDD->setNombreOld($nombreFamilia);
                            // ¡CAMBIO! Usamos el Slugger
                            $familiaBBDD->setNombreUrl($this->slugger->slug($nombreFamilia . "-" . $nombreProveedor)->lower());
                            $familiaBBDD->setProveedor($proveedor);
                            $familiaBBDD->setMarca($fabricante);
                            $this->em->persist($familiaBBDD);
                            if ($nuevaFamilia) {
                                $this->em->flush();
                            }
                        }
                    }

                    $modref = $modelo->ref;
                    if ($modref != null) {
                        $productCode = (string)$modelo->ref;
                        $nombre = $modelo->type . " " . $modelo->name;

                        $pack = intval($modelo->p1_units);
                        $box = intval($modelo->masterbox_units);
                        $order_min_product= intval($modelo->order_min_product);

                        $descripcion = $modelo->otherinfo . "<br>" . $modelo->extendedinfo;

                        // Usamos sintaxis ::class
                        $modeloBBDD = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modref]);

                        $nuevoModelo = false;
                        if ($modeloBBDD == null) {
                            $modeloBBDD = new Modelo();
                            $nuevoModelo = true;
                            $modeloBBDD->setProveedor($proveedor);
                            $modeloBBDD->setFabricante($fabricante);
                            $modeloBBDD->setReferencia($modref);
                            $modeloBBDD->setLargeRef($productCode);
                            $modeloBBDD->setNombre($nombre);
                            // ¡CAMBIO! Usamos el Slugger
                            $modeloBBDD->setNombreUrl($this->slugger->slug("Makito-" . $nombre)->lower());
                            $modeloBBDD->setDescripcion($descripcion);
                            $modeloBBDD->setArticuloPublicitario(true);
                            $modeloBBDD->setTituloSEO($nombreFamilia . " " . "Makito " . $nombre);
//                            $modeloBBDD->setDescripcionOld($descripcion);
                        }
                        $modeloBBDD->setActivo(true);
                        $modeloBBDD->setPack($pack);
                        $modeloBBDD->setBox($box);
                        $modeloBBDD->setDescripcion($descripcion);
                        if ($order_min_product > 0) {
                            $modeloBBDD->setObligadaVentaEnPack(true);
                        }else{
                            $modeloBBDD->setObligadaVentaEnPack(false);
                        }
                        $this->em->persist($modeloBBDD);
                        if ($nuevoModelo) {
                            $this->em->flush();
                        }
                    }
                } catch (\Exception  $e) {
                    $output->writeln('<error>Excepcion al crear el modelo: '. $e->getMessage() .'</error>');
                }
            }
            $this->em->flush();
            $this->em->clear();
        }


        if ($paso == 2 || $paso == 0) {
            // --- INICIO CORRECCIÓN ---
            // Como venimos de un clear(), $proveedor está desconectado. Lo recargamos.
            if ($proveedor->getId()) {
                $proveedor = $this->em->getRepository(Proveedor::class)->find($proveedor->getId());
            } else {
                // Por si acaso no se persistió antes (raro, pero seguro)
                $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);
            }
            // Hacemos lo mismo con fabricante si lo vas a usar
            if ($fabricante && $fabricante->getId()) {
                $fabricante = $this->em->getRepository(Fabricante::class)->find($fabricante->getId());
            }
            // --- FIN CORRECCIÓN ---

            $output->writeln("PASO 2- OBTENEMOS LAS VARIACIONES Y DATOS DE CADA MODELO");
            $sql = "UPDATE modelo SET activo = 0 WHERE proveedor = 3320";
            $stmt = $this->em->getConnection()->prepare($sql);
            $stmt->executeStatement();

            $urlDataFile = "http://print.makito.es:8080/user/xml/ItemDataFile.php?pszinternal=" . $pzinternal;
            $output->writeln("Parseamos el modelo 1");
            $xmlDataFile = simplexml_load_file($urlDataFile); //retrieve URL and parse XML content
            $output->writeln("Parseamos el modelo 2");

            foreach ($xmlDataFile->product as $producto) {
                // Usamos sintaxis ::class
                $myModelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $producto->ref]);

                if ($myModelo != null) {
                    $printCodes = explode(',', (string)$producto->printcode);
                    $this->em->persist($myModelo);

                    $largo = (string)$producto->item_long;
                    $alto = (string)$producto->item_hight;
                    $ancho = (string)$producto->item_width;
                    $diametro = (string)$producto->item_diameter;

                    $url_360 = str_replace("http://", "https://", (string)$producto->link360);
                    $myModelo->setUrl360($url_360);
                    $myModelo->setUrlImage("https://" . str_replace("http://", "", str_replace("https://", "", (string)$producto->imagemain)));
                    $detailImage = "";
                    foreach ($producto->images->image as $image) {
                        $cadena = "https://" . str_replace("http://", "", str_replace("https://", "", (string)$image->imagemax));
                        if (strpos($cadena, ".png") !== false) continue;
                        if (strpos($cadena, "-P.jpg") !== false) continue;
                        if (strpos($detailImage, $cadena) == false)
                            $detailImage = $detailImage . $cadena . ",";
                    }
                    $myModelo->setOtherImages($detailImage);
                    $myModelo->setDetailsImages("https://" . str_replace("http://", "", str_replace("https://", "", (string)$producto->images_gif360->image)));

                    $myModelo->setDescripcion($producto->extendedinfo . "<br>" . $producto->moreinformation);
                    $myModelo->setDescripcionSEO((string)$producto->extendedinfo);
//                    $myModelo->mergeNewTranslations();
                    $myModelo->setComposicion((string)$producto->composition);

                    foreach ($producto->variants->variant as $variacion) {
                        $variacionRef = (string)$variacion->matnr;
                        if ($variacionRef == "") {
                            $output->writeln("ERROR EN REFERENCIA" . $variacion->refct);
                            $variacionRef = (string)$variacion->refct;
                        }
                        $variacionTalla = (string)$variacion->size;

                        // Usamos sintaxis ::class
                        $productosBBDD = $this->em->getRepository(Producto::class)->findBy(
                            ['referencia' => $variacionRef]
                        );

                        if (count($productosBBDD) <= 0) {
                            $productoBBDD = new Producto();
                            $colorNombre = (string)$variacion->colour;
                            // ¡CAMBIO! Usamos el Slugger
                            $colorId = $nombreProveedor . "-" . $this->slugger->slug($colorNombre)->lower();
                            $colorNombreBBDD = $colorNombre;

                            // Usamos sintaxis ::class
                            $color = $this->em->getRepository(Color::class)->findOneBy(
                                ['nombre' => $colorNombre, 'proveedor' => $proveedor]
                            );

                            if ($color == null) {
                                $color = $this->em->getRepository(Color::class)->findOneBy(
                                    ['id' => $colorId]);
                            }

                            if ($color == null) {
                                $color = new Color();
                                $color->setId($colorId);
                                $color->setNombre($colorNombreBBDD);
                                $color->setProveedor($proveedor);
                                $this->em->persist($color);
                                $this->em->flush();
                            }
                            $productoBBDD->setReferencia($variacionRef);
                            $productoBBDD->setColor($color);
                            $productoBBDD->setModelo($myModelo);

                            // ¡CAMBIO! Método corregido
                            $myModelo->addProducto($productoBBDD);
                            $productoBBDD->setTalla($variacionTalla);
                        } else {
                            $productoBBDD = $productosBBDD[0];
                        }

                        if ($productoBBDD->getMedidas() == null) {
                            if ($diametro > 0) {
                                $medidas = $largo . " x " . $diametro;
                            } else {
                                $medidas = $largo . " x " . $ancho . " x " . $alto;
                            }
                            $productoBBDD->setMedidas($medidas);
                        }
                        $productoBBDD->setUrlImage("https://" . str_replace("http://", "", str_replace("https://", "", (string)$variacion->image500px)));
                        $productoBBDD->setActivo(true);
                        $this->em->persist($productoBBDD);
                    }
                    $myModelo->setActivo(true);
                    $this->em->persist($myModelo);
                }
            }
            $this->em->flush();
            $this->em->clear();

            foreach ($xmlDataFile->product as $producto){
                for ($i = 1; $i < 6; $i++) {
                    switch ($i) {
                        case 2: $value = $producto->categories->category_name_2; break;
                        case 3: $value = $producto->categories->category_name_3; break;
                        case 4: $value = $producto->categories->category_name_4; break;
                        case 5: $value = $producto->categories->category_name_5; break;
                        default: $value = $producto->categories->category_name_1; break;
                    }
                    if ($value != null && $value != "") {
                        // ¡CAMBIO! Usamos el Slugger
                        $familiaID = $nombreProveedor . "--" . $this->slugger->slug((string)$value)->lower();
                        $sql = "INSERT IGNORE INTO familia_modelo  (modelo_id, familia_id) select id,'".$familiaID."' from modelo where referencia ='".$producto->ref."';";
                        $stmt = $this->em->getConnection()->prepare($sql);
                        $stmt->executeStatement(); // <-- executeStatement()
                    }
                }
            }
        }

        if ($paso == 3 || $paso == 0) {
            // Recargar proveedor tras el clear() anterior
            $proveedor = $this->em->getRepository(Proveedor::class)->find($proveedor->getId());
            $output->writeln("PASO 3- MODIFICAMOS PRECIOS ONLINE");
            $urlPriceList = "http://print.makito.es:8080/user/xml/PriceListFile.php?pszinternal=" . $pzinternal;
            $xml = simplexml_load_file($urlPriceList);
            foreach ($xml->product as $producto) {
                $modeloReferencia = (string)$producto->ref;
                // Usamos sintaxis ::class
                $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloReferencia, 'proveedor' => $proveedor]);
                if ($modelo != null) {
                    $precioModelo = (float)$producto->price1;
                    if ($precioModelo > 10000) {
                        $precioModelo = 10000;
                    }

                    foreach ($modelo->getProductos() as $productoHijo) { // Renombrada la variable
                        $productoHijo->setPrecioCaja($precioModelo);
                        $productoHijo->setPrecioPack($precioModelo);
                        $productoHijo->setPrecioUnidad($precioModelo);
                        $modelo->setPrecioMin($precioModelo);
                        $modelo->setPrecioMinAdulto($precioModelo);
                        $this->em->persist($productoHijo);
                    }
                    $modelo->setActivo(true);
                }
            }
            $this->em->flush();
            $this->em->clear();

            // Usamos sintaxis ::class
            $modelos = $this->em->getRepository(Modelo::class)->findBy(['proveedor' => $proveedor]);
            foreach ($modelos as $modelo) {
                try {
                    $modelo->setPrecioMin($modelo->getPrecioUnidad(null));
                    if ($modelo->getPrecioCantidadBlancas(10000) > 0) {
                        $modelo->setPrecioMinAdulto($modelo->getPrecioCantidadBlancas(10000));
                    } else {
                        if ($modelo->getPrecioCantidadBlancasNino(10000) > 0) {
                            $modelo->setPrecioMinAdulto($modelo->getPrecioCantidadBlancasNino(10000));
                        } else {
                            $modelo->setPrecioMinAdulto($modelo->getPrecioUnidad());
                        }
                    }
                    $this->em->persist($modelo);
                } catch (\Exception  $e) {
                    $output->writeln('<error>Excepcion al cambiar precio modelo: '. $e->getMessage() .'</error>');
                }
            }
            $this->em->flush(); // <-- Añadido flush al final del bucle de precios
        }

        // ==========================================
        // PASO 4: IMPORTACIÓN DE TÉCNICAS Y PRECIOS
        // XML: PrintPrices_esp.xml
        // ==========================================
        if ($paso == 4) {
            $proveedor = $this->em->getRepository(Proveedor::class)->find($proveedor->getId());
            $output->writeln("PASO 4 - IMPORTANDO TÉCNICAS Y TARIFAS DE ESTAMPADO");

//            $urlPreciosTecnicas = "http://print.makito.es:8080/user/xml/PrintPrices_esp.xml?pszinternal=" . $pzinternal;
            $urlPreciosTecnicas = "http://print.makito.es:8080/user/xml/PrintJobsPrices.php?pszinternal=" . $pzinternal;

            // Cargar XML
            $xml = simplexml_load_file($urlPreciosTecnicas);
            if (!$xml) {
                $output->writeln("<error>No se pudo cargar el XML de precios</error>");
                return Command::FAILURE;
            }

            foreach ($xml->printjobs->printjob as $job) {
                $teccode = (string)$job->teccode;
                $codeName = (string)$job->code;
                $name = (string)$job->name;

                // Generamos ID único: Letra + "-MKT" + Código numérico
                $codigoUnico = $codeName . "-MKT" . $teccode;

                // Datos económicos generales
                $cliche = (float)$job->cliche;       // Pantalla nueva
                $clicheRep = (float)$job->clicherep; // Repetición
                $minJob = (float)$job->minjob;       // Trabajo mínimo

                // 1. Buscar o Crear Personalizacion
                $personalizacion = $this->em->getRepository(Personalizacion::class)->findOneBy(['codigo' => $codigoUnico]);

                if (!$personalizacion) {
                    $personalizacion = $this->em->getRepository(Personalizacion::class)->findOneBy(['teccode' => $teccode]);
                }

                if (!$personalizacion) {
                    $personalizacion = new Personalizacion();
                    $personalizacion->setCodigo($codigoUnico);
                }

                $personalizacion->setNombre($name);
                $personalizacion->setTeccode((int)$teccode);
                $personalizacion->setTrabajoMinimoPorColor((string)$minJob);
                $personalizacion->setNumeroMaximoColores(8); // Valor por defecto seguro
                $personalizacion->setIncrementoPrecio(30); // Resetear o configurar margen 5% a los que hacemos nostros 30%  los de makito
                $personalizacion->setProveedor($proveedor);

                $this->em->persist($personalizacion);

                // 2. Limpiar precios antiguos de ESTA técnica
                // (Usamos SQL directo para eficiencia y evitar problemas de colección)
                $this->em->getConnection()->executeStatement(
                    "DELETE FROM personalizacion_precios WHERE personalizacion = :code",
                    ['code' => $personalizacion->getCodigo()]
                );

                // 3. Insertar Rangos de Precios
                // El XML da el límite superior ("amountunder"). Calculamos el inferior.
                $cantidadInicio = 1;

                // Iteramos del 1 al 7 (son los rangos que da Makito)
                for ($i = 1; $i <= 7; $i++) {
                    $limitField = "amountunder" . $i;
                    $priceField = "price" . $i; // Precio 1 color
                    $priceAddField = "priceaditionalcol" . $i; // Precio colores extra

                    $limit = (int)$job->$limitField;
                    $price = (float)$job->$priceField;
                    $priceAdd = (float)$job->$priceAddField;

                    // Si el precio es 0, suele significar que no hay más rangos
                    if ($price <= 0 && $limit <= 0) break;

                    // Creamos el precio
                    $rango = new PersonalizacionPrecioCantidad();
                    $rango->setPersonalizacion($personalizacion);
                    $rango->setCantidad($cantidadInicio); // "Desde X unidades"

                    // Asignamos precios (convertimos a string para Decimal)
                    $rango->setPrecio((string)$price);
                    $rango->setPrecioColor((string)$price); // Precio base color = Precio base (blanco/color igual en Makito generalmente)

                    $rango->setPrecio2((string)$priceAdd); // Extra color en blanca
                    $rango->setPrecioColor2((string)$priceAdd); // Extra color en color

                    $rango->setPantalla((string)$cliche);
                    $rango->setRepeticion((string)$clicheRep);

                    $this->em->persist($rango);

                    // Preparamos siguiente iteración
                    if ($limit > 0) {
                        $cantidadInicio = $limit;
                    } else {
                        // Si limit es 0 pero había precio, es el último rango (hasta infinito)
                        $cantidadInicio = 999999;
                    }
                }
            }

            $this->em->flush();
            $this->em->clear();
            $output->writeln("Técnicas actualizadas correctamente.");
        }

        // ==========================================
        // PASO 5: ASIGNACIÓN PRODUCTO <-> TÉCNICA <-> ÁREAS
        // XML: allprintdatafile_esp.xml
        // ==========================================
        if ($paso == 5) {
            // Recargamos el proveedor para evitar problemas de Doctrine
            $proveedor = $this->em->getRepository(Proveedor::class)->find($proveedor->getId());
            $output->writeln("PASO 5 - ASIGNACIÓN DE TÉCNICAS (TEXTIL = NUESTRAS / RESTO = MAKITO)");

            // --- CORRECCIÓN DE URL ---
            $urlDatosImpresion = "http://print.makito.es:8080/user/xml/ItemPrintingFile.php?pszinternal=" . $pzinternal;

            // Usamos simplexml_load_file directamente.
            // Si el servidor de Makito tarda, a veces es mejor usar file_get_contents con stream_context, pero probemos así primero.
            $xml = @simplexml_load_file($urlDatosImpresion);

            if (!$xml) {
                $output->writeln("<error>Error al cargar XML desde: $urlDatosImpresion</error>");
                // Intentamos un fallback visual del error
                $error = error_get_last();
                if ($error) {
                    $output->writeln("<error>Detalle PHP: " . $error['message'] . "</error>");
                }
                return Command::FAILURE;
            }

            // --- TUS ÁREAS GENÉRICAS PARA TEXTIL ---
            $areasTextilGenericas = [
                ['name' => 'Delantera',       'w' => 30, 'h' => 30, 'img' => 'https://www.tuskamisetas.com/images/areas/generica_delantera.jpg'],
                ['name' => 'Trasera',         'w' => 30, 'h' => 30, 'img' => 'https://www.tuskamisetas.com/images/areas/generica_trasera.jpg'],
                ['name' => 'Manga Izquierda', 'w' => 10, 'h' => 10, 'img' => 'https://www.tuskamisetas.com/images/areas/generica_manga_izq.jpg'],
                ['name' => 'Manga Derecha',   'w' => 10, 'h' => 10, 'img' => 'https://www.tuskamisetas.com/images/areas/generica_manga_der.jpg'],
            ];
            // Helper para limpiar acentos (Sublimación -> Sublimacion)
            $removeAccents = function($str) {
                $unwanted = ['Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'];
                return strtr($str, $unwanted);
            };

            $batchSize = 20;
            $i = 0;

            foreach ($xml->product as $prodXml) {
                $ref = (string)$prodXml->ref;

                $modelo = $this->em->getRepository(Modelo::class)->findOneBy([
                    'referencia' => $ref,
                    'proveedor' => $proveedor
                ]);

                if (!$modelo) continue;

                // 1. DETECTAR SI ES TEXTIL
                $nombreFamilia = $modelo->getFamilia() ? strtoupper($modelo->getFamilia()->getNombre()) : '';
                $nombreProducto = strtoupper($modelo->getNombre());

                $esTextil = false;
                $keywordsTextil = ['TEXTIL', 'CAMISETA', 'POLO', 'SUDADERA', 'ROPA', 'PARKA', 'CHALECO', 'SOFT SHELL', 'FORRO POLAR'];

                foreach ($keywordsTextil as $kw) {
                    if (str_contains($nombreFamilia, $kw) || str_contains($nombreProducto, $kw)) {
                        $esTextil = true;
                        break;
                    }
                }

                $output->write("Procesando: $ref ");

                // 2. LIMPIEZA TOTAL
                $sqlClean = "DELETE a FROM areas_tecnicas_estampado a INNER JOIN modelo_tecnicas_estampado mt ON a.area_tecnica_id = mt.id WHERE mt.modelo_id = :modeloId";
                $this->em->getConnection()->executeStatement($sqlClean, ['modeloId' => $modelo->getId()]);

                $sqlCleanRel = "DELETE FROM modelo_tecnicas_estampado WHERE modelo_id = :modeloId";
                $this->em->getConnection()->executeStatement($sqlCleanRel, ['modeloId' => $modelo->getId()]);


                // 3. LÓGICA DIFERENCIADA
                // 3. LÓGICA DIFERENCIADA
                if ($esTextil) {
                    $output->writeln("<info>[TEXTIL]</info>");

                    // =========================================================================
                    // A. CONFIGURACIÓN DE TÉCNICAS Y MEDIDAS (W x H en cm)
                    // =========================================================================
                    $definicionesTecnicas = [
                        // --- SERIGRAFÍA (Con medidas especiales para mangas) ---
                        'A1'   => ['w' => 36, 'h' => 42, 'mangas' => true, 'w_manga' => 11, 'h_manga' => 41, 'img_type' => 'std'],

                        // --- TRANSFER / VINILO (P) ---
                        'P1'   => ['w' => 12, 'h' => 12, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
                        'P2'   => ['w' => 26, 'h' => 26, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],

                        // --- TRANSFER (T) ---
                        'T1'   => ['w' => 28, 'h' => 40, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
                        'T2'   => ['w' => 20, 'h' => 28, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
                        'T3'   => ['w' => 20, 'h' => 14, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
                        'T4'   => ['w' => 10, 'h' => 10, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],

                        // --- IMPRESIÓN DIGITAL (DTG) - Solo cuerpo, img específica ---
                        'DTG1' => ['w' => 10, 'h' => 10, 'mangas' => false, 'img_type' => 'dtg'],
                        'DTG2' => ['w' => 20, 'h' => 14, 'mangas' => false, 'img_type' => 'dtg'],
                        'DTG3' => ['w' => 20, 'h' => 30, 'mangas' => false, 'img_type' => 'dtg'],
                        'DTG4' => ['w' => 30, 'h' => 40, 'mangas' => false, 'img_type' => 'dtg'],
                        'DTG5' => ['w' => 40, 'h' => 50, 'mangas' => false, 'img_type' => 'dtg'],
                    ];

                    // Sublimación (SU) - Solo si se detecta
                    $definicionesSublimacion = [
                        'SU1'  => ['w' => 10, 'h' => 10, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
                        'SU2'  => ['w' => 20, 'h' => 14, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
                        'SU3'  => ['w' => 20, 'h' => 28, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
                        'SU4'  => ['w' => 28, 'h' => 40, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
                    ];

                    // =========================================================================
                    // B. DETERMINAR QUÉ TÉCNICAS APLICAR
                    // =========================================================================

                    // 1. Empezamos con el Pack Básico (A, P, T) y DTG (si aplica a todo textil)
                    // Si DTG no aplica a todos, quita las líneas de DTG de $definicionesTecnicas arriba
                    $tecnicasAplicar = $definicionesTecnicas;

                    // 2. Detectar Sublimación
                    $admiteSublimacion = false;
                    $codigosSublimacion = ['100820', '100819'];

                    if (isset($prodXml->printjobs->printjob)) {
                        foreach ($prodXml->printjobs->printjob as $jobXml) {
                            $teccodeXml = (string)$jobXml->teccode;
                            if (in_array($teccodeXml, $codigosSublimacion)) {
                                $admiteSublimacion = true; break;
                            }
                            // Chequeo por nombre
                            $nombresAChequear = [(string)$jobXml->name, (string)$jobXml->tecname];
                            foreach ($nombresAChequear as $rawName) {
                                $nombreNormalizado = strtoupper($removeAccents((string)$rawName));
                                if (str_contains($nombreNormalizado, 'SUBLIMACION') || str_contains($nombreNormalizado, 'SUBLIMATION')) {
                                    $admiteSublimacion = true; break 2;
                                }
                            }
                        }
                    }

                    if ($admiteSublimacion) {
                        $output->writeln("   -> Añadiendo SUBLIMACIÓN (SU1-SU4)");
                        $tecnicasAplicar = array_merge($tecnicasAplicar, $definicionesSublimacion);
                    }

                    // =========================================================================
                    // C. INSERTAR EN BBDD
                    // =========================================================================
                    foreach ($tecnicasAplicar as $codigoTecnica => $specs) {

                        $personalizacion = $this->em->getRepository(Personalizacion::class)->findOneBy(['codigo' => $codigoTecnica]);

                        if (!$personalizacion) {
                            // $output->writeln("<error>   -> Error: $codigoTecnica no existe.</error>");
                            continue;
                        }

                        // Crear relación Modelo <-> Técnica
                        $relacion = new ModeloHasTecnicasEstampado();
                        $relacion->setModelo($modelo);
                        $relacion->setPersonalizacion($personalizacion);
                        $relacion->setMaxcolores(8); // Por defecto en textil
                        $this->em->persist($relacion);

                        // --- 1. ÁREAS DE CUERPO (Delantera / Trasera) ---
                        $areasCuerpo = ['Delantera', 'Trasera'];
                        foreach ($areasCuerpo as $nombreArea) {
                            $suffixImg = ($specs['img_type'] === 'dtg') ? '_dtg.jpg' : '.jpg';
                            $nombreImg = 'generica_' . strtolower($nombreArea) . $suffixImg; // generica_delantera_dtg.jpg

                            $area = new AreasTecnicasEstampado();
                            $area->setTecnica($relacion);
                            $area->setAreaname($nombreArea);
                            $area->setAreawidth((string)$specs['w']);
                            $area->setAreahight((string)$specs['h']);
                            $area->setAreaimg("https://www.tuskamisetas.com/images/areas/" . $nombreImg);
                            $area->setMaxcolores(8);
                            $this->em->persist($area);
                        }

                        // --- 2. ÁREAS DE MANGAS (Solo si la técnica lo permite) ---
                        if ($specs['mangas']) {
                            $areasMangas = [
                                'Manga Izquierda' => 'generica_manga_izq.jpg',
                                'Manga Derecha'   => 'generica_manga_der.jpg'
                            ];

                            foreach ($areasMangas as $nombreArea => $imgFile) {
                                $area = new AreasTecnicasEstampado();
                                $area->setTecnica($relacion);
                                $area->setAreaname($nombreArea);

                                // Usamos medidas específicas de manga (o por defecto 10x10 si no se definen)
                                $wManga = isset($specs['w_manga']) ? $specs['w_manga'] : 10;
                                $hManga = isset($specs['h_manga']) ? $specs['h_manga'] : 10;

                                $area->setAreawidth((string)$wManga);
                                $area->setAreahight((string)$hManga);
                                $area->setAreaimg("https://www.tuskamisetas.com/images/areas/" . $imgFile);
                                $area->setMaxcolores(8);
                                $this->em->persist($area);
                            }
                        }
                    }

                } else {
                    $output->writeln("[NO TEXTIL]");

                    if (!isset($prodXml->printjobs->printjob)) continue;

                    foreach ($prodXml->printjobs->printjob as $jobXml) {
                        $teccodeMakito = (string)$jobXml->teccode;

                        $personalizacion = $this->em->getRepository(Personalizacion::class)->findOneBy(['teccode' => $teccodeMakito]);

                        if (!$personalizacion) continue;

                        $maxColors = 1;
                        foreach ($jobXml->areas->area as $areaCheck) {
                            $mc = (int)$areaCheck->maxcolour;
                            if ($mc > $maxColors) $maxColors = $mc;
                        }

                        $relacion = new ModeloHasTecnicasEstampado();
                        $relacion->setModelo($modelo);
                        $relacion->setPersonalizacion($personalizacion);
                        $relacion->setMaxcolores($maxColors);
                        $this->em->persist($relacion);

                        foreach ($jobXml->areas->area as $areaXml) {
                            $area = new AreasTecnicasEstampado();
                            $area->setTecnica($relacion);
                            $area->setAreaname((string)$areaXml->areaname);
                            $area->setAreawidth((string)$areaXml->areawidth);
                            $area->setAreahight((string)$areaXml->areahight);
                            $img = str_replace("http://", "https://", (string)$areaXml->areaimg);
                            $area->setAreaimg($img);
                            $area->setMaxcolores((int)$areaXml->maxcolour);
                            $this->em->persist($area);
                        }
                    }
                }

                if (($i++ % $batchSize) === 0) {
                    $this->em->flush();
                    $this->em->clear();
                    $proveedor = $this->em->getRepository(Proveedor::class)->find($proveedor->getId());
                }
            }

            $this->em->flush();
            $this->em->clear();
            $output->writeln("Paso 5 completado.");
        }

        $output->writeln("\n TERMINADO IMPORTACION MAKITO " . $paso);
        return Command::SUCCESS;
    }
}