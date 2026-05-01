<?php

namespace Tests\Unit;

use App\Services\Receipts\XmlReceiptParser;
use PHPUnit\Framework\TestCase;

class XmlReceiptParserTest extends TestCase
{
    public function test_reads_currency_inside_codigo_tipo_moneda_v44(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<FacturaElectronica xmlns="https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/facturaElectronica">
  <Clave>50601052600310123170700500085010000002890177837602</Clave>
  <NumeroConsecutivo>00500085010000002890</NumeroConsecutivo>
  <FechaEmision>2026-05-01T12:05:30-06:00</FechaEmision>
  <Emisor><Nombre>Test SA</Nombre></Emisor>
  <ResumenFactura>
    <CodigoTipoMoneda>
      <CodigoMoneda>CRC</CodigoMoneda>
      <TipoCambio>1.00000</TipoCambio>
    </CodigoTipoMoneda>
    <TotalComprobante>41120.00000</TotalComprobante>
  </ResumenFactura>
</FacturaElectronica>
XML;

        $parsed = (new XmlReceiptParser)->parse($xml);

        $this->assertSame('CRC', $parsed['currency']);
        $this->assertSame('1.00000', $parsed['exchange_rate']);
        $this->assertSame('41120.00000', $parsed['amount']);
    }
}
