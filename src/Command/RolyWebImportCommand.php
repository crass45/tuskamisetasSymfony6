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
    name: 'ss:import_command_roly_web',
    description: 'Importa Roly/Stamina localizando imágenes en WebP y detectando videos con autorreparación de colores.'
)]
class RolyWebImportCommand extends Command
{
    private const API_URL = "https://clientsws.gorfactory.es:2096/api";
    private const USERNAME = "tuskamisetas@gmail.com";
    private const PASSWORD = "hb2GxMgQ";

    private $token;
    private $fabricanteParam;
    private $em;

    public function __construct(
        private ManagerRegistry  $mr,
        private SluggerInterface $slugger,
        private ImageManager     $imageManager
    )
    {
        parent::__construct();
        $this->em = $mr->getManager();
    }

    protected function configure()
    {
        $this->addArgument('fabricante', InputArgument::REQUIRED, 'roly o stamina');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        $config = $this->em->getConnection()->getConfiguration();
        if (method_exists($config, 'setMiddlewares')) {
            $config->setMiddlewares([]);
        }

        $output->writeln('<info>--- INICIO DE IMPORTACIÓN ROLY (MODO ROBUSTO) ---</info>');
        $this->fabricanteParam = $input->getArgument('fabricante');

        if (!in_array($this->fabricanteParam, ['roly', 'stamina'])) {
            $output->writeln('<error>El fabricante debe ser "roly" o "stamina".</error>');
            return Command::FAILURE;
        }

        ini_set('memory_limit', '2048M');

        try {
            $this->authenticate();
            $modelosApi = $this->fetchCatalog();
            $preciosApi = $this->fetchPrices();

            if (empty($modelosApi['item'])) {
                $output->writeln('<comment>No hay datos en la API.</comment>');
                return Command::SUCCESS;
            }

            $this->processDataOneByOne($output, $modelosApi, $preciosApi);
            $this->ajustarPreciosFinales($output,2995);

        } catch (\Exception $e) {
            $output->writeln('<error>Error Crítico: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function processDataOneByOne(OutputInterface $output, array $modelosApi, array $preciosApi)
    {
        $nombreProveedor = ($this->fabricanteParam === 'stamina') ? 'Stamina' : 'Roly';
        $proveedor = $this->em->getRepository(Proveedor::class)->findOneBy(['nombre' => $nombreProveedor]);
        $fabricante = $this->em->getRepository(Fabricante::class)->findOneBy(['nombre' => $nombreProveedor]);

        $i = 0;
        $batchSize = 50;
        $modelosEnEsteLote = []; // Para evitar duplicados antes del flush

        // 1. Desactivación masiva (Optimizado)
        if ($proveedor) {
            $output->writeln("<comment>Desactivando productos antiguos...</comment>");
            $this->em->getConnection()->executeStatement(
                "UPDATE producto p JOIN modelo m ON p.modelo = m.id SET p.activo = 0 WHERE m.proveedor = ?",
                [$proveedor->getId()]
            );
            $this->em->getConnection()->executeStatement(
                "UPDATE modelo SET activo = 0 WHERE proveedor = ?",
                [$proveedor->getId()]
            );
        }

        foreach ($modelosApi["item"] as $row) {
            if (empty($row["modelname"])) continue;

            try {
                if (!$this->em->isOpen()) { $this->em = $this->mr->resetManager(); }

                $modeloRef = $row["modelcode"];

                // --- GESTIÓN DE MODELO (Evita duplicados) ---
                if (!isset($modelosEnEsteLote[$modeloRef])) {
                    $modelo = $this->em->getRepository(Modelo::class)->findOneBy(['referencia' => $modeloRef]) ?? new Modelo();
                    $modelo->setNombre($row["modelname"]);
                    $modelo->setReferencia($modeloRef);
                    $modelo->setActivo(true);
                    $modelo->setProveedor($this->em->find(Proveedor::class, $proveedor->getId()));
                    $modelo->setFabricante($this->em->find(Fabricante::class, $fabricante->getId()));

                    $slugModelo = $this->slugger->slug(strtolower($modelo->getNombre()))->lower();
                    if (method_exists($modelo, 'setNombreUrl')) $modelo->setNombreUrl($slugModelo);

                    if (!empty($row["modelimage"]) && $row["modelimage"] !== '---') {
                        $modelo->setUrlImage($this->imageManager->download($row["modelimage"], 'roly/modelos', $modeloRef));
                        // Solo intentamos detectar vídeo si el modelo aún no tiene uno asignado
                        if (null === $modelo->getUrlVideo()) {
                            $this->detectVideo($modelo, $row["modelimage"]);
                        }
                    }

                    $this->em->persist($modelo);
                    $modelosEnEsteLote[$modeloRef] = $modelo;
                }
                $modelo = $modelosEnEsteLote[$modeloRef];

                // --- GESTIÓN DE COLOR (ID por Nombre: Roly-gris-vigore) ---
                $colorNombre = $row["colorname"] ?? null;
                if ($colorNombre) {
                    $colorSlug = $this->slugger->slug(strtolower($colorNombre))->lower();
                    $colorId = $nombreProveedor . "-" . $colorSlug; // Resultado: Roly-gris-vigore

                    $color = $this->em->getRepository(Color::class)->find($colorId);
                    if (!$color) {
                        $color = new Color();
                        $color->setId($colorId);
                        $color->setNombre($colorNombre);
                        $color->setCodigoColor($row["colorcode"] ?? '00');
                        $color->setNombreUrl($colorSlug);
                        $color->setProveedor($this->em->find(Proveedor::class, $proveedor->getId()));
                        $color->setFabricante($this->em->find(Fabricante::class, $fabricante->getId()));
                        $this->em->persist($color);
                        $this->em->flush(); // Flush inmediato del color para evitar colisiones
                    }
                }

                // --- GESTIÓN DE PRODUCTO ---
                $prodRef = $row["itemcode"];
                $producto = $this->em->getRepository(Producto::class)->findOneBy(['referencia' => $prodRef]) ?? new Producto();
                $producto->setReferencia($prodRef);
                $producto->setTalla($row["sizename"] ?? null);
                $producto->setModelo($modelo);
                $producto->setActivo(true);
                if (isset($color)) $producto->setColor($color);

                $this->em->persist($producto);

                // --- BATCH PROCESSING ---
                if ((++$i % $batchSize) === 0) {
                    $this->em->flush();
                    $this->em->clear();
                    $modelosEnEsteLote = []; // Vaciamos el mapa para liberar RAM
                    $output->writeln("Procesados $i registros...");
                }

            } catch (\Exception $e) {
                $output->writeln("<error>Error en {$row['itemcode']}: " . $e->getMessage() . "</error>");
                $this->em->clear();
                $modelosEnEsteLote = [];
            }
        }
        $this->em->flush();
    }

    private function ajustarPreciosFinales(OutputInterface $output, int $proveedorId)
    {
        $output->writeln("AJUSTANDO PRECIOS MÍNIMOS DE MODELOS...");

        // 1. Obtener solo los IDs de los modelos activos para este proveedor
        // Lo hacemos con una query simple para no saturar la memoria
        $ids = $this->em->createQueryBuilder()
            ->select('m.id')
            ->from(Modelo::class, 'm')
            ->where('m.proveedor = :prov')
            ->andWhere('m.activo = :activo')
            ->setParameter('prov', $proveedorId)
            ->setParameter('activo', true)
            ->getQuery()
            ->getScalarResult();

        $countAjuste = 0;
        $batchSizeAjuste = 50; // Reducimos un poco el batch para asegurar cálculos correctos

        foreach ($ids as $row) {
            $modeloId = $row['id'];

            // Buscamos el modelo fresco en cada iteración
            $modeloAjuste = $this->em->find(Modelo::class, $modeloId);
            if (!$modeloAjuste) continue;

            try {
                // Calculamos el precio base
                $precioBase = $modeloAjuste->getPrecioUnidad();

                // Si el precio base es nulo o 0, intentamos buscar el primer producto con precio
                if (!$precioBase || $precioBase <= 0) {
                    foreach ($modeloAjuste->getProductos() as $prod) {
                        if ($prod->getPrecioUnidad() > 0) {
                            $precioBase = $prod->getPrecioUnidad();
                            break;
                        }
                    }
                }

                $modeloAjuste->setPrecioMin($precioBase ?? 0);

                // Lógica de precio mínimo adulto (escalados)
                $precio10k = $modeloAjuste->getPrecioCantidadBlancas(10000);
                $precio10kNino = $modeloAjuste->getPrecioCantidadBlancasNino(10000);

                if ($precio10k > 0) {
                    $modeloAjuste->setPrecioMinAdulto($precio10k);
                } elseif ($precio10kNino > 0) {
                    $modeloAjuste->setPrecioMinAdulto($precio10kNino);
                } else {
                    $modeloAjuste->setPrecioMinAdulto($precioBase ?? 0);
                }

                // SEGURIDAD: Si después de todo sigue siendo 0, algo falla en los datos de Roly
                if ($modeloAjuste->getPrecioMin() <= 0) {
                    $output->writeln("<comment>Advertencia: Modelo {$modeloAjuste->getReferencia()} sigue con precio 0</comment>");
                }

                $countAjuste++;

                if ($countAjuste % $batchSizeAjuste === 0) {
                    $this->em->flush();
                    $this->em->clear();
                    $output->writeln("...precios mínimos ajustados: $countAjuste...");
                }
            } catch (\Exception $e) {
                $output->writeln('<error>Error en modelo ID ' . $modeloId . ': ' . $e->getMessage() . '</error>');
            }
        }

        $this->em->flush();
        $this->em->clear();
        $output->writeln("<info>AJUSTE DE PRECIOS MÍNIMOS TERMINADO ($countAjuste modelos).</info>");
    }


    private function detectVideo($modelo, $imgUrl)
    {
        $baseUrlVideo = str_replace('/model/', '/videos/', $imgUrl);
        if (preg_match('/(\d+)_(\d+)_(\d+)_(\d+)\.jpg$/i', $baseUrlVideo, $matches)) {
            $dir = substr($baseUrlVideo, 0, strrpos($baseUrlVideo, '/') + 1);
            $url = $dir . "{$matches[1]}_{$matches[2]}_5_1.mp4";
            if ($this->urlExists($url)) {
                $modelo->setUrlVideo($url);
            } else {
                $coloresCandidatos = ['229', '01', '02', '55', '58', '60', '05', '67', '48', '10', '12', '100', '15', '46'];
                foreach ($coloresCandidatos as $c) {
                    $urlAlt = $dir . "{$matches[1]}_{$c}_5_1.mp4";
                    if ($this->urlExists($urlAlt)) {
                        $modelo->setUrlVideo($urlAlt);
                        break;
                    }
                }
            }
        }
    }

    private function safeFlush(OutputInterface $output)
    {
        $retries = 3;
        while ($retries > 0) {
            try {
                if (!$this->em->isOpen()) $this->em = $this->mr->resetManager();
                $this->em->flush();
                return;
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'Deadlock') || str_contains($e->getMessage(), '1213')) {
                    $retries--;
                    sleep(2);
                    if ($retries === 0) throw $e;
                } else {
                    throw $e;
                }
            }
        }
    }

    // Funciones API (authenticate, fetchCatalog, fetchPrices, apiRequest, urlExists) se mantienen igual...
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
        $url = self::API_URL . $endpoint;
        $ch = curl_init($url);
        $headers = ['Authorization: Bearer ' . $this->token];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) throw new \Exception("Error API: $httpCode");
        return json_decode($result, true);
    }

    private function urlExists(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }
}