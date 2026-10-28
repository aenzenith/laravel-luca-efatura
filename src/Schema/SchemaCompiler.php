<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Schema;

use Aenzenith\LucaEFatura\Exceptions\SchemaException;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Compiles a WCF "?singleWsdl" document into a schema definition.
 *
 * WCF writes data contracts in a fixed form, which is all this handles:
 *   - xs:complexType with xs:sequence (optionally inside
 *     xs:complexContent/xs:extension for a base type);
 *   - a complexType whose sequence is one element with
 *     maxOccurs="unbounded" is a collection (ArrayOfX);
 *   - xs:simpleType restrictions with xs:enumeration are enums;
 *   - operations are wrapped: element {Op} holds "request", element
 *     {Op}Response holds "{Op}Result".
 */
final class SchemaCompiler
{
    private const XS = 'http://www.w3.org/2001/XMLSchema';

    private const WSDL = 'http://schemas.xmlsoap.org/wsdl/';

    private const SERIALIZATION = 'http://schemas.microsoft.com/2003/10/Serialization/';

    private const TEMPURI = 'http://tempuri.org/';

    /**
     * Aliases the bundled schema already uses, so compiled keys line up.
     */
    private const KNOWN_ALIASES = [
        'http://schemas.datacontract.org/2004/07/EInvoice.Service.Model' => 'Model',
        'http://schemas.datacontract.org/2004/07/EInvoice.Data.GIB' => 'GIB',
        'http://schemas.datacontract.org/2004/07/EInvoice.Service.Model.DataModels' => 'DataModels',
        'http://schemas.datacontract.org/2004/07/EInvoice.Service.Model.DataTypes.Enums' => 'Enums',
        'http://schemas.datacontract.org/2004/07/EInvoice.Data.GIB.ShipmentPackage' => 'GIBShipmentPackage',
        'http://schemas.microsoft.com/2003/10/Serialization/Arrays' => 'Arrays',
        'http://schemas.datacontract.org/2004/07/EInvoice.Service.Model.DataContract.AddressBook' => 'AddressBook',
    ];

    /**
     * @var array<string, string> namespace URI => alias
     */
    private array $aliases = [];

    /**
     * @param  array<string, string>  $aliases  extra namespace URI => alias pairs
     */
    public function __construct(array $aliases = [])
    {
        $this->aliases = $aliases + self::KNOWN_ALIASES;
    }

    /**
     * @param  string|null  $service  service name; read from the portType ("IInvoiceService") when null
     * @return array{namespaces: array<string, string>, operations: array<string, array{service: string, request: string, response: string|null}>, types: array<string, array<string, mixed>>}
     */
    public function compile(string $wsdl, ?string $service = null): array
    {
        $xpath = $this->load($wsdl);
        $xpath->registerNamespace('xs', self::XS);
        $xpath->registerNamespace('wsdl', self::WSDL);

        $definition = ['namespaces' => [], 'operations' => [], 'types' => []];
        $wrappers = [];

        foreach ($this->nodes($xpath, '//xs:schema') as $schema) {
            $target = $schema->getAttribute('targetNamespace');

            if ($target === self::SERIALIZATION) {
                continue;
            }

            if ($target === self::TEMPURI) {
                foreach ($this->nodes($xpath, 'xs:element', $schema) as $element) {
                    $wrappers[$element->getAttribute('name')] = $element;
                }

                continue;
            }

            $alias = $this->alias($target);
            $definition['namespaces'][$alias] = $target;

            foreach ($this->nodes($xpath, 'xs:complexType[@name]', $schema) as $type) {
                $definition['types'][$alias.'.'.$type->getAttribute('name')] = $this->complexType($xpath, $type);
            }

            foreach ($this->nodes($xpath, 'xs:simpleType[@name]', $schema) as $type) {
                $values = array_map(
                    static fn (DOMElement $value): string => $value->getAttribute('value'),
                    $this->nodes($xpath, './/xs:enumeration', $type),
                );

                if ($values !== []) {
                    $definition['types'][$alias.'.'.$type->getAttribute('name')] = ['kind' => 'enum', 'values' => $values];
                }
            }
        }

        foreach ($this->nodes($xpath, '//wsdl:portType') as $portType) {
            $serviceName = $service ?? (string) preg_replace('/^I(?=[A-Z])/', '', $portType->getAttribute('name'));

            foreach ($this->nodes($xpath, 'wsdl:operation', $portType) as $operation) {
                $name = $operation->getAttribute('name');
                $request = $this->wrappedType($xpath, $wrappers[$name] ?? null, 'request');

                if ($request === null) {
                    continue;
                }

                $definition['operations'][$name] = [
                    'service' => $serviceName,
                    'request' => $request,
                    'response' => $this->wrappedType($xpath, $wrappers[$name.'Response'] ?? null, $name.'Result'),
                ];
            }
        }

        ksort($definition['types'], SORT_STRING);

        return $definition;
    }

