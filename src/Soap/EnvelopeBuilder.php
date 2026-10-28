<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Soap;

use Aenzenith\LucaEFatura\Exceptions\SchemaException;
use Aenzenith\LucaEFatura\Schema\Operation;
use Aenzenith\LucaEFatura\Schema\Schema;
use Illuminate\Contracts\Support\Arrayable;
use XMLWriter;

/**
 * Writes SOAP 1.1 envelopes from plain arrays, in the order and the
 * namespaces the data contract dictates.
 *
 * A payload is an array keyed by data member name; nested complex types are
 * nested arrays and array types are lists. Members may come in any order and
 * null members are left out. With strict on (the default) a key the
 * contract does not define is refused instead of being sent to a service
 * that would silently ignore it.
 */
final class EnvelopeBuilder
{
    public const SOAP_ENVELOPE = 'http://schemas.xmlsoap.org/soap/envelope/';

    public const TEMPURI = 'http://tempuri.org/';

    public const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public const ARRAYS = 'http://schemas.microsoft.com/2003/10/Serialization/Arrays';

    public function __construct(
        private readonly Schema $schema,
        private readonly ValueCodec $codec,
        private readonly bool $strict = true,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  members of the operation's request type
     */
    public function request(Operation $operation, array $payload): string
    {
        $writer = $this->open();

        $writer->startElement('s:Envelope');
        $writer->writeAttribute('xmlns:s', self::SOAP_ENVELOPE);
        $writer->writeAttribute('xmlns:tem', self::TEMPURI);

        foreach ($this->schema->namespaces() as $alias => $uri) {
            $writer->writeAttribute('xmlns:'.$this->prefix($alias), $uri);
        }

        $writer->startElement('s:Body');
        $writer->startElement('tem:'.$operation->name);
        $writer->startElement('tem:request');
        $this->writeComplex($writer, $operation->request, $payload, $operation->name, true);
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();

        return $writer->outputMemory();
    }

    /**
     * A response envelope shaped like WCF writes it. Used by the test fake.
     *
     * @param  array<string, mixed>  $payload  members of the operation's response type
     */
    public function response(Operation $operation, array $payload): string
    {
        $writer = $this->open();

        $writer->startElement('s:Envelope');
        $writer->writeAttribute('xmlns:s', self::SOAP_ENVELOPE);
        $writer->startElement('s:Body');
        $writer->startElement($operation->name.'Response');
        $writer->writeAttribute('xmlns', self::TEMPURI);
        $writer->startElement($operation->name.'Result');
        $writer->writeAttribute('xmlns:i', self::XSI);

        foreach ($this->schema->namespaces() as $alias => $uri) {
            $writer->writeAttribute('xmlns:'.$this->prefix($alias), $uri);
        }

        $operation->response !== null && $this->schema->hasType($operation->response)
            ? $this->writeComplex($writer, $operation->response, $payload, $operation->name, false)
            : $this->writeLoose($writer, $payload, $this->prefix('Model'));

        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();

        return $writer->outputMemory();
    }

    /**
     * A SOAP 1.1 fault envelope. Used by the test fake.
     */
    public static function fault(string $code, string $message): string
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startElement('s:Envelope');
        $writer->writeAttribute('xmlns:s', self::SOAP_ENVELOPE);
        $writer->startElement('s:Body');
        $writer->startElement('s:Fault');
        $writer->writeElement('faultcode', $code);
        $writer->writeElement('faultstring', $message);
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();

        return $writer->outputMemory();
    }

