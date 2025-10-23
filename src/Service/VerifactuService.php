<?php

namespace App\Service;

use App\Entity\Factura;
use App\Entity\FacturaRectificativa;
use App\Repository\FacturaRepository;
use DateTimeImmutable;


use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\CorrectiveType;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceType;
use josemmo\Verifactu\Models\Records\OperationType;
use josemmo\Verifactu\Models\Records\RegimeType;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Records\TaxType;
use josemmo\Verifactu\Services\QrGenerator;


class VerifactuService
{
    private string $issuerNif;
    private string $issuerName;
    private QrGenerator $qrGenerator;

    private $produccion = false;

    public function __construct(string $verifactuNif, string $verifactuSoftware)
    {
        $this->issuerNif = $verifactuNif;
        // El nombre de tu software se usa como el nombre del emisor para el registro
        $this->issuerName = $verifactuSoftware;
        // CORRECTO: Se instancia QrGenerator y luego se configura el entorno.
        $this->qrGenerator = new QrGenerator();
        $this->qrGenerator->setProduction($this->produccion);
        $this->qrGenerator->setOnlineMode(true); // Usamos el modo SIF (no VeriFactu en tiempo real)
    }

    /**
     * Crea el objeto RegistrationRecord para una factura, listo para ser hasheado.
     * @param  Factura         $factura      La factura a registrar.
     * @param  string|null     $previousHash El hash de la factura anterior para encadenamiento.
     * @return RegistrationRecord El objeto de registro listo.
     */
    public function createRegistrationRecord(Factura $factura, ?array $previousFactura): RegistrationRecord
    {
        $pedido = $factura->getPedido();
        if (!$pedido) {
            // Esta excepción parará el proceso y se registrará en los logs.
            throw new \LogicException('La factura con ID ' . $factura->getId() . ' no tiene un pedido asociado.');
        }

        $record = new RegistrationRecord();

        // --- Cabecera de Factura ---
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = $this->issuerNif;
        $record->invoiceId->invoiceNumber = $factura->getNombre(); // ej: "FW24/00001"
        $record->invoiceId->issueDate = $factura->getFecha();

        $record->issuerName = $this->issuerName;
        $record->invoiceType = InvoiceType::Factura;
        $record->description = 'Factura de venta';

        // --- Tipo de Factura y Datos del Cliente (Lógica mejorada) ---
        $cif = $factura->getCif() ? trim($factura->getCif()) : '';

        // Si el cliente tiene un NIF/CIF válido de 9 caracteres, es una factura completa.
        if (strlen($cif) === 9) {
            $record->invoiceType = InvoiceType::Factura;
            $recipient = new FiscalIdentifier($factura->getRazonSocial(), $cif);
            $record->recipients[] = $recipient;
        } else {
            // Si no, es una factura simplificada y no requiere identificar al destinatario.
            $record->invoiceType = InvoiceType::Simplificada;
        }


        // --- Desglose de Impuestos (Lógica para exenciones de IVA) ---
        $baseAmount = $factura->getBaseImponible();
        $ivaAmount = $factura->getImporteIva();
        $contacto = $pedido->getContacto();
        $provinciaDestino = $factura->getProvincia();

        // Comprobamos si la operación está exenta de IVA
        $isExempt = false;
        if ($contacto && $contacto->isIntracomunitario()) {
            $isExempt = true;
        }
        if (in_array($provinciaDestino, ['Las Palmas', 'Santa Cruz de Tenerife', 'Ceuta', 'Melilla'])) {
            $isExempt = true;
        }

        // La librería espera al menos un desglose si hay importe
        if ($baseAmount > 0) {
            $breakdown = new BreakdownDetails();
            $breakdown->taxType = TaxType::IVA;
            $breakdown->regimeType = RegimeType::C01;

            if ($isExempt) {
                // Operación sujeta pero exenta
                $breakdown->operationType = OperationType::NonSubject;
                $breakdown->baseAmount = sprintf('%.2f', $baseAmount);
//                $breakdown->taxRate = '0.00';
//                $breakdown->taxAmount = '0.00';
            } else {
                // Operación sujeta y no exenta (con IVA)
                $breakdown->operationType = OperationType::Subject;
                $breakdown->baseAmount = sprintf('%.2f', $baseAmount);
                $breakdown->taxRate = "21.00";
                $breakdown->taxAmount = sprintf('%.2f', $ivaAmount);
                if ($contacto && $contacto->isRecargoEquivalencia()) {
                    $breakdown->regimeType= RegimeType::C18;
                }
            }
            $record->breakdown[] = $breakdown;
        }
//        // --- Añadir Recargo de Equivalencia si aplica ---
//        $recargoAmount = $pedido->getRecargoEquivalencia();
//        if ($contacto && $contacto->isRecargoEquivalencia() && $recargoAmount > 0) {
//            $breakdownRE = new BreakdownDetails();
//            $breakdownRE->taxType = TaxType::IVA;
//            $breakdownRE->regimeType = RegimeType::C18; // Régimen especial C18 creo que es RE
//            $breakdownRE->operationType = OperationType::Subject;
//
//            $breakdownRE->baseAmount = sprintf('%.2f', $baseAmount);
//            // Calculamos el tipo de recargo
////            $recargoRate = ($recargoAmount / $baseAmount) * 100;
//            $breakdownRE->taxRate = "5.20";//21 de iva + 5,2 de Recargo de equivalencia, creo que es así
//            $breakdownRE->taxAmount = sprintf('%.2f', $ivaAmount);
//            $record->breakdown[] = $breakdownRE;
//        }

        // --- Totales (SIMPLIFICADO) ---
        $record->totalTaxAmount = sprintf('%.2f', $factura->getImporteIva()+$factura->getImporteRecargoEquivalencia());
        $record->totalAmount = sprintf('%.2f', $factura->getTotal());


        // --- Encadenamiento ---
        if ($previousFactura) {
            $record->previousHash = $previousFactura['hash'];
            $record->previousInvoiceId = new InvoiceIdentifier();
            $record->previousInvoiceId->issuerId = $this->issuerNif;
            $record->previousInvoiceId->invoiceNumber = $previousFactura['number'];
            $record->previousInvoiceId->issueDate = new \DateTimeImmutable($previousFactura['date']);
        } else {
            $record->previousHash = null;
            $record->previousInvoiceId = null;
        }

        // --- Cálculo del Hash ---
        $record->hashedAt = new DateTimeImmutable();
        $record->hash = $record->calculateHash();
//        $record->validate();

        return $record;
    }

