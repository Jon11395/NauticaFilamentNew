<?php

namespace App\Services\Receipts;

class XmlReceiptParser
{
    /**
     * @throws \RuntimeException
     */
    public function parse(string $payload): array
    {
        $payload = trim($payload);

        if ($payload === '') {
            throw new \RuntimeException('XML payload is empty.');
        }

        $document = $this->loadXml($payload);

        // Detect receipt type by checking the root element
        $receiptType = $this->detectReceiptType($document);

        $clave = $this->firstValue($document, 'Clave');
        $voucher = $this->firstValue($document, 'NumeroConsecutivo')
            ?? $clave;

        $issueDate = $this->firstValue($document, 'FechaEmision');
        $providerName = $this->firstValue($document, 'Emisor/Nombre');
        $providerTradeName = $this->firstValue($document, 'Emisor/NombreComercial');
        $providerIdentification = $this->firstValue($document, 'Emisor/Identificacion/Numero')
            ?? $this->firstValue($document, 'Emisor/Identificacion/IdentificacionExtranjero');
        $providerEmail = $this->firstValue($document, 'Emisor/CorreoElectronico');
        $providerPhone = $this->firstValue($document, 'Emisor/Telefono/NumTelefono');
        $providerPhoneCountry = $this->firstValue($document, 'Emisor/Telefono/CodigoPais');
        $providerLocation = $this->collectProviderLocation($document);
        $conceptLines = $this->collectConceptLines($document);

        $lineQuantities = $this->collectLineQuantities($document);

        $totalAmount = $this->firstValue($document, 'ResumenFactura/TotalComprobante')
            ?? $this->firstValue($document, 'ResumenFactura/TotalVentaNeta')
            ?? $this->firstValue($document, 'ResumenFactura/TotalVenta');

        $currency = $this->firstValue($document, 'ResumenFactura/CodigoMoneda');
        $saleCondition = $this->firstValue($document, 'CondicionVenta');

        $concepts = $this->collectConcepts($document);

        // Collect reference information if this is a credit note
        $referenceInfo = null;
        if ($receiptType === 'nota_credito') {
            $referenceInfo = $this->collectReferenceInfo($document);
        }

        return [
            'receipt_type' => $receiptType,
            'clave' => $clave,
            'voucher' => $voucher,
            'issue_date' => $issueDate,
            'provider_name' => $providerName,
            'provider_trade_name' => $providerTradeName,
            'provider_identification' => $providerIdentification,
            'provider_email' => $providerEmail,
            'provider_phone' => $providerPhone,
            'provider_phone_country' => $providerPhoneCountry,
            'provider_location' => $providerLocation,
            'concept_lines' => $conceptLines,
            'concept_quantities' => $lineQuantities,
            'amount' => $totalAmount,
            'currency' => $currency,
            'sale_condition' => $saleCondition,
            'concepts' => $concepts,
            'reference_info' => $referenceInfo,
            'raw_xml' => $payload,
        ];
    }

    protected function loadXml(string $payload): \SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $document = simplexml_load_string(
            $payload,
            'SimpleXMLElement',
            LIBXML_NOCDATA | LIBXML_NSCLEAN | LIBXML_NONET
        );

        if ($document === false) {
            $errorMessage = collect(libxml_get_errors())
                ->map(fn ($error) => trim($error->message))
                ->filter()
                ->implode('; ');

            libxml_clear_errors();

            throw new \RuntimeException('Unable to parse XML receipt: ' . ($errorMessage ?: 'Unknown error'));
        }