    private function open(): XMLWriter
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'utf-8');

        return $writer;
    }

    private function prefix(string $alias): string
    {
        return strtolower($alias);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function writeComplex(XMLWriter $writer, string $type, array $values, string $path, bool $strict): void
    {
        $fields = $this->schema->fields($type);
        $known = array_column($fields, 'name');

        $unknown = array_diff(array_map('strval', array_keys($values)), $known);

        if ($unknown !== [] && $strict && $this->strict) {
            $field = reset($unknown);

            throw new SchemaException(
                "Luca: [{$path}] has no field [{$field}] (type ".Schema::localName($type).').'
                .Schema::suggest($field, $known)
            );
        }

        foreach ($fields as $field) {
            if (! array_key_exists($field['name'], $values) || $values[$field['name']] === null) {
                continue;
            }

            $writer->startElement($this->prefix($field['alias']).':'.$field['name']);
            $this->writeValue($writer, $field['type'], $values[$field['name']], $path.'.'.$field['name'], $strict);
            $writer->endElement();
        }

        foreach ($unknown as $field) {
            if ($values[$field] === null) {
                continue;
            }

            $prefix = $this->prefix(Schema::alias($type));
            $writer->startElement($prefix.':'.$field);
            $this->writeLooseValue($writer, $values[$field], $prefix, $field);
            $writer->endElement();
        }
    }

    private function writeValue(XMLWriter $writer, string $type, mixed $value, string $path, bool $strict): void
    {
        if (Schema::isPrimitive($type)) {
            $writer->text($this->codec->encode($type, $value, $path));

            return;
        }

        $definition = $this->schema->type($type);

        if ($definition['kind'] === 'enum') {
            $writer->text($this->codec->encode('string', $value, $path));

            return;
        }

        $value = $value instanceof Arrayable ? $value->toArray() : $value;

        if (! is_array($value)) {
            throw new SchemaException(
                "Luca: [{$path}] expects ".($definition['kind'] === 'array' ? 'a list' : 'an array')
                .' ('.Schema::localName($type).'), got '.get_debug_type($value).'.'
            );
        }

        if ($definition['kind'] === 'complex') {
            $this->writeComplex($writer, $type, $value, $path, $strict);

            return;
        }

        if (! array_is_list($value)) {
            throw new SchemaException("Luca: [{$path}] expects a list of ".($definition['item'] ?? 'items').', got an associative array.');
        }

        $item = $this->prefix(Schema::alias($type)).':'.($definition['item'] ?? 'item');

        foreach ($value as $index => $element) {
            if ($element === null) {
                continue;
            }

            $writer->startElement($item);
            $this->writeValue($writer, $definition['of'] ?? 'string', $element, $path.'['.$index.']', $strict);
            $writer->endElement();
        }
    }

    /**
     * Writes data no type describes: element per key, list items named after
     * their parent ("TaxPayers" → "TaxPayer", "InboxTagList" → "InboxTag").
     *
     * @param  array<array-key, mixed>  $values
     */
    private function writeLoose(XMLWriter $writer, array $values, string $prefix): void
    {
        foreach ($values as $key => $value) {
            $writer->startElement($prefix.':'.$key);
            $this->writeLooseValue($writer, $value, $prefix, (string) $key);
            $writer->endElement();
        }
    }

    private function writeLooseValue(XMLWriter $writer, mixed $value, string $prefix, string $key): void
    {
        $value = $value instanceof Arrayable ? $value->toArray() : $value;

        if ($value === null) {
            $writer->writeAttribute('i:nil', 'true');

            return;
        }

        if (! is_array($value)) {
            $writer->text(match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_scalar($value) => (string) $value,
                default => $this->codec->encode('string', $value, $key),
            });

            return;
        }

        if (! array_is_list($value)) {
            $this->writeLoose($writer, $value, $prefix);

            return;
        }

        foreach ($value as $element) {
            $writer->startElement(is_array($element) ? $prefix.':'.self::singular($key) : $this->prefix('Arrays').':string');
            $this->writeLooseValue($writer, $element, $prefix, self::singular($key));
            $writer->endElement();
        }
    }

    public static function singular(string $name): string
    {
        return match (true) {
            str_starts_with($name, 'ArrayOf') => substr($name, 7),
            str_ends_with($name, 'List') && strlen($name) > 4 => substr($name, 0, -4),
            str_ends_with($name, 'ies') => substr($name, 0, -3).'y',
            str_ends_with($name, 's') && strlen($name) > 1 => substr($name, 0, -1),
            default => $name.'Item',
        };
    }
}
