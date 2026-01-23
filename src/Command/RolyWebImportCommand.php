<?php

namespace App\Command; // <-- Namespace actualizado

// Entidades actualizadas al namespace App
use App\Entity\Color;
use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Genero;
use App\Entity\Modelo;
use App\Entity\Producto;
use App\Entity\Proveedor;
use App\Entity\Tarifa; // <-- Añadido
// Servicios que vamos a inyectar
use Doctrine\ORM\EntityManagerInterface;
// Se eliminan las 'use' de Caché
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface; // <-- Para el slug

#[AsCommand(
    name: 'ss:import_command_roly_web',
    description: 'Importa en base de datos los productos de Roly/Stamina desde su API.'
)]
class RolyWebImportCommand extends Command
{
    private const API_URL = "https://clientsws.gorfactory.es:2096/api";
    private const USERNAME = "tuskamisetas@gmail.com";
    private const PASSWORD = "hb2GxMgQ";

    // Propiedades para los servicios inyectados
    private EntityManagerInterface $em;
    private SluggerInterface $slugger;
    // Se elimina la propiedad de la caché

    private $token;
    private $fabricanteParam;

    // Inyectamos solo los servicios que SÍ existen
    public function __construct(
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ) {
        parent::__construct();
        $this->em = $em;
        $this->slugger = $slugger;
    }

