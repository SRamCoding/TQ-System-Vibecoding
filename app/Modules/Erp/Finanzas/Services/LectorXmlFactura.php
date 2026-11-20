<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Services;

use RuntimeException;
use SimpleXMLElement;

/**
 * MVP rápido (t5-finanzas v3): lee la factura electrónica (UBL 2.1 de SUNAT): serie y número, emisión, RUC y razón
 * social del emisor, moneda, base, IGV y total. Nada de esto se vuelve a escribir a mano.
 */
final readonly class LectorXmlFactura
{
    /** @return array{serie_numero: string, emision: string, ruc: string, razon_social: string, moneda: string, subtotal: string, igv: string, total: string} */
    public function leer(string $xml): array
    {
        $previo = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previo);

        if ($doc === false) {
            throw new RuntimeException('El archivo no es un XML válido.');
        }

        $doc->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $doc->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $uno = fn (string $ruta): ?string => ($n = $doc->xpath($ruta)) ? trim((string) $n[0]) : null;
        $numero = $uno('/*/cbc:ID');
        $ruc = $uno('//cac:AccountingSupplierParty//cac:PartyIdentification/cbc:ID') ?? $uno('//cac:AccountingSupplierParty/cbc:CustomerAssignedAccountID');
        $total = $uno('//cac:LegalMonetaryTotal/cbc:PayableAmount');

        if ($numero === null || $ruc === null || $total === null) {
            throw new RuntimeException('El XML no parece una factura electrónica de SUNAT (falta el número, el RUC o el total).');
        }

        $igv = $uno('/*/cac:TaxTotal/cbc:TaxAmount') ?? '0';
        $base = $uno('//cac:LegalMonetaryTotal/cbc:LineExtensionAmount') ?? bcsub($total, $igv, 2);

        return [
            'serie_numero' => $numero, 'emision' => (string) $uno('/*/cbc:IssueDate'), 'ruc' => $ruc,
            'razon_social' => (string) ($uno('//cac:AccountingSupplierParty//cac:PartyLegalEntity/cbc:RegistrationName') ?? ''),
            'moneda' => (string) ($uno('/*/cbc:DocumentCurrencyCode') ?? 'PEN'), 'subtotal' => bcadd($base, '0', 2), 'igv' => bcadd($igv, '0', 2), 'total' => bcadd($total, '0', 2),
        ];
    }
}
