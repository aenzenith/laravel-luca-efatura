<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Soap;

use Aenzenith\LucaEFatura\Exceptions\SchemaException;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;

/**
 * Converts PHP values to XSD lexical values and back.
 *
 * - base64Binary takes and returns raw bytes; the codec does the base64.
 * - decimal stays a string on the way back, so amounts never pass through
 *   a float.
 * - dateTime is written in the configured timezone without an offset
 *   ("2026-10-28T14:05:00"), as Luca's samples do.
 */
final class ValueCodec
{
    private const INTEGERS = ['int', 'long', 'short', 'byte', 'unsignedByte', 'unsignedShort', 'unsignedInt', 'unsignedLong', 'integer'];

    private const DECIMALS = ['decimal', 'double', 'float'];

    private readonly DateTimeZone $timezone;

    public function __construct(string $timezone = 'Europe/Istanbul')
    {
        $this->timezone = new DateTimeZone($timezone);
    }

    /**
     * @param  string  $type  XSD primitive name, or an enum type key
     */
    public function encode(string $type, mixed $value, string $path): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return match (true) {
            $type === 'boolean' => $this->encodeBoolean($value, $path),
            in_array($type, self::INTEGERS, true) => $this->encodeInteger($value, $path),
            in_array($type, self::DECIMALS, true) => $this->encodeDecimal($value, $path),
            $type === 'dateTime' => $this->encodeDateTime($value, $path),
            $type === 'base64Binary' => base64_encode($this->scalar($value, $path)),
            default => $this->scalar($value, $path),
        };
    }

    public function decode(string $type, string $value): mixed
    {
        return match (true) {
            $type === 'boolean' => $value === 'true' || $value === '1',
            in_array($type, self::INTEGERS, true) => is_numeric($value) ? (int) $value : $value,
            $type === 'base64Binary' => $this->decodeBase64($value),
            default => $value,
        };
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    private function encodeBoolean(mixed $value, string $path): string
    {
        return match ($value) {
            true, 1, '1', 'true' => 'true',
            false, 0, '0', 'false' => 'false',
            default => throw new SchemaException("Luca: [{$path}] expects a boolean, got ".get_debug_type($value).'.'),
        };
    }

    private function encodeInteger(mixed $value, string $path): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return $value;
        }

        throw new SchemaException("Luca: [{$path}] expects an integer, got ".get_debug_type($value).'.');
    }

    private function encodeDecimal(mixed $value, string $path): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new SchemaException("Luca: [{$path}] expects a finite number.");
            }

            $string = (string) $value;

            return str_contains($string, 'E')
                ? rtrim(rtrim(sprintf('%.12F', $value), '0'), '.')
                : $string;
        }

        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        throw new SchemaException(
            "Luca: [{$path}] expects a decimal (int, float or a string like \"1234.56\"), got "
            .(is_string($value) ? "\"{$value}\"" : get_debug_type($value)).'.'
        );
    }

    private function encodeDateTime(mixed $value, string $path): string
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)
                ->setTimezone($this->timezone)
                ->format('Y-m-d\TH:i:s');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d+)?)?([+-]\d{2}:\d{2}|Z)?)?$/', $value) === 1) {
            return $value;
        }

        throw new SchemaException("Luca: [{$path}] expects a DateTimeInterface or an ISO 8601 string, got ".get_debug_type($value).'.');
    }

    private function scalar(mixed $value, string $path): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new SchemaException("Luca: [{$path}] expects a string, got ".get_debug_type($value).'.');
    }

    private function decodeBase64(string $value): string
    {
        $decoded = base64_decode(preg_replace('/\s+/', '', $value) ?? $value, true);

        return $decoded === false ? $value : $decoded;
    }
}
