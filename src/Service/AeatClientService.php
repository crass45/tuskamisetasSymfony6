<?php

namespace App\Service;

use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\Record;
use josemmo\Verifactu\Models\Responses\AeatResponse;
use josemmo\Verifactu\Services\AeatClient;

class AeatClientService
{
    private ComputerSystem $computerSystem;
    private FiscalIdentifier $taxpayer;
    private bool $isProduction;
    private string $certPath;
    private string $certPass;

    public function __construct(
        string $verifactuNif,
        string $verifactuSoftware,
        string $verifactuVersion,
        string $verifactuAeatCertPath,
        string $verifactuAeatCertPass,
        bool $verifactuIsProduction
    ) {
        $this->certPath = $verifactuAeatCertPath;
        $this->certPass = $verifactuAeatCertPass;
        $this->isProduction = $verifactuIsProduction;

        // Definimos el sistema informático (tu aplicación)

        $this->computerSystem = new ComputerSystem();
        $this->computerSystem->vendorNif = $verifactuNif;
        $this->computerSystem->vendorName = $verifactuSoftware;
        $this->computerSystem->name = $verifactuSoftware;
        $this->computerSystem->version = $verifactuVersion;
        $this->computerSystem->id = "10";//$verifactuSoftware; // <-- ¡AQUÍ ESTÁ LA CORRECCIÓN!
        $this->computerSystem->installationNumber = $verifactuVersion;

        // --- CORRECCIÓN ---
        // Inicializamos las propiedades booleanas obligatorias
        $this->computerSystem->onlySupportsVerifactu = true;     // Asumimos que tu sistema es solo para VeriFactu
        $this->computerSystem->supportsMultipleTaxpayers = false; // Tu software es solo para tu NIF
        $this->computerSystem->hasMultipleTaxpayers = false;      // No está siendo usado por múltiples NIFs

        // Definimos el contribuyente (tu empresa)
        $this->taxpayer = new FiscalIdentifier($verifactuSoftware, $verifactuNif);
    }

    /**
     * Envía un lote de registros a la AEAT.
     *
     * @param  Record[]     $records Array de objetos RegistrationRecord
     * @return AeatResponse La respuesta de la AEAT
     */
    public function sendRecords(array $records): AeatResponse
    {
        // 1. Creamos el cliente de la AEAT
        $client = new AeatClient($this->computerSystem, $this->taxpayer);

        // 2. --- ¡LÓGICA CORREGIDA PARA .PFX! ---
        // Le pasamos la ruta al .pfx y su contraseña,
        // tal como indica la firma del método que me mostraste.
        $client->setCertificate($this->certPath, $this->certPass);

        // 3. Configuramos el entorno de producción/pruebas
        $client->setProduction($this->isProduction);

        // 4. Enviamos los registros y esperamos la respuesta
        /** @var AeatResponse $response */
        $response = $client->send($records)->wait();

        return $response;
    }
}
