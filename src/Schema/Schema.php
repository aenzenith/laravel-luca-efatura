<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Schema;

use Aenzenith\LucaEFatura\Exceptions\SchemaException;

/**
 * The data contract of the Luca services: namespaces, operations and types.
 *
 * Type keys are "Alias.LocalName" where Alias names an entry of namespaces().
 * A type is one of
 *   ['kind' => 'complex', 'base' => ?key, 'fields' => [name => type, ...]]
 *   ['kind' => 'array',   'item' => element name, 'of' => type]
 *   ['kind' => 'enum',    'values' => [...]]
 * and a type name without a dot ("string", "dateTime", ...) is an XSD
 * primitive.
 *
 * WCF reads data members in order (base type first, then ordinal by name)
 * and skips an element it did not expect, so the field order of a complex
 * type is part of the contract, not cosmetics.
 *
 * @phpstan-type TypeDefinition array{kind: string, base?: string|null, fields?: array<string, string>, item?: string, of?: string, values?: list<string>}
 * @phpstan-type OperationDefinition array{service: string, request: string, response: string|null}
 * @phpstan-type Definition array{namespaces?: array<string, string>, operations?: array<string, OperationDefinition>, types?: array<string, TypeDefinition>}
 */
final class Schema
{
    /**
     * @var array<string, list<array{name: string, type: string, alias: string}>>
     */
    private array $flattened = [];

    /**
     * @param  array<string, string>  $namespaces
     * @param  array<string, OperationDefinition>  $operations
     * @param  array<string, TypeDefinition>  $types
     */
    private function __construct(
        private readonly array $namespaces,
        private readonly array $operations,
        private readonly array $types,
    ) {}

    /**
     * The schema shipped with the package: the 2022 WSDL plus overlay.php.
     */
    public static function bundled(): self
    {
        return self::fromFile(self::resourcePath('invoice-service-2022.php'))
            ->merge(self::load(self::resourcePath('overlay.php')));
    }

    public static function fromFile(string $path): self
    {
        return self::fromArray(self::load($path));
    }

    /**
     * @param  Definition  $definition
     */
    public static function fromArray(array $definition): self
    {
        return new self(
            $definition['namespaces'] ?? [],
            $definition['operations'] ?? [],
            $definition['types'] ?? [],
        );
    }

    /**
     * @return Definition
     */
    public static function load(string $path): array
    {
        if (! is_file($path)) {
            throw new SchemaException("Luca: schema file [{$path}] does not exist.");
        }

        $definition = require $path;

        if (! is_array($definition)) {
            throw new SchemaException("Luca: schema file [{$path}] must return an array.");
        }

        /** @var Definition $definition */
        return $definition;
    }

    public static function resourcePath(string $file): string
    {
        return dirname(__DIR__, 2).'/resources/schema/'.$file;
    }

    /**
     * Combine another definition into this one and return the result.
     *
     * Additive merge (the default): fields of an existing complex type are
     * added at their ordinal position or retyped, enum values are added,
     * everything else is replaced. With $replace, a type that both define
     * is taken whole from $definition, so fields it lacks are dropped; use
     * it for a schema compiled from the live WSDL.
     *
     * @param  Definition  $definition
     */
    public function merge(array $definition, bool $replace = false): self
    {
        $types = $this->types;

        foreach ($definition['types'] ?? [] as $key => $type) {
            $current = $types[$key] ?? null;

            if ($replace || $current === null || $current['kind'] !== $type['kind']) {
                $types[$key] = $type;

                continue;
            }

            $types[$key] = match ($type['kind']) {
                'complex' => $this->mergeComplex($current, $type),
                'enum' => [
                    'kind' => 'enum',
                    'values' => array_values(array_unique([...$current['values'] ?? [], ...$type['values'] ?? []])),
                ],
                default => $type,
            };
        }

        return new self(
            array_replace($this->namespaces, $definition['namespaces'] ?? []),
            array_replace($this->operations, $definition['operations'] ?? []),
            $types,
        );
    }

    public function hasOperation(string $name): bool
    {
        return isset($this->operations[$name]);
    }

