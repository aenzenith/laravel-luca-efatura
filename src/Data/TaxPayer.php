<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Data;

use Aenzenith\LucaEFatura\Result;
use Carbon\CarbonImmutable;

/**
 * A taxpayer registered with GİB for e-Fatura (or e-İrsaliye).
 *
 * Luca documents the members as "Taxpayertaxcode", "Inboxtaglist", ...;
 * keys are read case-insensitively.
 */
final class TaxPayer
{
    /**
     * @param  list<string>  $inboxTags  receiver aliases ("urn:mail:defaultpk@...") e-Fatura is sent to
     * @param  list<string>  $outboxTags
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $taxCode,
        public readonly ?string $name,
        public readonly ?string $type,
        public readonly ?CarbonImmutable $registeredAt,
        public readonly array $inboxTags,
        public readonly array $outboxTags,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $lower = array_change_key_case($data, CASE_LOWER);

        $string = static fn (string $key): ?string => isset($lower[$key]) && is_scalar($lower[$key]) && (string) $lower[$key] !== ''
            ? trim((string) $lower[$key])
            : null;

        $registered = $string('registrationdate');

        /** @var array<string, mixed> $data */
        return new self(
            (string) $string('taxpayertaxcode'),
            $string('taxpayername'),
            $string('type'),
            $registered !== null ? CarbonImmutable::parse($registered, 'Europe/Istanbul') : null,
            self::strings($lower['inboxtaglist'] ?? null),
            self::strings($lower['outboxtaglist'] ?? null),
            $data,
        );
    }

    /**
     * All taxpayer records of a GetTaxPayer-like result.
     *
     * @param  array<string, mixed>  $data
     * @return list<self>
     */
    public static function listFrom(array $data): array
    {
        $lower = array_change_key_case($data, CASE_LOWER);
        $items = Result::asList($lower['taxpayers'] ?? null);

        $taxPayers = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $taxPayers[] = self::fromArray($item);
            }
        }

        return $taxPayers;
    }

    /**
     * One record per tax code, aliases of duplicate records merged.
     *
     * @param  list<self>  $taxPayers
     */
    public static function merge(array $taxPayers): ?self
    {
        $first = $taxPayers[0] ?? null;

        if ($first === null) {
            return null;
        }

        $inbox = [];
        $outbox = [];

        foreach ($taxPayers as $taxPayer) {
            $inbox = [...$inbox, ...$taxPayer->inboxTags];
            $outbox = [...$outbox, ...$taxPayer->outboxTags];
        }

        return new self(
            $first->taxCode,
            $first->name,
            $first->type,
            $first->registeredAt,
            array_values(array_unique($inbox)),
            array_values(array_unique($outbox)),
            $first->raw,
        );
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        $strings = [];

        foreach (Result::asList($value) as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $strings[] = trim((string) $item);
            }
        }

        return $strings;
    }
}
