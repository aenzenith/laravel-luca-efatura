<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Data;

use ArrayAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use LogicException;

/**
 * One document from a search: e-Fatura, e-Arşiv, e-İrsaliye, irsaliye
 * yanıtı or e-SMM. The accessors read the member each document type uses
 * for the same thing (InvoicePdf / DespatchAdvicePdf / PdfFile, ...);
 * everything else is in raw / get().
 *
 * @implements ArrayAccess<string, mixed>
 */
final class Record implements ArrayAccess
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly array $raw,
    ) {}

    /**
     * @param  list<mixed>  $items
     * @return list<self>
     */
    public static function listFrom(array $items): array
    {
        $records = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $records[] = new self($item);
            }
        }

        return $records;
    }

    public function ettn(): ?string
    {
        return $this->string('ETTN', 'Ettn');
    }

    public function number(): ?string
    {
        return $this->string('InvoiceNumber', 'DespatchAdviceNumber', 'ReceiptAdviceNumber', 'DocumentNumber');
    }

    public function externalCode(): ?string
    {
        return $this->string('ExternalInvoiceCode', 'ExternalArchiveInvoiceCode', 'ExternalDespatchAdviceCode', 'ExternalReceiptAdviceCode', 'ExternalDocumentNumber');
    }

    public function status(): ?string
    {
        return $this->string('Status');
    }

    public function date(): ?CarbonImmutable
    {
        $date = $this->string('InvoiceDate', 'DespatchAdviceDate', 'ReceiptAdviceDate', 'DocumentDate');

        return $date === null ? null : CarbonImmutable::parse($date, 'Europe/Istanbul');
    }

    /**
     * PDF bytes, when the search asked for them (ResultSet.IsPDFIncluded).
     */
    public function pdf(): ?string
    {
        return $this->string('InvoicePdf', 'DespatchAdvicePdf', 'ReceiptAdvicePdf', 'PdfFile');
    }

    /**
     * UBL bytes, when the search asked for them (ResultSet.IsXMLIncluded).
     */
    public function xml(): ?string
    {
        return $this->string('InvoiceXml', 'DespatchAdviceXml', 'ReceiptAdviceXml', 'XmlFile');
    }

    public function html(): ?string
    {
        return $this->string('InvoiceHtml', 'DespatchAdviceHtml', 'ReceiptAdviceHtml');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->raw, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->raw[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->raw[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Luca: records are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Luca: records are read-only.');
    }

    private function string(string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->raw[$key] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
