<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Modules;

use Aenzenith\LucaEFatura\Connection;
use Aenzenith\LucaEFatura\Data\Record;
use Aenzenith\LucaEFatura\Data\SentDocument;
use Aenzenith\LucaEFatura\Exceptions\InvalidDocumentException;
use Aenzenith\LucaEFatura\Result;
use Aenzenith\LucaEFatura\Support\Ettn;
use Aenzenith\LucaEFatura\Support\UblDocument;
use InvalidArgumentException;

/**
 * Base of the task-level modules. A module only turns arguments into a
 * payload and a Result into data; the connection does the rest.
 */
abstract class Module
{
    /**
     * Documents per send call (Luca: "en az 1, en fazla 100").
     */
    public const MAX_BATCH = 100;

    public function __construct(
        protected readonly Connection $connection,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function call(string $operation, array $payload = []): Result
    {
        return $this->connection->call($operation, $payload);
    }

    /**
     * @return array{PageNumber: int, RecordsPerPage: int}
     */
    protected static function paging(int $page, int $perPage): array
    {
        return ['PageNumber' => max(1, $page), 'RecordsPerPage' => max(1, $perPage)];
    }

    /**
     * @param  array<array-key, mixed>  $items
     */
    protected static function assertBatch(array $items): void
    {
        $count = count($items);

        if ($count === 0 || $count > self::MAX_BATCH) {
            throw new InvalidArgumentException('Luca: a send call takes 1 to '.self::MAX_BATCH." documents, {$count} given.");
        }
    }

    /**
     * Parse each document up front: refuses empty, malformed or already
     * base64-encoded content before the call.
     *
     * @param  list<string>  $documents
     * @return list<UblDocument>
     */
    protected static function parseAll(array $documents): array
    {
        return array_map(static fn (string $xml): UblDocument => UblDocument::parse($xml), $documents);
    }

    /**
     * One SentDocument per document sent, even when the response lists
     * fewer (fields of the missing ones are null).
     *
     * @param  list<mixed>  $items
     * @return non-empty-list<SentDocument>
     */
    protected static function sentList(array $items, int $count): array
    {
        $sent = [];

        for ($i = 0; $i < max(1, $count); $i++) {
            $item = $items[$i] ?? [];
            $sent[] = SentDocument::fromArray(is_array($item) ? $item : []);
        }

        return $sent;
    }

    /**
     * The record with exactly this ETTN. A search answers with other
     * documents when the filter is ignored; those must not be taken for
     * the one asked for.
     *
     * @param  list<mixed>  $items
     */
    protected static function matching(array $items, string $ettn): ?Record
    {
        foreach (Record::listFrom($items) as $record) {
            if (Ettn::same($record->ettn(), $ettn)) {
                return $record;
            }
        }

        return null;
    }

    protected static function requireTag(?string $receiverTag): string
    {
        $receiverTag = trim((string) $receiverTag);

        if ($receiverTag === '') {
            throw new InvalidDocumentException('Luca: a receiver alias (urn:mail:...pk@...) is required.');
        }

        return $receiverTag;
    }

    /**
     * The list under the first of $keys present (any case), or else the
     * only list-valued member.
     *
     * @return list<array<string, mixed>>
     */
    protected static function records(Result $result, string ...$keys): array
    {
        $data = array_change_key_case($result->data(), CASE_LOWER);
        unset($data['result'], $data['errormessage']);

        $value = null;

        foreach ($keys as $key) {
            if (array_key_exists(strtolower($key), $data)) {
                $value = $data[strtolower($key)];
                break;
            }
        }

        if ($value === null && count($data) === 1) {
            $value = reset($data);
        }

        $records = [];

        foreach (Result::asList($value) as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $records[] = $item;
            }
        }

        return $records;
    }
}
