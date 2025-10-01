<?php
// src/Service/MrwApiService.php

namespace App\Service;

use App\Entity\Pedido;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class MrwApiService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private string $wsdlUrl,
        private string $franquicia,
        private string $abonado,
        private string $usuario,
        private string $password,
        private string $departamento = '' // Se añade el departamento con un valor por defecto
    ) {
    }

    public function documentarEnvio(Pedido $pedido, int $bultos, string $codigoServicio): ?string
    {
        // 1. Extraer los datos de la dirección y el contacto del pedido
        $direccionEnvio = $pedido->getDireccion();
        $contacto = $pedido->getContacto();

        if (!$direccionEnvio || !$contacto) {
            $this->logger->error('El pedido ' . $pedido->getId() . ' no tiene una dirección o contacto asignado.');
            return null;
        }

        $nombre = $direccionEnvio->getNombre() ?: ($contacto->getNombre() . ' ' . $contacto->getApellidos());
        $direccion = $direccionEnvio->getDir();
        $cp = $direccionEnvio->getCp();
        $poblacion = $direccionEnvio->getPoblacion();
        $telefono = $direccionEnvio->getTelefonoMovil() ?: $contacto->getTelefonoMovil();
        $dni = $contacto->getCif();
        $email = $contacto->getUsuario()->getEmail();

        // 2. Crear el cliente SOAP
        try {
            $clientMRW = new \SoapClient($this->wsdlUrl, ['trace' => true]);
        } catch (\SoapFault $e) {
            $this->logger->error('Error creando el cliente SOAP de MRW: ' . $e->getMessage());
            return null;
        }

        // 3. Preparar los parámetros para la API de MRW
        $hoy = date("d/m/Y");
        $entregaSabados = ($codigoServicio === "0015") ? "S" : "N";

        $params = [
            'request' => [
                'DatosEntrega' => [
                    'Direccion' => [
                        'Via' => substr($direccion, 0, 80),
                        'CodigoPostal' => $cp,
                        'Poblacion' => $poblacion,
                        'CodigoPais' => 'ESP'
                    ],
                    'Nif' => $dni,
                    'Nombre' => substr($nombre, 0, 50),
                    'Telefono' => $telefono,
                ],
                'DatosServicio' => [
                    'Fecha' => $hoy,
                    'Referencia' => (string)$pedido,
                    'EnFranquicia' => 'N',
                    'CodigoServicio' => $codigoServicio,
                    'NumeroBultos' => $bultos,
                    'Peso' => '1',
                    'EntregaSabado' => $entregaSabados,
                    'Notificaciones' => [
                        'NotificacionRequest' => [
                            ['CanalNotificacion' => '1', 'TipoNotificacion' => '2', 'MailSMS' => $email],
                            ['CanalNotificacion' => '2', 'TipoNotificacion' => '4', 'MailSMS' => $telefono]
                        ]
                    ],
                    'TramoHorario' => '0',
                ]
            ]
        ];

        // 4. Preparar las cabeceras de autenticación
        $cabeceras = [
            'CodigoFranquicia' => $this->franquicia,
            'CodigoAbonado' => $this->abonado,
            'CodigoDepartamento' => $this->departamento,
            'UserName' => $this->usuario,
            'Password' => $this->password
        ];
        $header = new \SoapHeader('http://www.mrw.es/', 'AuthInfo', $cabeceras);
        $clientMRW->__setSoapHeaders($header);

        // 5. Llamar a la API y procesar la respuesta
        try {
            $response = $clientMRW->TransmEnvio($params);

            if (isset($response->TransmEnvioResult) && $response->TransmEnvioResult->Estado == 1) {
                // Éxito: se ha generado la etiqueta
                $numeroEnvio = $response->TransmEnvioResult->NumeroEnvio;
                $urlSeguimiento = "https://www.mrw.es/seguimiento_envios/MRW_historico_nacional.asp?enviament=" . $numeroEnvio;

                $pedido->setSeguimientoEnvio($urlSeguimiento);
                $this->em->flush();

                $this->logger->info('Envío documentado con MRW para el pedido ' . $pedido->getId() . '. Número de envío: ' . $numeroEnvio);
                return $urlSeguimiento;

            } else {
                // Error: la API de MRW devolvió un error
                $errorMessage = $response->TransmEnvioResult->Mensaje ?? 'Error desconocido de MRW.';
                $this->logger->error('Error de la API de MRW para el pedido ' . $pedido->getId() . ': ' . $errorMessage);
                $this->logger->error('SOAP Request: ' . $clientMRW->__getLastRequest());
                return null;
            }
        } catch (\SoapFault $exception) {
            $this->logger->error('Excepción SOAP de MRW para el pedido ' . $pedido->getId() . ': ' . $exception->getMessage());
            $this->logger->error('SOAP Request: ' . $clientMRW->__getLastRequest());
            return null;
        }
    }

    /**
     * NUEVO MÉTODO: Obtiene el fichero PDF de la etiqueta de un envío ya documentado.
     */
    public function getEtiqueta(string $numeroEnvio): ?string
    {
        try {
            $clientMRW = new \SoapClient($this->wsdlUrl, ['trace' => true]);
        } catch (\SoapFault $e) {
            $this->logger->error('Error creando el cliente SOAP de MRW para obtener etiqueta: ' . $e->getMessage());
            return null;
        }

        $cabeceras = [
            'CodigoFranquicia' => $this->franquicia,
            'CodigoAbonado' => $this->abonado,
            'CodigoDepartamento' => $this->departamento,
            'UserName' => $this->usuario,
            'Password' => $this->password
        ];
        $header = new \SoapHeader('http://www.mrw.es/', 'AuthInfo', $cabeceras);
        $clientMRW->__setSoapHeaders($header);

        $params = [
            'request' => [
                'NumeroEnvio' => $numeroEnvio,
                'ReportTopMargin' => 1100,
                'ReportLeftMargin' => 650
            ]
        ];

        try {
            $response = $clientMRW->EtiquetaEnvio($params);
            $etiquetaPDF =$response->GetEtiquetaEnvioResult->EtiquetaFile;

            if (isset($etiquetaPDF)) {
                return $etiquetaPDF;
            }

            $this->logger->error('La respuesta de MRW para GetEtiquetaEnvio no contiene el fichero de la etiqueta.');
            return null;

        } catch (\SoapFault $exception) {
            $this->logger->error('Excepción SOAP al obtener etiqueta de MRW: ' . $exception->getMessage());
            return null;
        }
    }
}

