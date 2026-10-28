<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Testing;

use Aenzenith\LucaEFatura\Exceptions\LucaException;
use Aenzenith\LucaEFatura\Soap\EnvelopeBuilder;
use Aenzenith\LucaEFatura\Transport\SoapRequest;
use Aenzenith\LucaEFatura\Transport\SoapResponse;
use Aenzenith\LucaEFatura\Transport\Transport;
use Closure;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Records requests and answers them from stubs instead of calling Luca.
 *
 *   $fake = Luca::fake([
 *       'GetCompanyBalance' => ['Balance' => 120],
 *       'SendArchiveInvoiceXml' => FakeResponse::failure('Fatura numarası daha önce kullanılmış'),
 *       'SearchInvoice' => [FakeResponse::connectionError(), FakeResponse::success([...])],
 *   ]);
 *
 * A stub is an array of response members (success), a FakeResponse, a
 * closure (SoapRequest): FakeResponse|array, or a list of those consumed in
 * order (the last one repeats). Operations without a stub answer with an
 * empty success, unless preventStrayRequests() was called.
 */
final class FakeTransport implements Transport
{
    /**
     * @var array<string, list<FakeResponse|Closure|array<string, mixed>>>
     */
    private array $stubs = [];

    /**
     * @var list<SoapRequest>
     */
    private array $recorded = [];

    private bool $preventStray = false;

    private ?EnvelopeBuilder $builder = null;

    /**
     * @param  array<string, mixed>  $stubs
     */
    public function __construct(array $stubs = [])
    {
        foreach ($stubs as $operation => $stub) {
            $this->stub($operation, $stub);
        }
    }

    /**
     * Set by the manager so stubbed members are written with the schema.
     */
    public function useBuilder(EnvelopeBuilder $builder): self
    {
        $this->builder = $builder;

        return $this;
    }

    public function stub(string $operation, mixed $response): self
    {
        $this->stubs[$operation] = is_array($response) && array_is_list($response) && $response !== []
            ? array_map(self::normalize(...), $response)
            : [self::normalize($response)];

        return $this;
    }

    public function preventStrayRequests(bool $prevent = true): self
    {
        $this->preventStray = $prevent;

        return $this;
    }

    public function send(SoapRequest $request): SoapResponse
    {
        $this->recorded[] = $request;

        $name = $request->operation->name;
        $stub = null;

        if (isset($this->stubs[$name])) {
            $stub = count($this->stubs[$name]) > 1 ? array_shift($this->stubs[$name]) : $this->stubs[$name][0];
        } elseif ($this->preventStray) {
            throw new LucaException("Luca fake: no stub for [{$name}] and stray requests are prevented.");
        }

        $response = $stub instanceof Closure ? $stub($request) : ($stub ?? FakeResponse::success());

        if (! $response instanceof FakeResponse) {
            $response = FakeResponse::success(is_array($response) ? $response : []);
        }

        if ($this->builder === null) {
            throw new LucaException('Luca fake: no envelope builder; create the fake with Luca::fake().');
        }

        return $response->toResponse($request, $this->builder);
    }

    /**
     * @return list<SoapRequest>
     */
    public function recorded(?string $operation = null, ?Closure $filter = null): array
    {
        return array_values(array_filter(
            $this->recorded,
            static fn (SoapRequest $request): bool => ($operation === null || $request->operation->name === $operation)
                && ($filter === null || $filter($request->payload, $request) === true),
        ));
    }

    /**
     * @param  (Closure(array<string, mixed>, SoapRequest): bool)|null  $callback
     */
    public function assertSent(string $operation, ?Closure $callback = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->recorded($operation, $callback),
            "Luca fake: expected [{$operation}] to be sent".($callback !== null ? ' matching the callback' : '').'.'
        );
    }

    /**
     * @param  (Closure(array<string, mixed>, SoapRequest): bool)|null  $callback
     */
    public function assertNotSent(string $operation, ?Closure $callback = null): void
    {
        PHPUnit::assertEmpty(
            $this->recorded($operation, $callback),
            "Luca fake: [{$operation}] was sent unexpectedly."
        );
    }

    public function assertSentCount(int $count, ?string $operation = null): void
    {
        PHPUnit::assertCount($count, $this->recorded($operation), 'Luca fake: unexpected number of requests.');
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->recorded, 'Luca fake: requests were sent unexpectedly.');
    }

    /**
     * @return FakeResponse|Closure|array<string, mixed>
     */
    private static function normalize(mixed $response): FakeResponse|Closure|array
    {
        if ($response instanceof FakeResponse || $response instanceof Closure) {
            return $response;
        }

        if (is_array($response)) {
            /** @var array<string, mixed> $response */
            return $response;
        }

        throw new LucaException('Luca fake: a stub must be an array, a FakeResponse or a Closure.');
    }
}
