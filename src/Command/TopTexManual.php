<?php
//
//namespace App\Command;
//
//// 1. Importa todas tus entidades (asumiendo que están en App\Entity)
//use App\Entity\Color;
//use App\Entity\Fabricante;
//use App\Entity\Familia;
//use App\Entity\Genero;
//use App\Entity\Modelo;
//use App\Entity\Producto;
//use App\Entity\Proveedor;
//use App\Entity\Tarifa;
//
//// 2. Importa el servicio de Utiles (asumiendo su nueva ruta)
//// 3. Importa los componentes necesarios
//use Doctrine\ORM\EntityManagerInterface;
//
//;
//
//use Symfony\Component\Console\Attribute\AsCommand;
//use Symfony\Component\Console\Command\Command;
//use Symfony\Component\Console\Input\InputArgument;
//use Symfony\Component\Console\Input\InputInterface;
//use Symfony\Component\Console\Output\OutputInterface;
//use Symfony\Component\Console\Style\SymfonyStyle;
//use Symfony\Component\HttpKernel\KernelInterface;
//use Symfony\Component\String\Slugger\SluggerInterface;
//use Symfony\Contracts\HttpClient\HttpClientInterface;
//
//// 4. Define el nombre y descripción con el atributo
//#[AsCommand(
//    name: 'ss:import_command_toptex',
//    description: 'Importa en base de datos el excel de productos de Continental Clothing'
//)]
//class TopTexImportCommand extends Command
//{
//    private string $token = "";
//    private ?Proveedor $proveedor = null; // Tipado para más seguridad
//    private SymfonyStyle $io;
//
//    // 5. Inyecta todas las dependencias y parámetros en el constructor
//    public function __construct(
//        private EntityManagerInterface $em,
//        private HttpClientInterface    $httpClient,
//        private KernelInterface        $kernel,
//        private SluggerInterface       $slugger,
//        // 6. Inyecta tus parámetros de configuración (ver services.yaml abajo)
//        private string                 $apiUrl = "https://api.toptex.io",
//        private string                 $apiKey = "qHWMb9ppfz3xdLHqPCBnZ1ZaSdX8fKru8ciHVgKN",
//        private string                 $nombreProveedor = "TopTex",
//        private string                 $username = "toes_tuskamisetasorgin",
//        private string                 $password = "TusKamisetas474!"
//    )
//    {
//        parent::__construct();
//    }
//
//    protected function configure(): void
//    {
//        $this
//            ->addArgument(
//                'pagina',
//                InputArgument::OPTIONAL,
//                'numero de página por la que empeamos a importar',
//                1
//            )
//            ->addArgument(
//                'fabricante',
//                InputArgument::OPTIONAL,
//                'identificador del fabricante para añadir personalizaciones'
//            );
//    }
//
//    // 7. Reemplaza cURL con HttpClientInterface
//    private function login(): ?array
//    {
//        try {
//            $response = $this->httpClient->request('POST', $this->apiUrl . '/v3/authenticate', [
//                'headers' => [
//                    'accept' => 'application/json',
//                    'x-api-key' => $this->apiKey,
//                    'Content-Type' => 'application/json',
//                ],
//                'json' => [
//                    'username' => $this->username,
//                    'password' => $this->password
//                ]
//            ]);
//
//            $data = $response->toArray(); // Decodifica JSON automáticamente
//            $this->token = $data["token"] ?? '';
//            return $data;
//
//        } catch (\Exception $e) {
//            $this->io->error("Error en la autenticación: " . $e->getMessage());
//            return null;
//        }
//    }
//
//    // 8. Reemplaza cURL con HttpClientInterface
//    private function getModelos(int $pagina, string $lang): ?array
//    {
//        try {
//            $response = $this->httpClient->request('GET', $this->apiUrl . '/v3/products/all', [
//                'headers' => [
//                    'accept' => 'application/json',
//                    'x-api-key' => $this->apiKey,
//                    'x-toptex-authorization' => $this->token
//                ],
//                'query' => [
//                    'usage_right' => 'b2b_uniquement',
//                    'page_number' => $pagina,
//                    'page_size' => 40,
//                    'display_prices' => 1,
//                    'lang' => $lang
//                ]
//            ]);
//
//            return $response->toArray(); // Decodifica JSON automáticamente
//
//        } catch (\Exception $e) {
//            $this->io->error("Error obteniendo modelos: " . $e->getMessage());
//            return null;
//        }
//    }
//
//
//    protected function execute(InputInterface $input, OutputInterface $output): int
//    {
//        ini_set('memory_limit', '-1');
//        // 9. Inicializa SymfonyStyle para salida limpia
//        $this->io = new SymfonyStyle($input, $output);
//
//        $pagina = $input->getArgument('pagina');
//        // $em ya está disponible en $this->em
//
//        if ($pagina == "f" || $pagina == "fno") {
//            $this->creaProveedor();
//            $fabricante = $input->getArgument('fabricante');
//            $this->ponTecnicasSerigrafia($fabricante, $pagina == "f");
//            return Command::SUCCESS;
//        }
//
//
//
//        // --- INICIO DE LA NUEVA LÓGICA ---
//
//        $paginaArgInt = (int) $pagina;
//        $paginaActual = 1; // Valor por defecto
//
//        if ($paginaArgInt == 0) {
//            $this->io->info("Página 0 detectada: Iniciando importación completa desde la página 1.");
//            $paginaActual = 1; // Forzamos el inicio en 1
//        } else {
//            // Esto cubre 1, 2, 3...
//            $this->io->info("Iniciando importación desde la página $paginaArgInt.");
//            $paginaActual = $paginaArgInt; // Empezamos donde se pidió
//        }
//
//        $this->creaProveedor(); // Asegura que el proveedor existe
//
//        // La desactivación se ejecuta si el argumento original fue 0 O 1
//        if ($paginaArgInt == 0 || $paginaArgInt == 1) {
//            $this->io->warning('Iniciando importación completa: Desactivamos productos antiguos.');
//            $this->desactivarProductosAntiguos(); // Esto incluye em->clear()
//        }
//
//        // --- FIN DE LA NUEVA LÓGICA ---
//
//        $this->proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $this->nombreProveedor]);
//        if (!$this->proveedor) {
//            $this->io->error('No se pudo cargar el Proveedor. Abortando.');
//            return Command::FAILURE;
//        }
//
//        if ($this->login() === null) {
//            $this->io->error('Fallo en el login. Abortando.');
//            return Command::FAILURE;
//        }
//
//
//
//        $this->io->info("LLamamos pagina " . $pagina);
//        $modelos = $this->getModelos($pagina, "es");
//
//        if (empty($modelos) || empty($modelos['items'])) {
//            $this->ajustaPrecios();
//            $this->io->warning('No se encontraron items. Ajustando precios y terminando.');
//            $this->io->section('Respuesta API:');
//            $this->io->writeln(json_encode($modelos, JSON_PRETTY_PRINT));
//        } else {
//            foreach ($modelos["items"] as $modelo) {
//                try {
//                    $imagenModelo = null;
//                    $modeloReferencia = $modelo["catalogReference"];
//                    $composicion = $modelo["composition"]["es"];
//                    $fabricanteNombre = $modelo["brand"];
//                    if ($fabricanteNombre == "B&C") $fabricanteNombre = "B & C";
//                    if ($fabricanteNombre == "Dickies Medical") $fabricanteNombre = "Dickies";
//                    if ($fabricanteNombre == "PROACT®") $fabricanteNombre = "PROACT";
//                    if ($fabricanteNombre == "Kimood") $fabricanteNombre = "Ki-mood";
//                    if ($fabricanteNombre == "Russell") $fabricanteNombre = "Russell Europe";
//                    if (in_array($fabricanteNombre, ["Onna", "Mumbles", "TIGER GRIP"])) {
//                        continue;
//                    }
//
//                    $descripcion = $modelo["description"]["es"];
//                    $nombre = $modelo["designation"]["es"];
//                    $familiaNombre = $modelo["sub_family"]["es"];
//                    $genderName = "N/A";
//                    if (count($modelo["gender"]) > 0)
//                        $genderName = $modelo["gender"][0]["es"];
//
//                    // 10. Usa la sintaxis de repositorio moderna
//                    $genero = $this->em->getRepository(Genero::class)->findOneBy(['nombre' => $genderName]);
//                    if ($genero == null) {
//                        $genero = new Genero();
//                        $genero->setNombre($genderName);
//                        $this->em->persist($genero);
//                    }
//
//                    $otherImages = "";
//                    if (isset($modelo["images"]) && count($modelo["images"]) > 0) {
//                        $index = 0;
//                        $fechaPrimeraImagen = null;
//                        foreach ($modelo["images"] as $image) {
//                            if ($index == 0) {
//                                $fechaPrimeraImagen = new \DateTime($image["last_update"]);
//                                $imagenModelo = $image["url"];
//                            } else {
//                                $fechaImagen = new \DateTime($image["last_update"]);
//                                if ($fechaPrimeraImagen && $fechaPrimeraImagen->format("Y") == $fechaImagen->format("Y")) {
//                                    $otherImages = $otherImages . $image["url"] . ",";
//                                }
//                            }
//                            $index++;
//                        }
//                    }
//
//                    $salesArguments1 = $modelo["salesArguments"]["es"];
//                    $salesArguments2 = $modelo["salesArguments"]["es"];
//                    $salesArguments3 = $modelo["salesArguments"]["es"];
//
//                    $fabricante = $this->em->getRepository(Fabricante::class)->findOneBy(['nombre' => $fabricanteNombre]);
//                    if ($fabricante == null) {
//                        $fabricante = new Fabricante();
//                        $fabricante->setNombre($fabricanteNombre);
//                        $fabricante->setNombreUrl($this->slugger->slug($fabricanteNombre));
//                        $this->em->persist($fabricante);
//                        $this->em->flush();
//                    }
//
//                    $modeloBBDD = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloReferencia]);
//                    if ($modeloBBDD == null) {
//                        $modeloBBDD = new Modelo(); // Asegúrate de que el 'use' sea App\Entity\Modelo
//                    }
//
//                    $modeloBBDD->setGender($genero);
//                    if ($otherImages != "") $modeloBBDD->setOtherImages($otherImages);
//
//                    $familiaID = $this->slugger->slug("TKMPX" . $fabricanteNombre . "--" . $familiaNombre);
//                    $familia = $this->em->getRepository(Familia::class)->findOneBy(['id' => $familiaID]);
//                    if ($familia == null) {
//                        $familia = new Familia();
//                        $familia->setId($familiaID);
//                        $familia->setNombre($familiaNombre);
////                        $familia->setNombreOld($familiaNombre);
//                        $familia->setNombreUrl($this->slugger->slug($fabricanteNombre . "--" . $familiaNombre));
////                        $familia->setNombreUrlFromNombre($fabricanteNombre . "--" . $familiaNombre);
//                        $familia->setProveedor($this->proveedor);
//                        $this->em->persist($familia);
//                        $this->em->flush();
//                    }
//
//                    // ... (resto de tu lógica de seteo de datos) ...
//                    // ... (la lógica interna de tu bucle 'foreach' es la misma) ...
//                    // ... (solo asegúrate de usar $this->em y $this->utiles) ...
//
//                    $modeloBBDD->setActivo(true);
//                    $modeloBBDD->setNombre($nombre);
//                    $modeloBBDD->setReferencia($modeloReferencia);
//                    $modeloBBDD->setFabricante($fabricante);
//                    $modeloBBDD->setProveedor($this->proveedor);
//                    $modeloBBDD->setNombreUrl($this->slugger->slug($fabricanteNombre . "-" . $modeloReferencia . "-" . $nombre));
//                    $modeloBBDD->setDescripcion($descripcion . "<br>" . $salesArguments1 . "<br>" . $salesArguments2 . "<br>" . $salesArguments3, 'es');
//                    $modeloBBDD->setUrlImage($imagenModelo);
//                    $modeloBBDD->setDescripcionSEO($descripcion);
////                    $modeloBBDD->setDescripcionSEOOld($descripcion);
//
//                    $familia->setMarca($fabricante);
////                    $familia->addModelosOneToMany($modeloBBDD);
//                    $modeloBBDD->setFamilia($familia);
//                    $this->em->persist($familia);
////                    $this->downloadImagen($modeloBBDD);
//
//                    foreach ($modelo["colors"] as $color) {
//                        if ($color["saleState"] != "active") continue;
//
//                        $colorNombre = $color["colors"]["es"];
//                        $colorId = $this->slugger->slug($fabricanteNombre . "-" . $colorNombre);
//                        $colorBBDD = $this->em->getRepository(Color::class)->findOneBy(array('id' => $colorId));
//
//                        if ($colorBBDD == null) {
//                            $colorBBDD = new Color();
//                            $colorBBDD->setId($colorId);
//                            $colorBBDD->setNombre($colorNombre);
//                            $colorBBDD->setProveedor($this->proveedor);
//                            $colorBBDD->setCodigoColor("#" . ($color["colorsHexa"][0] ?? 'FFFFFF'));
//                            $this->em->persist($colorBBDD);
//                        }
//
//                        $productoImagen = ""; // ... lógica para obtener la imagen del producto ...
//
////                        if ($imagenModelo == null) {
//                        $modeloBBDD->setUrlImage($productoImagen);
////                            $this->downloadImagen($modeloBBDD);
////                        }
//
//                        foreach ($color["sizes"] as $talla) {
//                            if ($talla["saleState"] != "active") continue;
//
//                            $productoReferencia = $talla["sku"];
//                            $productoBBDD = $this->em->getRepository(Producto::class)->findOneBy(array('referencia' => $productoReferencia));
//                            if ($productoBBDD == null) {
//                                $productoBBDD = new Producto();
//                            }
//
//                            $valorPrecio = 0; // ... lógica para obtener precio ...
//
//                            $modeloBBDD->setComposicion($composicion . " " . $talla["coatingWeight_fr"]);
//                            $modeloBBDD->setBox($talla["unitsPerBox"]);
//                            $modeloBBDD->setPack($talla["unitsPerPack"]);
//
//                            $productoBBDD->setReferencia($productoReferencia);
//                            $productoBBDD->setTalla($talla["size"]);
//                            $productoBBDD->setColor($colorBBDD);
//                            $productoBBDD->setActivo(true);
//                            $productoBBDD->setPrecioUnidad($valorPrecio);
//                            $productoBBDD->setUrlImage($productoImagen);
//
////                            $this->downloadImagenProducto($productoBBDD, $modeloBBDD);
//                            $modeloBBDD->addProducto($productoBBDD);
//
//                            $this->em->persist($modeloBBDD);
//                            $this->em->persist($productoBBDD);
//                            $this->em->flush(); // Flush original, uno por cada talla
//                        }
//                    }
//
//                    // ----------------------------------------------------
//                    // !! RECOMENDACIÓN DE RENDIMIENTO !!
//                    // Mueve el $this->em->flush() fuera del bucle 'foreach ($color["sizes"]...)'
//                    // Ver nota abajo. Por ahora, mantengo tu lógica original.
//                    // ----------------------------------------------------
//
//                } catch (\Exception $e) {
//                    $this->io->error("ERROR PROCESANDO MODELO {$modelo['catalogReference']}: " . $e->getMessage());
//                    if (!$this->em->isOpen()) {
//                        // Re-crea el EntityManager si se cerró (esto es más complejo en SF6)
//                        // Por ahora, solo logueamos. Si esto pasa a menudo, necesita un manejador.
//                        $this->io->error('EntityManager se cerró. Abortando bucle.');
//                        break;
//                    }
//                }
//            }
//        }
//        $this->io->success("Proceso Finalizado");
//        return Command::SUCCESS;
//    }
//
//
//    // 11. Todos los métodos privados ahora usan $this->em, $this->kernel, $this->utiles
//    // No necesitan 'getContainer()'
//
//    private function creaProveedor()
//    {
//        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $this->nombreProveedor]);
//
//        if ($proveedor == null) {
//            $proveedor = new Proveedor();
//            $tarifa = $this->em->getRepository(Tarifa::class)->findOneBy(['id' => 1]);
//
//            if (!$tarifa) {
//                // Importante: Si la tarifa 1 no existe, el comando fallará.
//                // Es mejor detenerse aquí con un error claro.
//                throw new \RuntimeException('La Tarifa con ID 1 no existe. No se puede crear el proveedor.');
//            }
//
//            $proveedor->setTarifa($tarifa);
//            $proveedor->setNombre($this->nombreProveedor);
//
//            // --- ¡VOLVEMOS A AÑADIR ESTO! ---
//            // Es necesario para que el proveedor exista en la BBDD
//            // antes de que lo "desvinculemos" con em->clear().
//            $this->em->persist($proveedor);
//            $this->em->flush();
//            // ---------------------------------
//        }
//        $this->proveedor = $proveedor;
//    }
//
//    private function ajustaPrecios()
//    {
//        $this->io->info("EMPEZAMOS AJUSTAR PRECIOS");
//        $modelos = $this->em->getRepository(Modelo::class)->findBy(['proveedor' => $this->proveedor->getId()]);
//        foreach ($modelos as $modelo) {
//            try {
//                // ... (lógica de precios sin cambios) ...
//                $this->em->persist($modelo);
//            } catch (\Exception  $e) {
//                $this->io->warning('Excepcion al cambiar precio modelo: ' . $e->getMessage());
//            }
//        }
//        $this->em->flush();
//        $this->em->clear();
//        $this->io->info("SE TERMINA DE AJUSTAR PRECIOS");
//    }
//
//    private function ponTecnicasSerigrafia($fabricante, $conDTG)
//    {
//        $conn = $this->em->getConnection();
//        $tecnicas = ['A1', 'P1', 'P2', 'T1', 'T2', 'T3', 'T4', 'P1'];
//        if ($conDTG) {
//            $tecnicas = array_merge($tecnicas, ['DTG1', 'DTG2', 'DTG3', 'DTG4', 'DTG5']);
//        }
//        $sql = "INSERT IGNORE INTO modelo_tecnicas_estampado (modelo_id, personalizacion_id)
//                SELECT id, :tecnica_id FROM modelo WHERE fabricante = :fabricante_id AND proveedor = :proveedor_id";
//        foreach ($tecnicas as $tecnica) {
//            $stmt = $conn->prepare($sql);
//            $stmt->bindValue('tecnica_id', $tecnica);
//            $stmt->bindValue('fabricante_id', $fabricante);
//            $stmt->bindValue('proveedor_id', $this->proveedor->getId());
//            $stmt->executeStatement();
//        }
////        $em->flush();
////        $em->clear();
//    }
//
//    private function desactivarProductosAntiguos()
//    {
//        $conn = $this->em->getConnection();
//        $conn->executeStatement("UPDATE modelo SET activo = 0 WHERE proveedor = 3323");
//
//        $sql = "UPDATE modelo SET activo = 0 WHERE proveedor = :proveedorId";
//        $stmt = $conn->prepare($sql);
//        $stmt->bindValue('proveedorId', $this->proveedor->getId());
//        $stmt->executeStatement();
//
//        $sql = "UPDATE producto SET activo = 0 WHERE modelo in (SELECT id FROM modelo WHERE proveedor = :proveedorId)";
//        $stmt = $conn->prepare($sql);
//        $stmt->bindValue('proveedorId', $this->proveedor->getId());
//        $stmt->executeStatement();
//
//        $conn->executeStatement("DELETE FROM familia_modelo WHERE familia_id LIKE 'TKMPX%'");
//        $conn->executeStatement("DELETE FROM familia WHERE id LIKE 'TKMPX%'");
//        // --- ¡LA SOLUCIÓN! ---
//        // Limpia la caché interna de Doctrine para estas entidades.
//        // Esto fuerza a Doctrine a recargar los datos de la BBDD
//        // en el próximo ->find() o ->findOneBy().
//        $this->em->clear(Modelo::class);
//        $this->em->clear(Producto::class);
//        $this->em->clear(Familia::class);
//    }
//
////    private function downloadImagen($modelo)
////    {
////        if (!$modelo || !$modelo->getUrlImage()) return;
////
////        try {
////            $modelo->setUpdated(new \DateTime("now"));
////            $relativePath = '/uploads/media/modelo/' . $this->utiles->stringURLSafe($modelo->getFabricante() . "-" . $modelo->getReferencia()) . '.jpg';
////            // 12. La ruta 'web' ahora es 'public'
////            $absoluteSavePath = $this->kernel->getProjectDir() . '/public' . $relativePath;
////
////            // 13. Llama al servicio 'utiles' inyectado
////            $this->utiles->creaImagen(
////                preg_replace("/ /", "%20", $modelo->getUrlImage()),
////                $absoluteSavePath,
////                50
////            );
////
////            $modelo->setUrlImage($relativePath);
////            $modelo->setImagenDescargada(true);
////            $this->io->text("DESCARGADA IMAGEN DE MODELO: " . $modelo->getReferencia());
////
////            $this->em->persist($modelo);
////            $this->em->flush();
////        } catch (\Exception $e) {
////            $this->io->warning("Excepcion al guardar IMAGEN MODELO: " . $e->getMessage());
////        }
////    }
//
////    private function downloadImagenProducto($producto, $modelo)
////    {
////        if (!$producto || !$producto->getUrlImage()) return;
////
////        try {
////            $relativePath = '/uploads/media/producto/' . $this->utiles->stringURLSafe($modelo->getFabricante() . '-' . $modelo->getReferencia() . "-" . $producto->getColor()) . '.jpg';
////            // 12. La ruta 'web' ahora es 'public'
////            $absoluteSavePath = $this->kernel->getProjectDir() . '/public' . $relativePath;
////
////            if (!file_exists($absoluteSavePath)) {
////                // 13. Llama al servicio 'utiles' inyectado
////                $this->utiles->creaImagen(
////                    preg_replace("/ /", "%20", $producto->getUrlImage()),
////                    $absoluteSavePath,
////                    50
////                );
////                $this->io->text("\tDESCARGADA IMAGEN DE PRODUCTO: " . $producto->getReferencia());
////            }
////
////            $producto->setUrlImage($relativePath);
////            $producto->setImagenDescargada(true);
////            $this->em->persist($producto);
////        } catch (\Exception $e) {
////            $this->io->warning("Excepcion al guardar IMAGEN PRODUCTO: " . $e->getMessage());
////        }
////    }
//}