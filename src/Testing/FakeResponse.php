<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Testing;

use Aenzenith\LucaEFatura\Exceptions\ConnectionException;
use Aenzenith\LucaEFatura\Soap\EnvelopeBuilder;
use Aenzenith\LucaEFatura\Transport\SoapRequest;
use Aenzenith\LucaEFatura\Transport\SoapResponse;
use RuntimeException;

/**
 * A canned answer for FakeTransport. success() and failure() go through the
 * real envelope builder and parser, so a fake exercises the same decoding
 * (base64, lists, nil) a live response does.
 */
final class FakeResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        private readonly string $kind,
        private readonly array $data = [],
        private readonly string $body = '',
        private readonly int $status = 200,
    ) {}

    /**
     * Result=Success plus the given response members.
     *
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data = []): self
    {
        return new self('result', ['ErrorMessage' => null, 'Result' => 'Success'] + $data);
    }

    /**
     * Result=Failed with an error message.
     *
     * @param  array<string, mixed>  $data
     */
    public static function failure(string $message, array $data = []): self
    {
        return new self('result', ['ErrorMessage' => $message, 'Result' => 'Failed'] + $data);
    }

    public static function fault(string $message, string $code = 's:Server'): self
    {
        return new self('raw', [], EnvelopeBuilder::fault($code, $message), 500);
    }

    /**
     * No response at all, the way an unregistered IP is answered.
     */
    public static function connectionError(string $message = 'Recv failure: Connection reset by peer'): self
    {
        return new self('connection', [], $message);
    }

    public static function raw(string $body, int $status = 200): self
    {
        return new self('raw', [], $body, $status);
    }

    public function toResponse(SoapRequest $request, EnvelopeBuilder $builder): SoapResponse
    {
        return match ($this->kind) {
            'result' => new SoapResponse($this->status, $builder->response($request->operation, $this->data)),
            'raw' => new SoapResponse($this->status, $this->body),
            'connection' => throw ConnectionException::wrap(
                $request->operation->name,
                $request->url,
                new RuntimeException($this->body),
            ),
            default => throw new RuntimeException("Unknown fake response kind [{$this->kind}]."),
        };
    }
}