        return $document;
    }

    protected function firstValue(\SimpleXMLElement $document, string $path): ?string
    {
        $segments = array_map('trim', explode('/', $path));

        $xpath = '//' . implode('/', array_map(function (string $segment) {
            return '*[local-name()="' . $segment . '"]';
        }, $segments));

        $result = $document->xpath($xpath);

        if (!$result || !isset($result[0])) {
            return null;
        }

        $value = (string) $result[0];

        return $value !== '' ? trim($value) : null;
    }

    protected function collectConcepts(\SimpleXMLElement $document): array
    {
        $details = $document->xpath('//*[local-name()="LineaDetalle"]/*[local-name()="Detalle"]');

        if (!$details) {
            $details = $document->xpath('//*[local-name()="DetalleServicio"]//*[local-name()="Detalle"]');
        }

        return collect($details ?? [])
            ->map(fn ($detail) => trim((string) $detail))
            ->filter()
            ->values()
            ->all();
    }

    protected function collectProviderLocation(\SimpleXMLElement $document): array
    {
        $province = $this->firstValue($document, 'Emisor/Ubicacion/Provincia');
        $canton = $this->firstValue($document, 'Emisor/Ubicacion/Canton');
        $district = $this->firstValue($document, 'Emisor/Ubicacion/Distrito');

        return [
            'province_code' => $this->normalizeLocationCode($province, 1),
            'canton_code' => $this->normalizeLocationCode($canton, 2),
            'district_code' => $this->normalizeLocationCode($district, 2),
            'neighborhood' => $this->firstValue($document, 'Emisor/Ubicacion/Barrio'),
            'address' => $this->firstValue($document, 'Emisor/Ubicacion/OtrasSenas'),
        ];
    }

    protected function normalizeLocationCode(?string $value, int $expectedLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) < $expectedLength) {
            $digits = str_pad($digits, $expectedLength, '0', STR_PAD_LEFT);
        }

        return $digits;
    }

    protected function collectConceptLines(\SimpleXMLElement $document): array
    {
        $lines = $document->xpath('//*[local-name()="LineaDetalle"]');

        return collect($lines ?? [])
            ->map(function ($line) {
                $detail = $line->xpath('*[local-name()="Detalle"]');
                $total = $line->xpath('*[local-name()="MontoTotalLinea"]');
                $quantity = $line->xpath('*[local-name()="Cantidad"]');

                return [
                    'detail' => isset($detail[0]) ? trim((string) $detail[0]) : null,
                    'total' => isset($total[0]) ? trim((string) $total[0]) : null,
                    'quantity' => isset($quantity[0]) ? trim((string) $quantity[0]) : null,
                ];
            })
            ->filter(fn ($data) => !empty($data['detail']))
            ->values()
            ->all();
    }

    protected function collectLineQuantities(\SimpleXMLElement $document): array
    {
        $lines = $document->xpath('//*[local-name()="LineaDetalle"]');

        return collect($lines ?? [])
            ->map(function ($line) {
                $quantity = $line->xpath('*[local-name()="Cantidad"]');

                return isset($quantity[0]) ? trim((string) $quantity[0]) : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function detectReceiptType(\SimpleXMLElement $document): string
    {
        // First, try to get TipoDocumento from Hacienda Costa Rica XML
        $tipoDocumento = $this->firstValue($document, 'TipoDocumento');

        if ($tipoDocumento !== null) {
            // Hacienda Costa Rica document type codes:
            // 01 = Factura Electrónica
            // 02 = Nota de Débito Electrónica
            // 03 = Nota de Crédito Electrónica
            // 04 = Tiquete Electrónico
            return match ($tipoDocumento) {
                '01' => 'factura',
                '02' => 'nota_debito',
                '03' => 'nota_credito',
                '04' => 'tiquete',
                default => 'unknown',
            };
        }

        // Fallback: Check the root element local name for backwards compatibility
        $rootName = strtolower($document->getName());

        if ($rootName === 'notacreditoelectronica') {
            return 'nota_credito';
        }

        if ($rootName === 'facturaelectronica') {
            return 'factura';
        }

        if ($rootName === 'tiqueteelectronico') {
            return 'tiquete';
        }

        if ($rootName === 'notadebitoelectronica') {
            return 'nota_debito';
        }

        // Default to unknown
        return 'unknown';
    }

    protected function collectReferenceInfo(\SimpleXMLElement $document): ?array
    {
        // For Nota de Credito, collect reference information
        $numeroRef = $this->firstValue($document, 'InformacionReferencia/Numero');
        $fechaEmisionRef = $this->firstValue($document, 'InformacionReferencia/FechaEmisionIR');
        $codigoRef = $this->firstValue($document, 'InformacionReferencia/Codigo');
        $razonRef = $this->firstValue($document, 'InformacionReferencia/Razon');

        if (!$numeroRef && !$fechaEmisionRef) {
            return null;
        }

        return [
            'numero' => $numeroRef,
            'fecha_emision' => $fechaEmisionRef,
            'codigo' => $codigoRef,
            'razon' => $razonRef,
        ];
    }
}

