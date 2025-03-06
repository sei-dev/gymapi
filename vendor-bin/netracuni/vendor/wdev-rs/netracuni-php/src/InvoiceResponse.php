<?php

namespace WdevRs\NetRacuniPhp;

use PHPUnit\Util\Json;

class InvoiceResponse implements \JsonSerializable
{
    private array $invoice;
    private string $invoicePdfUrl;

    /**
     * @param array $invoice
     * @param string $invoicePdfUrl
     */
    public function __construct(array $data)
    {
        $this->invoice = $data['invoice'];
        $this->invoicePdfUrl = $data['invoice_pdf'];
    }

    public function getInvoice(): array
    {
        return $this->invoice;
    }

    public function getInvoicePdfUrl(): string
    {
        return $this->invoicePdfUrl;
    }

    public function jsonSerialize(): array
    {
        return [
            'invoice' => $this->invoice,
            'invoice_pdf' => $this->invoicePdfUrl
        ];
    }
}