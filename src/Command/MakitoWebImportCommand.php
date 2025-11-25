<?php

namespace App\Command; // <-- Namespace actualizado

// Entidades actualizadas al namespace App
use App\Entity\Color;
use App\Entity\Fabricante; // <-- Añadido
use App\Entity\Familia;
use App\Entity\Modelo;
use App\Entity\Personalizacion;
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
        // El nombre y la descripción ya están en el atributo #[AsCommand]
        $this->addArgument(
            'paso',
            InputArgument::OPTIONAL,
            'Indica el paso a ejecutar ya que el bloque entero tarda mas de una hora 1: Importa Familias, 2: Importa productos, 3:Actualiza precios, 4: Técnicas'
        );
    }


    protected function execute(InputInterface $input, OutputInterface $output): int // <-- Devuelve int
    {
        ini_set('memory_limit', '-1');

        $paso = $input->getArgument('paso');
        $output->writeln("Iniciando paso: " . (string)$paso); // <-- Cambiado var_dump por writeln

        $nombreProveedor = "Makito";
        $pzinternal = "0002676932808535201852904";

        // $controller = $this->getContainer(); // <-- Línea eliminada
        // $em = $controller->get('doctrine')->getManager(); // <-- Línea eliminada, usamos $this->em

        // Usamos $this->em y la sintaxis ::class
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
                        if ($pack > 0) {
                            $modeloBBDD->setObligadaVentaEnPack(true);
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

                    foreach ($modelo->getModeloHasProductos() as $productoHijo) { // Renombrada la variable
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

        if ($paso == 4) {
            $output->writeln("PASO 4- PONEMOS LAS TECNICAS DE ESTAMPADO");
            $urlPersonalizaciones = "http://print.makito.es:8080/user/xml/PrintJobsPrices.php?pszinternal=" . $pzinternal;
            $xmlPersonalizaciones = simplexml_load_file($urlPersonalizaciones);

            foreach ($xmlPersonalizaciones->printjobs->printjob as $printjob) {
                $teccode = (string)$printjob->teccode;
                $pedidoMinimo = (float)$printjob->minamount;
                $pantalla = (float)$printjob->cliche;
                $pantallarep = (float)$printjob->clicherep;
                $code = $printjob->code . "-MKT" . $teccode;
                $name = (string)$printjob->name;

                if ($printjob->code == null || $printjob->code == "") continue;

                // Usamos sintaxis ::class
                $personalizacion = $this->em->getRepository(Personalizacion::class)->findOneBy(['teccode' => $teccode]);

                $sql = "delete from personalizacion_precios where personalizacion = " . "'" . $code . "'";
                $stmt = $this->em->getConnection()->prepare($sql);
                $stmt->executeStatement(); // <-- executeStatement()

                if ($personalizacion == null) {
                    $personalizacion = new Personalizacion();
                }
                $personalizacion->setCodigo($code);
                $personalizacion->setNombre($name);
                $personalizacion->setTeccode($teccode);
                $personalizacion->setTrabajoMinimoPorColor($pedidoMinimo);
                $personalizacion->setNumeroMaximoColores(6);
                $this->em->persist($personalizacion);
                $this->em->flush();
                $this->em->clear();

                // Lógica de precios de personalización (con executeStatement)
                $amount = 0; $price1 = (float)$printjob->price1; $price2 = (float)$printjob->priceaditionalcol1;
                if ($price1 > 0) {
                    $sql = "insert into personalizacion_precios (personalizacion, cantidad, precio, precio2Color, pantalla, repeticion, precio_color, precio_color2Color) VALUES ('" . $code . "'," . $amount . "," . $price1 . "," . $price2 . "," . $pantalla . "," . $pantallarep . "," . $price1 . "," . $price2 . ")";
                    $this->em->getConnection()->prepare($sql)->executeStatement();
                }
                $amount = (int)$printjob->amountunder1; $price1 = (float)$printjob->price2; $price2 = (float)$printjob->priceaditionalcol2;
                if ($price1 > 0) {
                    $sql = "insert into personalizacion_precios (personalizacion, cantidad, precio, precio2Color, pantalla, repeticion, precio_color, precio_color2Color) VALUES ('" . $code . "'," . $amount . "," . $price1 . "," . $price2 . "," . $pantalla . "," . $pantallarep . "," . $price1 . "," . $price2 . ")";
                    $this->em->getConnection()->prepare($sql)->executeStatement();
                }
                // ... (repetir para price3 a price7)
                $amount = (int)$printjob->amountunder2; $price1 = (float)$printjob->price3; $price2 = (float)$printjob->priceaditionalcol3;
                if ($price1 > 0) {
                    $sql = "insert into personalizacion_precios (personalizacion, cantidad, precio, precio2Color, pantalla, repeticion, precio_color, precio_color2Color) VALUES ('" . $code . "'," . $amount . "," . $price1 . "," . $price2 . "," . $pantalla . "," . $pantallarep . "," . $price1 . "," . $price2 . ")";
                    $this->em->getConnection()->prepare($sql)->executeStatement();
                }
                $amount = (int)$printjob->amountunder3; $price1 = (float)$printjob->price4; $price2 = (float)$printjob->priceaditionalcol4;
                if ($price1 > 0) {
                    $sql = "insert into personalizacion_precios (personalizacion, cantidad, precio, precio2Color, pantalla, repeticion, precio_color, precio_color2Color) VALUES ('" . $code . "'," . $amount . "," . $price1 . "," . $price2 . "," . $pantalla . "," . $pantallarep . "," . $price1 . "," . $price2 . ")";
                    $this->em->getConnection()->prepare($sql)->executeStatement();
                }
                $amount = (int)$printjob->amountunder4; $price1 = (float)$printjob->price5; $price2 = (float)$printjob->priceaditionalcol5;
                if ($price1 > 0) {
                    $sql = "insert into personalizacion_precios (personalizacion, cantidad, precio, precio2Color, pantalla, repeticion, precio_color, precio_color2Color) VALUES ('" . $code . "'," . $amount . "," . $price1 . "," . $price2 . "," . $pantalla . "," . $pantallarep . "," . $price1 . "," . $price2 . ")";
                    $this->em->getConnection()->prepare($sql)->executeStatement();
                }
                $amount = (int)$printjob->amountunder5; $price1 = (float)$printjob->price6; $price2 = (float)$printjob->priceaditionalcol6;
                if ($price1 > 0) {
                    $sql = "insert into personalizacion_precios (personalizacion, cantidad, precio, precio2Color, pantalla, repeticion, precio_color, precio_color2Color) VALUES ('" . $code . "'," . $amount . "," . $price1 . "," . $price2 . "," . $pantalla . "," . $pantallarep . "," . $price1 . "," . $price2 . ")";
                    $this->em->getConnection()->prepare($sql)->executeStatement();
                }
                $amount = (int)$printjob->amountunder6; $price1 = (float)$printjob->price7; $price2 = (float)$printjob->priceaditionalcol7;
                if ($price1 > 0) {
                    $sql = "insert into personalizacion_precios (personalizacion, cantidad, precio, precio2Color, pantalla, repeticion, precio_color, precio_color2Color) VALUES ('" . $code . "'," . $amount . "," . $price1 . "," . $price2 . "," . $pantalla . "," . $pantallarep . "," . $price1 . "," . $price2 . ")";
                    $this->em->getConnection()->prepare($sql)->executeStatement();
                }
            }


            $urlTecnicasList = "http://print.makito.es:8080/user/xml/ItemPrintingFile.php?pszinternal=" . $pzinternal;
            $xml = simplexml_load_file($urlTecnicasList);
            foreach ($xml->product as $producto) {
                $modeloReferencia = (string)$producto->ref;
                // Usamos sintaxis ::class
                $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloReferencia, 'proveedor' => $proveedor]);
                if ($modelo != null) {

                    $sql = "delete from modelo_tecnicas_estampado where modelo_id = " . $modelo->getId();
                    $stmt = $this->em->getConnection()->prepare($sql);
                    $stmt->executeStatement();

                    foreach ($producto->printjobs->printjob as $printjob) {
                        $teccode = (string)$printjob->teccode;
                        // Usamos sintaxis ::class
                        $personalizacion = $this->em->getRepository(Personalizacion::class)->findOneBy(['teccode' => $teccode]);
                        if ($personalizacion != null) {
                            $sql = "insert into modelo_tecnicas_estampado (modelo_id, personalizacion_id, maxcolores) VALUES (" . $modelo->getId() . ",'" . $personalizacion->getCodigo() . "'," . $printjob->maxcolour . ")";
                            $stmt = $this->em->getConnection()->prepare($sql);
                            $stmt->executeStatement();

                            $lastID = $this->em->getConnection()->lastInsertId(); // Nota: lastInsertId puede no necesitar argumento en PDO

                            $sql = "delete from areas_tecnicas_estampado where tecnica_id = '" . $personalizacion->getCodigo() . "'";
                            $stmt = $this->em->getConnection()->prepare($sql);
                            $stmt->executeStatement();

                            // $output->writeln(print_r($printjob->areas, true)); // Cambiado var_dump

                            foreach ($printjob->areas->area as $area) {
                                try {
                                    $areaname = (string)$area->areaname;
                                    $areawidth = (float)$area->areawidth;
                                    $areahight = (float)$area->areahight;
                                    $areaimg = (string)$area->areaimg;
                                    $areacode = (string)$area->areacode;
                                    // ¡OJO! Asumo que 'tecnica_id' en 'areas_tecnicas_estampado' debe ser el ID de 'modelo_tecnicas_estampado' ($lastID)
                                    // Si debe ser el código de 'personalizacion', cambia $lastID por $personalizacion->getCodigo()
                                    $sql = "insert into areas_tecnicas_estampado (tecnica_id, areawidth, areahight, areaname , areaimg) VALUES (" . $lastID . "," . $areawidth . "," . $areahight . ",'" . $areaname . "','" . $areaimg . "')";
                                    // $output->writeln($sql); // Cambiado var_dump
                                    $stmt = $this->em->getConnection()->prepare($sql);
                                    $stmt->executeStatement();
                                } catch (\Exception  $e) {
                                    $output->writeln('<error>Excepcion al crear el area del trabajo: '. $e->getMessage() .'</error>');
                                }
                            }
                        }
                    }
                }
            }
            $this->em->flush();
            $this->em->clear();
        }

        $this->em->flush();
        $this->em->clear();

        $output->writeln("\n TERMINADO IMPORTACION MAKITO " . $paso);
        return Command::SUCCESS; // <-- Devolver SUCCESS
    }
}