    private function load(string $wsdl): DOMXPath
    {
        if (stripos($wsdl, '<!DOCTYPE') !== false) {
            throw new SchemaException('Luca: the WSDL carries a DTD; refused.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($wsdl, LIBXML_NONET | LIBXML_PARSEHUGE);
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new SchemaException('Luca: the WSDL is not well-formed XML.');
        }

        return new DOMXPath($document);
    }

    /**
     * @return array<string, mixed>
     */
    private function complexType(DOMXPath $xpath, DOMElement $type): array
    {
        $extension = $this->nodes($xpath, 'xs:complexContent/xs:extension', $type)[0] ?? null;
        $container = $extension ?? $type;
        $elements = $this->nodes($xpath, 'xs:sequence/xs:element', $container);

        if ($extension === null && count($elements) === 1 && $elements[0]->getAttribute('maxOccurs') === 'unbounded') {
            return [
                'kind' => 'array',
                'item' => $elements[0]->getAttribute('name'),
                'of' => $this->typeName($elements[0], $elements[0]->getAttribute('type')),
            ];
        }

        $fields = [];

        foreach ($elements as $element) {
            $fields[$element->getAttribute('name')] = $this->typeName($element, $element->getAttribute('type'));
        }

        return $extension !== null
            ? ['kind' => 'complex', 'base' => $this->typeName($extension, $extension->getAttribute('base')), 'fields' => $fields]
            : ['kind' => 'complex', 'fields' => $fields];
    }

    private function wrappedType(DOMXPath $xpath, ?DOMElement $wrapper, string $member): ?string
    {
        if ($wrapper === null) {
            return null;
        }

        foreach ($this->nodes($xpath, 'xs:complexType/xs:sequence/xs:element', $wrapper) as $element) {
            if ($element->getAttribute('name') === $member) {
                return $this->typeName($element, $element->getAttribute('type'));
            }
        }

        return null;
    }

    /**
     * "xs:string" → "string", "ser:guid" → "guid", "tns:Invoice" → "Model.Invoice".
     */
    private function typeName(DOMElement $context, string $qname): string
    {
        [$prefix, $local] = str_contains($qname, ':') ? explode(':', $qname, 2) : ['', $qname];
        $uri = $context->lookupNamespaceURI($prefix === '' ? null : $prefix);

        if ($uri === self::XS || $uri === self::SERIALIZATION) {
            return $local;
        }

        if ($uri === null || $uri === '') {
            throw new SchemaException("Luca: WSDL type [{$qname}] uses an undeclared prefix.");
        }

        return $this->alias($uri).'.'.$local;
    }

    private function alias(string $uri): string
    {
        if (isset($this->aliases[$uri])) {
            return $this->aliases[$uri];
        }

        $segment = (string) preg_replace('/[^A-Za-z0-9]/', '', (string) strrchr('/'.str_replace('.', '/', rtrim($uri, '/')), '/'));
        $alias = $segment !== '' ? $segment : 'Ns';
        $candidate = $alias;

        for ($i = 2; in_array($candidate, $this->aliases, true); $i++) {
            $candidate = $alias.$i;
        }

        return $this->aliases[$uri] = $candidate;
    }

    /**
     * @return list<DOMElement>
     */
    private function nodes(DOMXPath $xpath, string $query, ?DOMElement $context = null): array
    {
        $nodes = $xpath->query($query, $context);
        $elements = [];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $elements[] = $node;
                }
            }
        }

        return $elements;
    }

    /**
     * PHP source of a definition, for the compiled schema file.
     *
     * @param  array<string, mixed>  $definition
     */
    public static function export(array $definition, string $source): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\n"
            ."// Compiled by `php artisan luca:schema` from {$source} at ".date('Y-m-d H:i:s')."; do not edit.\n\n"
            .'return '.var_export($definition, true).";\n";
    }
}
