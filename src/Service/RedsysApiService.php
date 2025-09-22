<?php
// src/Service/RedsysApiService.php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\Pedido;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Servicio para gestionar la comunicación con la pasarela de pago Redsys.
 * Migración fiel de la antigua clase de utilidad RedsysAPI.
 */
class RedsysApiService
{
    private ?Empresa $empresaConfig;
    private array $vars_pay = [];

    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $router
    ) {
        $this->empresaConfig = $this->em->getRepository(Empresa::class)->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Prepara todos los datos necesarios para el formulario de Redsys.
     */
    public function generateRedsysFormData(Pedido $pedido): array
    {
        if (!$this->empresaConfig || !$pedido->getTotal() || !$this->empresaConfig->getMerchantCode() || !$this->empresaConfig->getMerchantId()) {
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
        $this->setParameter("DS_MERCHANT_MERCHANTCODE", $this->empresaConfig->getMerchantCode());
        $this->setParameter("DS_MERCHANT_CURRENCY", "978"); // EUR
        $this->setParameter("DS_MERCHANT_TRANSACTIONTYPE", "0");
        $this->setParameter("DS_MERCHANT_TERMINAL", "001");
        $this->setParameter("DS_MERCHANT_MERCHANTURL", $urlNotificacion);
        $this->setParameter("DS_MERCHANT_URLOK", $urlOK);
        $this->setParameter("DS_MERCHANT_URLKO", $urlKO);

        $version = "HMAC_SHA256_V1";
        $params = $this->createMerchantParameters();
        $signature = $this->createMerchantSignature($this->empresaConfig->getMerchantId());

        return [
            'Ds_SignatureVersion' => $version,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
            'redsys_url' => 'https://sis.redsys.es/sis/realizarPago'
        ];
    }

    // ===================================================================
    // MÉTODOS MIGRADOS FIELMENTE DE TU CLASE RedsysAPI
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
        $iv = "\0\0\0\0\0\0\0\0";
        $ciphertext = openssl_encrypt($message, 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $ciphertext;
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

    // --- Métodos para notificaciones (los migramos para tenerlos listos) ---

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

