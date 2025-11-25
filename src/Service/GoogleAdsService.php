<?php
// src/Service/GoogleAnalyticsService.php

namespace App\Service;

use App\Entity\Pedido;
use Symfony\Contracts\HttpClient\HttpClientInterface;



//[GOOGLE_ADS]
//developerToken = "TU_TOKEN_DE_DESARROLLADOR"
//clientCustomerId = "TU_CUSTOMER_ID_DE_GOOGLE_ADS"
//
//[OAUTH2]
//clientId = "1025565949258-sl662m9sjoljgte9jjj1mjr7dp95f6ra.apps.googleusercontent.com"
//clientSecret = "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"
//refreshToken = "TU_REFRESH_TOKEN"
//
//[LOGGING]
//logFilePath = "/var/log/google-ads.log"
//logLevel = "INFO"
/**
 * Servicio para enviar eventos al Measurement Protocol de Google Analytics 4.
 */
class GoogleAdsService
{
    private const GA_ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $measurementId, // Se inyectará desde services.yaml
        private string $apiSecret      // Se inyectará desde services.yaml
    ) {
    }

    public function sendPurchaseEvent(Pedido $pedido): void
    {
        $clientId = $pedido->getGoogleClientId();
//        if (!$clientId) {
//            // No podemos enviar el evento si no tenemos el Client ID
//            return;
//        }

        $items = [];
        foreach ($pedido->getLineas() as $linea) {
            $items[] = [
                'item_id' => $linea->getProducto()->getModelo()->getReferencia(),
                'item_name' => $linea->getProducto()->getModelo()->getNombre(),
                'item_brand' => $linea->getProducto()->getModelo()->getFabricante()->getNombre(),
                'item_variant' => $linea->getProducto()->getReferencia(),
                'price' => $linea->getPrecio(),
                'quantity' => $linea->getCantidad(),
            ];
        }

        $payload = [
            'client_id' => $clientId,
            'events' => [
                [
                    'name' => 'purchase',
                    'params' => [
                        'transaction_id' => $pedido->getNombre(),
                        'value' => $pedido->getTotal(),
                        'shipping' => $pedido->getEnvio(),
                        'tax' => $pedido->getIva(),
                        'currency' => 'EUR',
                        'items' => $items,
                        'gclid' => $pedido->getGclid(),
                        'gbraid' => $pedido->getGbraid(),
                        'wbraid' => $pedido->getWbraid(),
                    ],
                ],
            ],
        ];

        $this->httpClient->request('POST', self::GA_ENDPOINT, [
            'query' => [
                'measurement_id' => $this->measurementId,
                'api_secret' => $this->apiSecret,
            ],
            'json' => $payload,
        ]);
    }
}
