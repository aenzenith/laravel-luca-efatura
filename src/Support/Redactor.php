<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Support;

/**
 * Makes payloads and results safe to log: secrets become "***", document
 * bodies (UBL, PDF, HTML) become their size.
 */
final class Redactor
{
    private const BINARY_SUFFIXES = ['Content', 'Pdf', 'Html', 'Xml', 'File'];

    private const BINARY_KEYS = ['ESMM'];

    /**
     * @param  list<string>  $secrets  keys whose values are replaced by "***"
     */
    public function __construct(
        private readonly array $secrets = ['UserPassword', 'Password'],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            $key = (string) $key;

            if ($this->isSecret($key)) {
                $data[$key] = '***';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redact($value);

                continue;
            }

            if (is_string($value) && $this->isBinary($key)) {
                $data[$key] = '[binary '.strlen($value).' bytes]';
            }
        }

        return $data;
    }

    private function isSecret(string $key): bool
    {
        foreach ($this->secrets as $secret) {
            if (strcasecmp($key, $secret) === 0) {
                return true;
            }
        }

        return false;
    }

    private function isBinary(string $key): bool
    {
        if (in_array($key, self::BINARY_KEYS, true)) {
            return true;
        }

        foreach (self::BINARY_SUFFIXES as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
