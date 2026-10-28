<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura;

use Aenzenith\LucaEFatura\Exceptions\InvalidConfiguration;

final class ConnectionConfig
{
    /**
     * @param  array<string, string>  $services  service name => path under the base URL
     */
    public function __construct(
        public readonly string $name,
        public readonly string $environment,
        public readonly string $baseUrl,
        public readonly array $services,
        public readonly ?Company $company,
        public readonly int $timeout = 60,
        public readonly int $connectTimeout = 10,
        public readonly bool $verify = true,
        public readonly ?string $proxy = null,
        public readonly int $retryTimes = 2,
        public readonly int $retrySleepMs = 500,
    ) {}

    /**
     * @param  array<string, mixed>  $config  one entry of luca.connections
     */
    public static function fromArray(string $name, array $config): self
    {
        $environment = (string) ($config['environment'] ?? 'test');

        /** @var array<string, string> $urls */
        $urls = (array) ($config['base_urls'] ?? []);

        if (! isset($urls[$environment]) || $urls[$environment] === '') {
            throw new InvalidConfiguration(
                "Luca: connection [{$name}] has no base URL for environment [{$environment}]. "
                .'Expected one of: '.implode(', ', array_keys($urls)).'.'
            );
        }

        /** @var array{tax_code?: string|null, vendor_number?: string|null} $company */
        $company = (array) ($config['company'] ?? []);
        $taxCode = trim((string) ($company['tax_code'] ?? ''));

        /** @var array{times?: int, sleep_ms?: int} $retry */
        $retry = (array) ($config['retry'] ?? []);

        $proxy = $config['proxy'] ?? null;

        try {
            $scoped = $taxCode === '' ? null : new Company($taxCode, $company['vendor_number'] ?? null);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidConfiguration("Luca: connection [{$name}] company: {$e->getMessage()}", 0, $e);
        }

        /** @var array<string, string> $services */
        $services = (array) ($config['services'] ?? []);

        return new self(
            name: $name,
            environment: $environment,
            baseUrl: rtrim($urls[$environment], '/'),
            services: $services,
            company: $scoped,
            timeout: (int) ($config['timeout'] ?? 60),
            connectTimeout: (int) ($config['connect_timeout'] ?? 10),
            verify: (bool) ($config['verify'] ?? true),
            proxy: is_string($proxy) && $proxy !== '' ? $proxy : null,
            retryTimes: max(0, (int) ($retry['times'] ?? 2)),
            retrySleepMs: max(0, (int) ($retry['sleep_ms'] ?? 500)),
        );
    }

    public function url(string $service): string
    {
        $path = $this->services[$service] ?? throw new InvalidConfiguration(
            "Luca: connection [{$this->name}] has no path for service [{$service}] (luca.connections.{$this->name}.services)."
        );

        return $this->baseUrl.'/'.ltrim($path, '/');
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
}
