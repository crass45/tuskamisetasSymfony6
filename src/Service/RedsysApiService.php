<?php
// src/Service/RedsysApiService.php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\Pedido;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Servicio para gestionar la comunicación con la pasarela de pago Redsys.
 * Migración de la antigua clase de utilidad RedsysAPI.
 */
class RedsysApiService
{
    private array $parameters = [];
    private ?Empresa $empresaConfig;

    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $router
    ) {
        $this->empresaConfig = $this->em->getRepository(Empresa::class)->findOneBy([], ['id' => 'DESC']);
    }

    public function generateRedsysFormData(Pedido $pedido): array
    {
        if (!$this->empresaConfig || !$pedido->getTotal()) {
            return [];
        }

        $fuc = $this->empresaConfig->getMerchantCode();
        $terminal = "001";
        $moneda = "978"; // EUR
        $trans = "0"; // Autorización
        $fecha = new \DateTime();
        $idPedidoSermepa = $pedido->getId() . "-" . $fecha->format('is');

        // Guardamos este código en el pedido para poder encontrarlo en la respuesta del banco
        $pedido->setCodigoSermepa($idPedidoSermepa);
        $this->em->flush();

        // URLs de notificación y de retorno
        $urlNotificacion = $this->router->generate('app_payment_notification', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $urlOK = $this->router->generate('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $urlKO = $this->router->generate('app_payment_error', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->setParameter("DS_MERCHANT_AMOUNT", (int)(($pedido->getTotal() - $pedido->getCantidadPagada()) * 100));
        $this->setParameter("DS_MERCHANT_ORDER", $idPedidoSermepa);
        $this->setParameter("DS_MERCHANT_MERCHANTCODE", $fuc);
        $this->setParameter("DS_MERCHANT_CURRENCY", $moneda);
        $this->setParameter("DS_MERCHANT_TRANSACTIONTYPE", $trans);
        $this->setParameter("DS_MERCHANT_TERMINAL", $terminal);
        $this->setParameter("DS_MERCHANT_MERCHANTURL", $urlNotificacion);
        $this->setParameter("DS_MERCHANT_URLOK", $urlOK);
        $this->setParameter("DS_MERCHANT_URLKO", $urlKO);

        $version = "HMAC_SHA256_V1";
        $kc = $this->empresaConfig->getMerchantId();
        $params = $this->createMerchantParameters();
        $signature = $this->createMerchantSignature($kc);

        return [
            'Ds_SignatureVersion' => $version,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
            // URL del TPV (debería estar en tu .env)
            'redsys_url' => 'https://sis.redsys.es/sis/realizarPago' // URL de producción. Usa la de test si es necesario.
        ];
    }

    // --- Métodos de ayuda (migrados de tu clase original) ---
    private function setParameter($key, $value): void { $this->parameters[$key] = $value; }
    private function createMerchantParameters(): string { return base64_encode(json_encode($this->parameters)); }
    private function createMerchantSignature($key): string { /* ... tu lógica de firma HMAC256 ... */ return ''; }
    public function decodeMerchantParameters($data): array { return json_decode(base64_decode($data), true); }
}
