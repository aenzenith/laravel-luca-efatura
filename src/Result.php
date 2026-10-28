<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura;

use Aenzenith\LucaEFatura\Exceptions\OperationFailedException;
use Aenzenith\LucaEFatura\Schema\Operation;
use Illuminate\Support\Arr;

/**
 * The parsed result of one call.
 *
 * Success means the service said Result=Success. A response without a
 * Result member is not a success.
 */
final class Result
{
    /**
     * @param  array<string, mixed>  $data  members of the operation result
     */
    public function __construct(
        public readonly Operation $operation,
        private readonly array $data,
        public readonly string $raw = '',
        public readonly ?Company $company = null,
        public readonly float $durationMs = 0.0,
    ) {}

    public function successful(): bool
    {
        return ($this->data['Result'] ?? null) === 'Success';
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    public function errorMessage(): ?string
    {
        $message = $this->data['ErrorMessage'] ?? null;

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Read a member with dot notation: get('PagingResponse.TotalNumberOfRecords').
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    /**
     * Read a member that holds a list. A missing, nil or empty member gives
     * an empty list; a single record read without a schema is wrapped.
     *
     * @return list<mixed>
     */
    public function list(string $key): array
    {
        return self::asList($this->get($key));
    }

    /**
     * @return list<mixed>
     */
    public static function asList(mixed $value): array
    {
        return match (true) {
            $value === null, $value === '', $value === [] => [],
            is_array($value) && array_is_list($value) => $value,
            default => [$value],
        };
    }

    /**
     * @throws OperationFailedException
     */
    public function throw(): self
    {
        if ($this->failed()) {
            throw new OperationFailedException($this);
        }

        return $this;
    }
}
