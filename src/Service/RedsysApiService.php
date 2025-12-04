<?php
// src/Service/RedsysApiService.php
// --- ESTE ES EL CÓDIGO CORREGIDO Y COMPLETO ---

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\Pedido;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Servicio para gestionar la comunicación con la pasarela de pago Redsys.
 */
class RedsysApiService
{
    private ?Empresa $empresaConfig = null; // Inicializado a null
    private array $vars_pay = [];

    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $router
    ) {
        // ¡NO HACEMOS NINGUNA CONSULTA AQUÍ!
        // El constructor se deja vacío para evitar errores en comandos de consola.
    }

    /**
     * Carga la configuración de la BBDD de forma perezosa (lazy-load)
     * solo cuando se necesita, y NUNCA desde la consola.
     */
    private function getConfig(): ?Empresa
    {
        // --- INICIO DEL "CORTAFUEGOS" PARA LA CONSOLA ---
        // Si el código se está ejecutando desde un comando de consola (cli)
        if (php_sapi_name() === 'cli') {
            // No ejecutes ninguna consulta y devuelve null.
            // Esto evita el error "Unknown column" durante las migraciones.
            return null;
        }
        // --- FIN DEL CORTAFUEGOS ---

        // Si ya la hemos cargado antes, la devolvemos.
        if ($this->empresaConfig !== null) {
            return $this->empresaConfig;
        }

        // Si no, esta es la PRIMERA VEZ que la pedimos (desde la web).
        // Hacemos la consulta de la BBDD aquí.
        $this->empresaConfig = $this->em->getRepository(Empresa::class)->findOneBy([], ['id' => 'DESC']);
        return $this->empresaConfig;
    }

    /**
     * Prepara todos los datos necesarios para el formulario de Redsys.
     */
    public function generateRedsysFormData(Pedido $pedido): array
    {
        // Usamos el getter en lugar de la propiedad directamente
        $config = $this->getConfig();

        // Comprobamos si la configuración se pudo cargar (no será null si no estamos en 'cli')
        if (!$config || !$pedido->getTotal() || !$config->getMerchantCode() || !$config->getMerchantId()) {
            return [];
        }

        $fecha = new \DateTime();
        $idPedidoSermepa = $pedido->getId() . "-" . $fecha->format('is');

        $pedido->setCodigoSermepa($idPedidoSermepa);
        $this->em->flush();

        $urlNotificacion = $this->router->generate('app_payment_notification', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $urlOK = $this->router->generate('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $urlKO = $this->router->generate('app_payment_error', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->setParameter("DS_MERCHANT_AMOUNT", (int)(($pedido->getTotal() - $pedido->getCantidadPagada()) * 100));
        $this->setParameter("DS_MERCHANT_ORDER", $idPedidoSermepa);
        $this->setParameter("DS_MERCHANT_MERCHANTCODE", $config->getMerchantCode());
        $this->setParameter("DS_MERCHANT_CURRENCY", "978"); // EUR

        // --- INICIO DE LA CORRECCIÓN ---
        $this->setParameter("DS_MERCHANT_TRANSACTIONTYPE", "0");
        $this->setParameter("DS_MERCHANT_TERMINAL", "001");
        $this->setParameter("DS_MERCHANT_MERCHANTURL", $urlNotificacion);
        $this->setParameter("DS_MERCHANT_URLOK", $urlOK);
        $this->setParameter("DS_MERCHANT_URLKO", $urlKO);
        // --- FIN DE LA CORRECCIÓN ---

        $version = "HMAC_SHA256_V1";
        $params = $this->createMerchantParameters(); // Corregido aquí también
        $signature = $this->createMerchantSignature($config->getMerchantId()); // Corregido aquí también

        // Decidimos la URL basándonos en el nuevo campo de la BBDD
        $redsysUrl = $config->getModoPruebas()
            ? 'https://sis-t.redsys.es/sis/realizarPago'  // URL de PRUEBAS
            : 'https://sis.redsys.es/sis/realizarPago'; // URL de PRODUCCIÓN

        return [
            'Ds_SignatureVersion' => $version,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
            'redsys_url' => $redsysUrl
        ];
    }

    /**
     * Devuelve la clave secreta (MerchantId) cargada desde la entidad Empresa.
     * (Lo necesitará el PaymentController)
     */
    public function getSecretKey(): ?string
    {
        // Usamos el getter aquí también
        return $this->getConfig()?->getMerchantId();
    }

    // ===================================================================
    // RESTO DE MÉTODOS (sin cambios)
    // ===================================================================

    private function setParameter($key, $value): void
    {
        $this->vars_pay[$key] = $value;
    }

    private function getParameter($key)
    {
        return $this->vars_pay[$key] ?? null;
    }

    private function getOrder(): ?string
    {
        return $this->vars_pay['DS_MERCHANT_ORDER'] ?? $this->vars_pay['Ds_Merchant_Order'] ?? null;
    }

    private function arrayToJson(): string
    {
        return json_encode($this->vars_pay);
    }

    private function createMerchantParameters(): string
    {
        $json = $this->arrayToJson();
        return $this->encodeBase64($json);
    }

    private function createMerchantSignature(string $key): string
    {
        $key = $this->decodeBase64($key);
        $ent = $this->createMerchantParameters();
        $key = $this->encrypt_3DES($this->getOrder(), $key);
        $res = $this->mac256($ent, $key);
        return $this->encodeBase64($res);
    }

    private function encrypt_3DES(string $message, string $key): string
    {
        // LÓGICA DE COMPATIBILIDAD CON MCRYPT (Zero Padding)
        // Forzamos que la longitud sea múltiplo de 8 bytes rellenando con ceros (\0)
        $l = ceil(strlen($message) / 8) * 8;
        $message = $message . str_repeat("\0", $l - strlen($message));

        $iv = "\0\0\0\0\0\0\0\0";

        // Usamos OPENSSL_RAW_DATA | OPENSSL_NO_PADDING para que OpenSSL no añada su propio relleno
        return openssl_encrypt($message, 'des-ede3-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
    }

    private function mac256(string $ent, string $key): string
    {
        return hash_hmac('sha256', $ent, $key, true);
    }

    private function encodeBase64(string $data): string
    {
        return base64_encode($data);
    }

    private function decodeBase64(string $data): string
    {
        return base64_decode($data);
    }

    // --- Métodos para notificaciones ---

    public function decodeMerchantParameters(string $data): ?array
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'));
        if ($decoded) {
            $this->vars_pay = json_decode($decoded, true);
            return $this->vars_pay;
        }
        return null;
    }

    private function getOrderNotif(): ?string
    {
        return $this->vars_pay['Ds_Order'] ?? $this->vars_pay['DS_ORDER'] ?? null;
    }

    public function createMerchantSignatureNotif(string $key, string $datos): string
    {
        $key = $this->decodeBase64($key);
        $this->decodeMerchantParameters($datos);
        $key = $this->encrypt_3DES($this->getOrderNotif(), $key);
        $res = $this->mac256($datos, $key);
        return strtr(base64_encode($res), '+/', '-_');
    }
}