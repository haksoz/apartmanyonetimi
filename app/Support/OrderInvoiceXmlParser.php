<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

class OrderInvoiceXmlParser
{
    public function parse(string $xml): array
    {
        if (strlen($xml) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('XML en fazla 5 MB olabilir.');
        }
        if (trim($xml) === '') {
            throw new InvalidArgumentException('XML içeriği boş.');
        }
        $document = new DOMDocument;
        $document->resolveExternals = false;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded || $document->doctype !== null || $document->documentElement?->localName !== 'Invoice'
            || $document->documentElement?->namespaceURI !== 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2') {
            throw new InvalidArgumentException('Geçerli bir UBL satış faturası XML dosyası yükleyin.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('inv', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $text = fn (string $path): string => trim((string) $xpath->evaluate('string(/inv:Invoice/'.$path.')'));
        $number = mb_strtoupper($text('cbc:ID'));
        $date = $text('cbc:IssueDate');
        $checkDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($number === '' || mb_strlen($number) > 100 || ! $checkDate || $checkDate->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('XML’de geçerli bir fatura numarası ve tarihi bulunamadı.');
        }
        if ($text('cbc:DocumentCurrencyCode') !== 'TRY' || $text('cbc:InvoiceTypeCode') === 'IADE') {
            throw new InvalidArgumentException('Türk lirası ile düzenlenmiş satış faturası XML’i yükleyin.');
        }
        $identity = $text("cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID[@schemeID='VKN' or @schemeID='TCKN']");
        $identity = preg_replace('/\s+/u', '', $identity);
        if (! preg_match('/^\d{10,11}$/D', $identity)) {
            throw new InvalidArgumentException('XML’de alıcı VKN/TCKN bulunamadı veya geçersiz.');
        }
        $money = function (string $path) use ($xpath): int {
            $nodes = $xpath->query('/inv:Invoice/'.$path);
            if ($nodes->length === 0) {
                throw new InvalidArgumentException('XML’de fatura toplamları eksik.');
            }
            $sum = 0;
            foreach ($nodes as $node) {
                $value = trim($node->textContent);
                if (($node->hasAttribute('currencyID') && $node->getAttribute('currencyID') !== 'TRY')
                    || ! preg_match('/^\d{1,12}(?:\.\d{1,6})?$/D', $value)) {
                    throw new InvalidArgumentException('XML’de tutar veya para birimi geçersiz.');
                }
                $sum += (int) round((float) $value * 100);
            }

            return $sum;
        };
        $net = $money('cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount');
        $tax = $money('cac:TaxTotal/cbc:TaxAmount');
        $gross = $money('cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount');
        if ($gross <= 0 || $net + $tax !== $gross) {
            throw new InvalidArgumentException('XML’de KDV hariç tutar, KDV ve genel toplam birbiriyle uyuşmuyor.');
        }
        $descriptions = [];
        foreach ($xpath->query('/inv:Invoice/cac:InvoiceLine//cbc:Name | /inv:Invoice/cac:InvoiceLine//cbc:Description | /inv:Invoice/cac:InvoiceLine/cbc:Note | /inv:Invoice/cbc:Note | /inv:Invoice/cac:OrderReference/cbc:ID') as $node) {
            $descriptions[] = $node->textContent;
        }
        $description = implode(' ', $descriptions);
        preg_match_all('/\bSIP-\d{2}-[A-Z0-9]+(?:-[A-Z0-9]+)*\b/i', $description, $orders);
        preg_match_all('/\bABN-\d{2}-[A-Z0-9]+(?:-[A-Z0-9]+)*\b/i', $description, $subscriptions);
        $orders = array_values(array_unique(array_map('strtoupper', $orders[0])));
        $subscriptions = array_values(array_unique(array_map('strtoupper', $subscriptions[0])));
        if ($orders === [] && $subscriptions === []) {
            throw new InvalidArgumentException('Fatura açıklamalarında SIP veya ABN numarası bulunamadı.');
        }
        if (count($orders) > 1 || count($subscriptions) > 1) {
            throw new InvalidArgumentException('Bu akış tek sipariş içindir. XML’de birden fazla SIP veya ABN numarası var.');
        }
        $uuid = strtolower($text('cbc:UUID'));
        if (strlen($uuid) > 64) {
            throw new InvalidArgumentException('Fatura UUID alanı geçersiz.');
        }

        return [
            'invoice_number' => $number, 'invoice_date' => $date, 'uuid' => $uuid ?: null,
            'customer_identity' => $identity,
            'customer_name' => $text('cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name'),
            'order_numbers' => $orders, 'subscription_numbers' => $subscriptions,
            'net_amount' => $net / 100, 'tax_amount' => $tax / 100, 'amount' => $gross / 100,
        ];
    }
}