    protected function configure()
    {
        $this->addArgument(
            'fabricante',
            InputArgument::REQUIRED,
            'Identificador del fabricante: "roly" o "stamina".'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>--- INICIO DE LA IMPORTACIÓN DEL CATÁLOGO (MODO ESTABLE) ---</info>');
        $this->fabricanteParam = $input->getArgument('fabricante');
        if (!in_array($this->fabricanteParam, ['roly', 'stamina'])) {
            $output->writeln('<error>El fabricante debe ser "roly" o "stamina".</error>');
            return Command::FAILURE;
        }

        ini_set('memory_limit', '-1');

        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        $lastProcessedRow = null;

        try {
            $output->writeln('1. Autenticando contra la API...');
            $this->authenticate();
            $output->writeln('<info>Autenticación exitosa.</info>');

            $output->writeln('2. Descargando datos de la API...');
            $modelosApi = $this->fetchCatalog();
            $preciosApi = $this->fetchPrices();
            $output->writeln('<info>Datos de la API descargados.</info>');

            if (empty($modelosApi['item'])) {
                $output->writeln('<comment>No se encontraron items en el catálogo de la API. Finalizando.</comment>');
                return Command::SUCCESS;
            }

            $this->processDataOneByOne($output, $modelosApi, $preciosApi, $lastProcessedRow);

            $this->ajustaPrecios($output);

            // $this->clearModelsCache($output); // <-- LLAMADA ELIMINADA

        } catch (\Exception $e) {
            $output->writeln('<error>Ha ocurrido un error: ' . $e->getMessage() . '</error>');
            if ($lastProcessedRow) {
                $output->writeln('<comment>--- DATOS DE LA FILA QUE CAUSÓ EL ERROR ---</comment>');
                $output->writeln(print_r($lastProcessedRow, true));
                $output->writeln('<comment>-----------------------------------------</comment>');
            }
            return Command::FAILURE;
        }

        $output->writeln('<info>--- FIN DEL PROCESO ---</info>');
        return Command::SUCCESS;
    }

    /**
     * Procesa y guarda los datos uno por uno. Es más lento pero más estable.
     */
    private function processDataOneByOne(OutputInterface $output, array $modelosApi, array $preciosApi, &$lastProcessedRow)
    {
        $output->writeln('3. Procesando y guardando datos (uno por uno)...');
        $em = $this->em;

        $nombreProveedor = ($this->fabricanteParam === 'stamina') ? 'Stamina' : 'Roly';

        $proveedor = $em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);
        if (!$proveedor) {
            $proveedor = new Proveedor();
            $proveedor->setNombre($nombreProveedor);
            $tarifa = $em->getRepository(Tarifa::class)->find(1);
            if (!$tarifa) throw new \Exception('La tarifa por defecto con ID 1 no fue encontrada.');
            $proveedor->setTarifa($tarifa);
            $em->persist($proveedor);
        }

        $fabricante = $em->getRepository(Fabricante::class)->findOneBy(['nombre' => $nombreProveedor]);
        if (!$fabricante) {
            $fabricante = new Fabricante();
            $fabricante->setNombre($nombreProveedor);
            $em->persist($fabricante);
        }
        $em->flush();

        $conn = $em->getConnection();
        $conn->executeStatement('UPDATE modelo SET activo = 0 WHERE proveedor = ?', [$proveedor->getId()]);
        $conn->executeStatement('UPDATE producto p JOIN modelo m ON p.modelo = m.id SET p.activo = 0 WHERE m.proveedor = ?', [$proveedor->getId()]);

        $preciosMap = [];
        foreach ($preciosApi['pricelist'] as $precio) {
            $preciosMap[$precio['productcode']] = $precio;
        }

        foreach ($modelosApi["item"] as $row) {
            $lastProcessedRow = $row;
            if (empty($row["modelname"])) continue;

            try {
                // --- Modelo ---
                $modeloReferencia = $row["modelcode"];
//                if ($modeloReferencia == "CA1205"){
//                    var_dump($row);
//                }
                $modelo = $em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloReferencia]) ?? new Modelo();

                $modelo->setActivo(true);
                $modelo->setProveedor($proveedor);
                $modelo->setFabricante($fabricante);
                $modelo->setReferencia($modeloReferencia);
                $modelo->setNombre($row["modelname"]);
                $modelo->setComposicion($row["composition"] ?? null);
                $modelo->setObservaciones($row["observations"] ?? null);
                $modelo->setUrlImage($row["modelimage"] === "---" ? ($row["childimage"] ?? null) : ($row["modelimage"] ?? null));
                $modelo->setChildImage($row["childimage"] ?? null);
                $modelo->setDetailsImages($row["detailsimages"] ?? null);
                $modelo->setOtherImages($row["otherimages"] ?? null);
                $modelo->setUrlFichaTecnica("https://static.gorfactory.es/b2b/Ficha_Tecnica/es/pdf/{$modeloReferencia}_es.pdf");
                $modelo->setIsNovelty(($row["isnovelty"] ?? 'False') !== 'False');
                $modelo->setPack($row["packunits"] ?? null);
                $modelo->setBox($row["boxunits"] ?? null);
                $modelo->setDescripcion($row["description"] ?? null);


                // ¡CAMBIO! Usamos el Slugger de Symfony
                if ($modelo->getNombreUrl() === null) {
                    if ($modelo->getFamilia() !== null) {
                        $slugText = $modelo->getFabricante()->getNombre() . "-" . $modelo->getFamilia()->getNombreUrl() . "-" . $modelo->getNombre();
                    } else {
                        $slugText = $modelo->getFabricante()->getNombre() . "-" . $modelo->getNombre();
                    }
                    $modelo->setNombreUrl($this->slugger->slug($slugText)->lower());
                }

                // --- Familia (lógica original) ---
                if (!empty($row["family"])) {
                    $familiaNombre = $row["family"];
                    // ¡CAMBIO! Usamos el Slugger de Symfony
                    $familiaID = $nombreProveedor . "-" . $this->slugger->slug($familiaNombre)->lower();
                    $familia = $em->getRepository(Familia::class)->findOneBy(['id' => $familiaID]);

                    if ($familia == null) {
                        $familia = new Familia();
                        $familia->setId($familiaID);
                        $familia->setNombre($familiaNombre);
//                        $familia->setNombreOld($familiaNombre);
//                        $familia->setNombreUrlFromNombre($nombreProveedor . "-" . $familiaNombre);
                        // #################################################
                        // # CORRECCIÓN 2: Usar $this->slugger aquí
                        // #################################################
                        $familiaSlug = $this->slugger->slug($nombreProveedor . "-" . $familiaNombre)->lower();
                        $familia->setNombreUrl($familiaSlug); // Usamos el setter directo
                        $familia->setProveedor($proveedor);
                        $em->persist($familia);
                    }
                    $familia->setMarca($fabricante);
//                    $familia->addModelo($modelo);
                    $familia->addModelosOneToMany($modelo);
                    $modelo->setFamilia($familia);
                    $em->persist($familia);
                }

                // --- Género ---
                if (!empty($row["gender"])) {
                    $genero = $em->getRepository(Genero::class)->findOneBy(['nombre' => $row["gender"]]) ?? new Genero();
                    if (!$genero->getId()) {
                        $genero->setNombre($row["gender"]);
                        $em->persist($genero);
                    }
                    $modelo->setGender($genero);
                }

                // --- Producto ---
                $productoReferencia = $row["itemcode"];
                $producto = $em->getRepository(Producto::class)->findOneBy(['referencia' => $productoReferencia]) ?? new Producto();

                $producto->setReferencia($productoReferencia);
                $producto->setMedidas($row["measures"] ?? null);
                $producto->setTalla($row["sizename"] ?? null);
                $producto->setUrlImage($row["productimage"] ?? null);
                $producto->setViewsImages($row["viewsimages"] ?? null);
                $producto->setActivo(true);
                $producto->setModelo($modelo);

                // --- Color ---
                if (!empty($row["colorname"])) {
                    // ¡CAMBIO! Usamos el Slugger de Symfony
                    $colorId = $nombreProveedor . "-" . $this->slugger->slug($row["colorname"])->lower();
                    $color = $em->getRepository(Color::class)->findOneBy(['id' => $colorId]) ?? new Color();
                    if (!$color->getId()) {
                        $color->setId($colorId);
                        $color->setNombre($row["colorname"]);
                        $color->setProveedor($proveedor);
                        $em->persist($color);
                    }
                    $producto->setColor($color);
                }

                // --- Precios ---
                if (isset($preciosMap[$productoReferencia])) {
                    $precioInfo = $preciosMap[$productoReferencia];
                    $producto->setPrecioUnidad((float)$precioInfo["price_unit"]);
                    $producto->setPrecioPack((float)$precioInfo["price_pack"]);
                    $producto->setPrecioCaja((float)$precioInfo["price_box"]);
                }

                $em->persist($modelo);
                $em->persist($producto);

                $em->flush();
                $em->clear();

                $proveedor = $em->find(Proveedor::class, $proveedor->getId());
                $fabricante = $em->find(Fabricante::class, $fabricante->getId());

            } catch (\Exception $e) {
                throw $e;
            }
        }
    }

    private function ajustaPrecios(OutputInterface $output)
    {
        $output->writeln('5. Ajustando precios mínimos de los modelos...');
        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $this->fabricanteParam]);
        if ($proveedor) {
            $modelos = $this->em->getRepository(Modelo::class)->findBy(['proveedor' => $proveedor]);
            foreach ($modelos as $modelo) {
                $modelo->setPrecioMin($modelo->getPrecioUnidad());
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
                echo($modelo->getPrecioUnidad() . "\n");
            }
            $this->em->flush();
            $this->em->clear();
        }
    }

    // private function clearModelsCache(...) // <-- FUNCIÓN ELIMINADA

    private function authenticate()
    {
        $response = $this->apiRequest('/v1.0/login', 'POST', 'password=' . self::PASSWORD . '&username=' . self::USERNAME);
        $this->token = $response['token'];
    }

    private function fetchCatalog()
    {
        return $this->apiRequest('/v2.3/item/getcatalog?lang=es-ES&brand=' . $this->fabricanteParam);
    }

    private function fetchPrices()
    {
        return $this->apiRequest('/v1.0/item/pricelist', 'POST', 'brand=' . $this->fabricanteParam);
    }

    private function apiRequest(string $endpoint, string $method = 'GET', $payload = null)
    {
        // (Esta función de cURL no necesita cambios)
        $url = self::API_URL . $endpoint;
        $ch = curl_init($url);

        $headers = ['Authorization: Bearer ' . $this->token];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } else {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $result === false) {
            throw new \Exception("Error en la petición a {$url}. Código HTTP: {$httpCode}. Respuesta: {$result}");
        }

        return json_decode($result, true);
    }
}