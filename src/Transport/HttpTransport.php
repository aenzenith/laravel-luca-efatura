<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Transport;

use Aenzenith\LucaEFatura\ConnectionConfig;
use Aenzenith\LucaEFatura\Exceptions\ConnectionException;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Factory;

/**
 * Sends envelopes with Laravel's HTTP client, so Http::fake() and the
 * client's global middleware apply as well.
 */
final class HttpTransport implements Transport
{
    public function __construct(
        private readonly Factory $http,
        private readonly ConnectionConfig $config,
    ) {}

    public function send(SoapRequest $request): SoapResponse
    {
        $options = ['verify' => $this->config->verify];

        if ($this->config->proxy !== null) {
            $options['proxy'] = $this->config->proxy;
        }

        try {
            $response = $this->http
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '"'.$request->action().'"',
                    'Accept' => 'text/xml',
                ])
                ->timeout($this->config->timeout)
                ->connectTimeout($this->config->connectTimeout)
                ->withOptions($options)
                ->withBody($request->body, 'text/xml; charset=utf-8')
                ->post($request->url);
        } catch (HttpConnectionException $e) {
            throw ConnectionException::wrap($request->operation->name, $request->url, $e);
        }

        return new SoapResponse($response->status(), $response->body());
    }
}
