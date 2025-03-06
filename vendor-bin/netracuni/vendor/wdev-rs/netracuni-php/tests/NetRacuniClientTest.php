<?php

namespace WdevRs\NetRacuniPhp\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use WdevRs\NetRacuniPhp\InvoiceResponse;
use WdevRs\NetRacuniPhp\NetRacuniClient;

class NetRacuniClientTest extends TestCase
{
    /**
     * @return void
     *
     * @covers \WdevRs\NetRacuniPhp\NetRacuniClient::ping
     */
    public function testPing()
    {
        $netRacuni = new NetRacuniClient('token');
        $mock = $netRacuni->fake();
        $mock->shouldReceive('send')->andReturn(new Response(200, [], json_encode('Ok')));

        $result = $netRacuni->ping();

        $this->assertTrue($result);
    }

    /**
     * @return void
     * @throws \Exception
     *
     * @covers \WdevRs\NetRacuniPhp\NetRacuniClient::getTaxLabels
     */
    public function testGetTaxLabels()
    {
        $netRacuni = new NetRacuniClient('token');
        $mock = $netRacuni->fake();
        $expectedTaxLabels = '[{"order_id":1,"name":"ECAL","category_type":0,"label":"F","rate":11},{"order_id":2,"name":"N-TAX","category_type":0,"label":"N","rate":0},{"order_id":3,"name":"PBL","category_type":2,"label":"P","rate":0.5},{"order_id":4,"name":"STT","category_type":0,"label":"E","rate":6},{"order_id":5,"name":"TOTL","category_type":1,"label":"T","rate":2},{"order_id":6,"name":"VAT","category_type":0,"label":"A","rate":10},{"order_id":6,"name":"VAT","category_type":0,"label":"B","rate":0},{"order_id":6,"name":"VAT","category_type":0,"label":"\u0416","rate":19},{"order_id":7,"name":"VAT-EXCL","category_type":0,"label":"C","rate":0}]';
        $mock->shouldReceive('send')->andReturn(new Response(200, [], $expectedTaxLabels));

        $result = $netRacuni->getTaxLabels();

        $this->assertEquals($expectedTaxLabels, json_encode($result));
    }

    /**
     * @return void
     * @throws \Exception
     *
     * @covers \WdevRs\NetRacuniPhp\NetRacuniClient::createInvoice
     */
    public function testCreateInvoice()
    {
        $netRacuni = new NetRacuniClient('token');
        $mock = $netRacuni->fake();
        $expectedInvoiceResponse = new InvoiceResponse([
            'invoice' => ['test'],
            'invoice_pdf' => 'https://example.com/invoice.pdf'
        ]);

        $mock->shouldReceive('send')->andReturn(new Response(200, [], json_encode($expectedInvoiceResponse)));

        $items = [
            "items" => [
                [
                    "name" => "Test Item",
                    "taxLabels" => [
                        "A"
                    ],
                    "unit" => "KOM",
                    "quantity" => 2,
                    "price" => 152.66
                ]
            ]
        ];

        $result = $netRacuni->createInvoice($items);

        $this->assertEquals('https://example.com/invoice.pdf', $result->getInvoicePdfUrl());
        $this->assertEquals(['test'], $result->getInvoice());
    }
}
