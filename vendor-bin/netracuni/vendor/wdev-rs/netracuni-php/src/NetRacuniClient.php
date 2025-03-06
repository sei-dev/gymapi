<?php

namespace WdevRs\NetRacuniPhp;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class NetRacuniClient
{
    private string $token;

    private Client $client;

    private string $url;

    private array $headers;

    const SANDBOX_URL = 'https://staging.netracuni.com/api';
    const PRODUCTION_URL = 'https://netracuni.com/api';

    /**
     * @param $token
     */
    public function __construct($token)
    {
        $this->token = $token;
        $this->client = new Client();

        $isProductionToken = strpos($token, 'net_racuni_staging_') === false && strpos($token,
                'net_racuni_local_') === false;
        $this->url = $isProductionToken ? self::PRODUCTION_URL : self::SANDBOX_URL;

        $this->headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
    }

    public function fake(): \Mockery\MockInterface
    {
        $this->client = \Mockery::mock(Client::class);

        return $this->client;
    }

    public function sandbox(): self
    {
        $this->url = self::SANDBOX_URL;

        return $this;
    }

    public function production(): self
    {
        $this->url = self::PRODUCTION_URL;

        return $this;
    }

    public function ping(): bool
    {
        $request = new Request('GET', $this->url . '/ping', $this->headers);
        $res = $this->client->send($request, ['http_errors' => false]);

        if ($res->getStatusCode() !== 200) {
            $this->throwError($res);
        }

        return json_decode((string)$res->getBody(), true) == 'Ok';
    }

    public function getTaxLabels(): ?array
    {
        $request = new Request('GET', $this->url . '/tax-labels', $this->headers);
        $res = $this->client->send($request, ['http_errors' => false]);

        if ($res->getStatusCode() !== 200) {
            $this->throwError($res);
        }

        $result = json_decode((string)$res->getBody(), true);

        if (empty($result)) {
            throw new \Exception('Empty response was returned from API.');
        }

        return $result;

    }

    public function createInvoice(array $items): ?InvoiceResponse
    {
        $data = json_encode($items);

        $request = new Request('POST', $this->url . '/create-invoice', $this->headers, $data);
        $res = $this->client->send($request, ['http_errors' => false]);

        if ($res->getStatusCode() !== 200) {
            $this->throwError($res);
        }

        $result = json_decode((string)$res->getBody(), true);

        if (empty($result)) {
            throw new \Exception('Empty response was returned from API.');
        }

        return new InvoiceResponse($result);
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $res
     * @return mixed
     * @throws \Exception
     */
    private function throwError(\Psr\Http\Message\ResponseInterface $res)
    {
        $response = json_decode((string)$res->getBody(), true);
        throw new \Exception($response['message'] ?? 'Unknown error occurred!');
    }
}