    public function operation(string $name): Operation
    {
        $definition = $this->operations[$name] ?? null;

        if ($definition === null) {
            throw new SchemaException(
                "Luca: unknown operation [{$name}].".self::suggest($name, array_keys($this->operations))
            );
        }

        return new Operation($name, $definition['service'], $definition['request'], $definition['response']);
    }

    /**
     * @return array<string, Operation>
     */
    public function operations(): array
    {
        $operations = [];

        foreach (array_keys($this->operations) as $name) {
            $operations[$name] = $this->operation($name);
        }

        return $operations;
    }

    public function hasType(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * @return TypeDefinition
     */
    public function type(string $key): array
    {
        return $this->types[$key] ?? throw new SchemaException("Luca: unknown schema type [{$key}].");
    }

    public static function isPrimitive(string $type): bool
    {
        return ! str_contains($type, '.');
    }

    /**
     * Data members of a complex type in wire order, base type first. Each
     * member carries the namespace alias of the type that declares it.
     *
     * @return list<array{name: string, type: string, alias: string}>
     */
    public function fields(string $key): array
    {
        if (isset($this->flattened[$key])) {
            return $this->flattened[$key];
        }

        $chain = [];
        $current = $key;

        while ($current !== null && $current !== '') {
            if (in_array($current, $chain, true)) {
                throw new SchemaException("Luca: schema type [{$key}] inherits from itself.");
            }

            $chain[] = $current;
            $current = $this->type($current)['base'] ?? null;
        }

        $fields = [];

        foreach (array_reverse($chain) as $level) {
            foreach ($this->type($level)['fields'] ?? [] as $name => $type) {
                $fields[] = ['name' => (string) $name, 'type' => $type, 'alias' => self::alias($level)];
            }
        }

        return $this->flattened[$key] = $fields;
    }

    /**
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return $this->namespaces;
    }

    public function namespaceUri(string $alias): string
    {
        return $this->namespaces[$alias] ?? throw new SchemaException("Luca: unknown schema namespace [{$alias}].");
    }

    public static function alias(string $key): string
    {
        return strstr($key, '.', true) ?: $key;
    }

    public static function localName(string $key): string
    {
        $position = strpos($key, '.');

        return $position === false ? $key : substr($key, $position + 1);
    }

    /**
     * @return array{namespaces: array<string, string>, operations: array<string, OperationDefinition>, types: array<string, TypeDefinition>}
     */
    public function toArray(): array
    {
        return [
            'namespaces' => $this->namespaces,
            'operations' => $this->operations,
            'types' => $this->types,
        ];
    }

    /**
     * " Did you mean [X]?" for the closest candidate, or an empty string.
     *
     * @param  list<string>  $candidates
     */
    public static function suggest(string $given, array $candidates): string
    {
        $best = null;
        $distance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            if (strcasecmp($candidate, $given) === 0) {
                return " Did you mean [{$candidate}]?";
            }

            $d = levenshtein(strtolower($given), strtolower($candidate));

            if ($d < $distance) {
                [$best, $distance] = [$candidate, $d];
            }
        }

        return $best !== null && $distance <= max(2, intdiv(strlen($given), 4))
            ? " Did you mean [{$best}]?"
            : '';
    }

    /**
     * @param  TypeDefinition  $current
     * @param  TypeDefinition  $overlay
     * @return TypeDefinition
     */
    private function mergeComplex(array $current, array $overlay): array
    {
        $fields = $current['fields'] ?? [];

        foreach ($overlay['fields'] ?? [] as $name => $type) {
            $fields = self::insertOrdinal($fields, (string) $name, $type);
        }

        $merged = ['kind' => 'complex', 'fields' => $fields];

        if (array_key_exists('base', $overlay)) {
            $merged['base'] = $overlay['base'];
        } elseif (isset($current['base'])) {
            $merged['base'] = $current['base'];
        }

        return $merged;
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private static function insertOrdinal(array $fields, string $name, string $type): array
    {
        if (array_key_exists($name, $fields)) {
            $fields[$name] = $type;

            return $fields;
        }

        $result = [];
        $inserted = false;

        foreach ($fields as $existing => $existingType) {
            if (! $inserted && strcmp((string) $existing, $name) > 0) {
                $result[$name] = $type;
                $inserted = true;
            }

            $result[(string) $existing] = $existingType;
        }

        if (! $inserted) {
            $result[$name] = $type;
        }

        return $result;
    }
}
