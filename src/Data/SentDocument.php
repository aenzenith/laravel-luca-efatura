<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Data;

use Aenzenith\LucaEFatura\Support\UblDocument;

/**
 * What Luca returns for an issued document: its ETTN, the number it was
 * given and your own reference, if any.
 */
final class SentDocument
{
    private const NUMBER_KEYS = ['InvoiceNumber', 'ArchiveInvoiceNumber', 'DespatchAdviceNumber', 'ReceiptAdviceNumber', 'DocumentNumber'];

    private const EXTERNAL_KEYS = ['ExternalInvoiceCode', 'ExternalArchiveInvoiceCode', 'ExternalDespatchAdviceCode', 'ExternalReceiptAdviceCode', 'ExternalESMMCode'];

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?string $ettn,
        public readonly ?string $number,
        public readonly ?string $externalCode,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  an InvoiceReturn, ArchiveInvoiceReturn, DespatchAdviceReturn, ... record
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $data */
        return new self(
            self::first($data, ['Ettn', 'ETTN']),
            self::first($data, self::NUMBER_KEYS),
            self::first($data, self::EXTERNAL_KEYS),
            $data,
        );
    }

    /**
     * Pair the returned records with the documents sent, by position. The
     * UBL's own UUID and number fill what the response leaves out.
     *
     * @param  list<mixed>  $items
     * @param  list<UblDocument>  $documents
     * @return list<self>
     */
    public static function forDocuments(array $items, array $documents): array
    {
        $sent = [];

        foreach ($documents as $index => $document) {
            $item = $items[$index] ?? [];
            $returned = self::fromArray(is_array($item) ? $item : []);

            $sent[] = new self(
                $returned->ettn ?? $document->uuid(),
                $returned->number ?? $document->number(),
                $returned->externalCode,
                $returned->raw,
            );
        }

        return $sent;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<self>
     */
    public static function listFrom(array $items): array
    {
        return array_map(
            fn (mixed $item): self => self::fromArray(is_array($item) ? $item : []),
            $items,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private static function first(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && (string) $data[$key] !== '') {
                return (string) $data[$key];
            }
        }

        return null;
    }
}
