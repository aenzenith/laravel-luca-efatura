<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Soap;

use Aenzenith\LucaEFatura\Exceptions\SoapFaultException;
use Aenzenith\LucaEFatura\Exceptions\TransportException;
use Aenzenith\LucaEFatura\Schema\Operation;
use Aenzenith\LucaEFatura\Schema\Schema;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Reads a SOAP response into a plain array.
 *
 * With a response type in the schema, members are decoded by their type
 * (base64 to bytes, int, boolean; decimal stays a string) and array types
 * become lists even with one or zero items. Members the schema does not
 * know are still kept, read by shape. Nothing in the response is dropped.
 */
final class ResponseParser
{
    private const PRIMITIVES = ['string', 'int', 'long', 'short', 'decimal', 'double', 'float', 'boolean', 'dateTime', 'base64Binary', 'guid', 'anyType', 'unsignedByte', 'char', 'duration'];

    public function __construct(
        private readonly Schema $schema,
        private readonly ValueCodec $codec,
    ) {}

    /**
     * @return array<string, mixed> members of the operation result
     */
    public function parse(Operation $operation, string $body, ?int $status = null): array
    {
        $body = $this->extractEnvelope($body);

        if (trim($body) === '') {
            throw new TransportException("Luca: [{$operation->name}] empty response (HTTP {$status}).", $operation->name, $status, $body);
        }

        // SOAP forbids a DTD; refusing one keeps entity expansion out, which
        // matters because LIBXML_PARSEHUGE (needed for PDFs inside text
        // nodes over 10 MB) lifts libxml's own limits.
        if (stripos($body, '<!DOCTYPE') !== false) {
            throw new TransportException("Luca: [{$operation->name}] response carries a DTD; refused.", $operation->name, $status, $body);
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($body, LIBXML_NONET | LIBXML_PARSEHUGE);
            $error = libxml_get_last_error();
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $document->documentElement === null) {
            $reason = $error !== false ? trim($error->message) : 'unreadable XML';

            throw new TransportException(
                "Luca: [{$operation->name}] response is not XML (HTTP {$status}): {$reason}.",
                $operation->name,
                $status,
                $body,
            );
        }

        $soapBody = $this->child($document->documentElement, 'Body');
        $content = $soapBody !== null ? $this->firstElement($soapBody) : null;

        if ($content === null) {
            throw new TransportException("Luca: [{$operation->name}] response has no SOAP body (HTTP {$status}).", $operation->name, $status, $body);
        }

        if ($content->localName === 'Fault') {
            throw $this->fault($operation, $content, $status, $body);
        }

        $result = $content->localName === $operation->name.'Response'
            ? $this->child($content, $operation->name.'Result')
            : null;

        if ($result === null) {
            throw new TransportException(
                "Luca: [{$operation->name}] unexpected response element [{$content->localName}] (HTTP {$status}).",
                $operation->name,
                $status,
                $body,
            );
        }

        $data = $operation->response !== null && $this->schema->hasType($operation->response)
            ? $this->complex($result, $operation->response)
            : $this->loose($result);

        return is_array($data) ? $data : [];
    }

    /**
     * WCF answers MTOM/multipart when asked to; take the envelope part.
     */
    private function extractEnvelope(string $body): string
    {
        $start = stripos($body, '<');

        if ($start === false) {
            return $body;
        }

        if ($start > 0 || ! str_contains(substr($body, 0, 200), 'Envelope')) {
            if (preg_match('/<([A-Za-z0-9_]+:)?Envelope\b.*<\/([A-Za-z0-9_]+:)?Envelope>/s', $body, $match) === 1) {
                return $match[0];
            }
        }

        return $body;
    }

    private function fault(Operation $operation, DOMElement $fault, ?int $status, string $body): SoapFaultException
    {
        // SOAP 1.1: faultcode / faultstring / detail
        // SOAP 1.2: Code/Value / Reason/Text / Detail
        $code = $this->child($fault, 'faultcode')->textContent
            ?? $this->child($this->child($fault, 'Code'), 'Value')->textContent
            ?? '';

        $message = $this->child($fault, 'faultstring')->textContent
            ?? $this->child($this->child($fault, 'Reason'), 'Text')->textContent
            ?? '';

        $detail = $this->child($fault, 'detail') ?? $this->child($fault, 'Detail');

        return new SoapFaultException(
            $operation->name,
            trim($code),
            trim($message),
            $detail !== null ? trim($detail->textContent) : null,
            $status,
            $body,
        );
    }

    private function decode(DOMElement $element, string $type): mixed
    {
        if ($this->isNil($element)) {
            return null;
        }

        if (Schema::isPrimitive($type)) {
            return $this->codec->decode($type, $element->textContent);
        }

        if (! $this->schema->hasType($type)) {
            return $this->loose($element);
        }

        $definition = $this->schema->type($type);

        return match ($definition['kind']) {
            'complex' => $this->complex($element, $type),
            'array' => array_map(
                fn (DOMElement $item): mixed => $this->decode($item, $definition['of'] ?? 'string'),
                $this->elements($element),
            ),
            default => $element->textContent,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function complex(DOMElement $element, string $type): array
    {
        $types = array_column($this->schema->fields($type), 'type', 'name');
        $data = [];

        foreach ($this->elements($element) as $child) {
            $name = $child->localName ?? '';

            $data[$name] = isset($types[$name])
                ? $this->decode($child, $types[$name])
                : $this->loose($child);
        }

        return $data;
    }

    /**
     * Reads an element no type describes, by shape:
     *  - i:nil → null
     *  - no child elements → its text
     *  - children that form a list → list
     *  - otherwise → array keyed by child name
     */
    private function loose(DOMElement $element): mixed
    {
        if ($this->isNil($element)) {
            return null;
        }

        $children = $this->elements($element);

        if ($children === []) {
            return $element->textContent;
        }

        if ($this->isList($element, $children)) {
            return array_map(fn (DOMElement $child): mixed => $this->loose($child), $children);
        }

        $data = [];

        foreach ($children as $child) {
            $data[$child->localName ?? ''] = $this->loose($child);
        }

        return $data;
    }

    /**
     * @param  list<DOMElement>  $children
     */
    private function isList(DOMElement $parent, array $children): bool
    {
        $name = $children[0]->localName ?? '';

        foreach ($children as $child) {
            if ($child->localName !== $name) {
                return false;
            }
        }

        $parentName = $parent->localName ?? '';

        return count($children) > 1
            || $children[0]->namespaceURI === EnvelopeBuilder::ARRAYS
            || in_array($name, self::PRIMITIVES, true)
            || str_starts_with($parentName, 'ArrayOf')
            || ($parentName !== $name && str_starts_with($parentName, $name));
    }

    private function isNil(DOMElement $element): bool
    {
        return $element->getAttributeNS(EnvelopeBuilder::XSI, 'nil') === 'true';
    }

    private function child(?DOMNode $parent, string $localName): ?DOMElement
    {
        if ($parent === null) {
            return null;
        }

        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $localName) {
                return $node;
            }
        }

        return null;
    }

    private function firstElement(DOMNode $parent): ?DOMElement
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(DOMNode $parent): array
    {
        $elements = [];

        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