    /**
     * Crea el objeto RegistrationRecord para una factura rectificativa.
     */
    public function createCreditNoteRecord(FacturaRectificativa $rectificativa, ?array $previousFactura): RegistrationRecord
    {
        $record = new RegistrationRecord();
        $facturaOriginal = $rectificativa->getFacturaPadre();
        $pedido = $facturaOriginal->getPedido();

        // --- Cabecera de Factura Rectificativa ---
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = $this->issuerNif;
        $record->invoiceId->invoiceNumber = $rectificativa->getNumeroFactura();
        $record->invoiceId->issueDate = $rectificativa->getFecha();

        $record->issuerName = $this->issuerName;
        $record->invoiceType = InvoiceType::R1;
        $record->description = 'Factura rectificativa';

        // --- Datos del Cliente (se heredan de la factura original) ---
//        $recipient = new FiscalIdentifier(
//            $facturaOriginal->getRazonSocial(),
//            $facturaOriginal->getCif() ?? 'SIN_NIF'
//        );
//        $record->recipients[] = $recipient;
        // Si el cliente tiene un NIF/CIF válido de 9 caracteres, es una factura completa.
        if (strlen($facturaOriginal->getCif()) === 9) {
            $record->invoiceType = InvoiceType::R1;
            $recipient = new FiscalIdentifier($facturaOriginal->getRazonSocial(), $facturaOriginal->getCif());
            $record->recipients[] = $recipient;
        } else {
            // Si no, es una factura simplificada y no requiere identificar al destinatario.
            $record->invoiceType = InvoiceType::Simplificada;
        }

        // --- BLOQUE DE CORRECCIÓN (CORREGIDO) ---
        // Se asigna a las propiedades directas del registro
        $record->correctiveType = CorrectiveType::Differences;
        $record->correctedInvoiceId = new InvoiceIdentifier();
        $record->correctedInvoiceId->issuerId = $this->issuerNif;
        $record->correctedInvoiceId->invoiceNumber = $facturaOriginal->getNombre();
        $record->correctedInvoiceId->issueDate = $facturaOriginal->getFecha();

        // --- Desglose (importes en negativo) ---
        $baseImponible = $rectificativa->getBaseImponible(); // Ya es negativo
        $importeIva = $rectificativa->getImporteIva();     // Ya es negativo

        if ($baseImponible < 0) {
            $breakdown = new BreakdownDetails();
            $breakdown->taxType = TaxType::IVA;
            $breakdown->regimeType = RegimeType::C01;
            $breakdown->operationType = OperationType::Subject;
            $breakdown->baseAmount = sprintf('%.2f', $baseImponible);
            $breakdown->taxRate = "21.00";
            $breakdown->taxAmount = sprintf('%.2f', $importeIva);
            if ($facturaOriginal->getPedido()->getContacto() && $facturaOriginal->getPedido()->getContacto()->isRecargoEquivalencia()) {
                $breakdown->regimeType= RegimeType::C18;
                $breakdown->taxRate = "26.20";

            }
            $record->breakdown[] = $breakdown;
        }

        // --- Totales (en negativo) ---
        $record->totalTaxAmount = sprintf('%.2f', $importeIva);
        $record->totalAmount = sprintf('%.2f', $rectificativa->getTotal());

        // --- Encadenamiento ---
        if ($previousFactura) {
            $record->previousHash = $previousFactura['hash'];
            $record->previousInvoiceId = new InvoiceIdentifier();
            $record->previousInvoiceId->issuerId = $this->issuerNif;
            $record->previousInvoiceId->invoiceNumber = $previousFactura['number'];
            $record->previousInvoiceId->issueDate = new \DateTimeImmutable($previousFactura['date']);
        } else {
            $record->previousHash = null;
            $record->previousInvoiceId = null;
        }

//        $record->previousHash = $previousHash;

        // --- Cálculo del Hash ---
        $record->hashedAt = new DateTimeImmutable();
        $record->hash = $record->calculateHash();

        $record->validate();
        return $record;
    }

    /**
     * Genera el contenido del QR para un registro de facturación.
     * @param  RegistrationRecord $record El registro de facturación.
     * @return string             El contenido para el QR.
     */
    public function getQrContent(RegistrationRecord $record): string
    {
        // CORRECTO: Usamos el método `fromRegistrationRecord` que espera el objeto completo.
        return $this->qrGenerator->fromRegistrationRecord($record);
    }
}

