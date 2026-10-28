<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura;

use Aenzenith\LucaEFatura\Exceptions\InvalidConfiguration;
use Aenzenith\LucaEFatura\Modules\Account;
use Aenzenith\LucaEFatura\Modules\AddressBook;
use Aenzenith\LucaEFatura\Modules\Despatches;
use Aenzenith\LucaEFatura\Modules\Documents;
use Aenzenith\LucaEFatura\Modules\EArchive;
use Aenzenith\LucaEFatura\Modules\EInvoices;
use Aenzenith\LucaEFatura\Modules\Esmm;
use Aenzenith\LucaEFatura\Modules\Products;
use Aenzenith\LucaEFatura\Modules\TaxPayers;
use Aenzenith\LucaEFatura\Schema\Schema;
use Aenzenith\LucaEFatura\Soap\EnvelopeBuilder;
use Aenzenith\LucaEFatura\Soap\ResponseParser;
use Aenzenith\LucaEFatura\Soap\ValueCodec;
use Aenzenith\LucaEFatura\Support\Redactor;
use Aenzenith\LucaEFatura\Testing\FakeTransport;
use Aenzenith\LucaEFatura\Transport\HttpTransport;
use Aenzenith\LucaEFatura\Transport\Transport;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;

/**
 * Entry point: hands out connections and keeps what they share (schema,
 * modules, transport override).
 *
 *   Luca::eArchive()->sendXml($ubl);                         // default connection and company
 *   Luca::company('1234567801')->eInvoices()->find($ettn);   // another tenant
 *   Luca::connection('production')->account()->balance();
 *
 * @mixin Connection
 */
class Luca
{
    /**
     * @var array<string, class-string|Closure(Connection): object>
     */
    private array $modules = [
        'eInvoices' => EInvoices::class,
        'eArchive' => EArchive::class,
        'despatches' => Despatches::class,
        'esmm' => Esmm::class,
        'taxPayers' => TaxPayers::class,
        'addressBook' => AddressBook::class,
        'products' => Products::class,
        'account' => Account::class,
        'documents' => Documents::class,
    ];

    /**
     * @var array<string, Connection>
     */
    private array $connections = [];

    private ?Schema $schema = null;

    /**
     * @var list<array<string, mixed>>
     */
    private array $schemaExtensions = [];

    private ?Transport $transport = null;

    public function __construct(
        private readonly Container $app,
    ) {}

    public function connection(?string $name = null): Connection
    {
        $name ??= $this->defaultConnection();

        return $this->connections[$name] ??= $this->make($name);
    }

    /**
     * The default connection acting for another company.
     */
    public function company(Company|string $taxCode, ?string $vendorNumber = null): Connection
    {
        return $this->connection()->company($taxCode, $vendorNumber);
    }

    public function defaultConnection(): string
    {
        return (string) $this->config('default', 'luca');
    }

    /**
     * Register or replace a module. It becomes available as
     * $connection->module($name) on every connection.
     *
     * @param  class-string|Closure(Connection): object  $concrete
     */
    public function extend(string $name, string|Closure $concrete): static
    {
        $this->modules[$name] = $concrete;
        $this->connections = [];

        return $this;
    }

    /**
     * Add definitions to the schema (same shape as the schema files).
     *
     * @param  array<string, mixed>  $definition
     */
    public function extendSchema(array $definition): static
    {
        $this->schemaExtensions[] = $definition;
        $this->schema = null;
        $this->connections = [];

        return $this;
    }

    public function schema(): Schema
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $schema = Schema::bundled();

        $compiled = $this->config('schema.path');

        if (is_string($compiled) && $compiled !== '' && is_file($compiled)) {
            $schema = $schema->merge(Schema::load($compiled), replace: true);
        }

        $extend = $this->config('schema.extend', []);

        foreach ([is_array($extend) ? $extend : [], ...$this->schemaExtensions] as $definition) {
            if ($definition !== []) {
                $schema = $schema->merge($definition);
            }
        }

        return $this->schema = $schema;
    }

    /**
     * Send every call of every connection through this transport.
     */
    public function useTransport(?Transport $transport): static
    {
        $this->transport = $transport;
        $this->connections = [];

        return $this;
    }

    /**
     * Replace the transport with a fake and return it for assertions.
     *
     * @param  array<string, mixed>  $stubs  operation => stub, see FakeTransport
     */
    public function fake(array $stubs = []): FakeTransport
    {
        $fake = new FakeTransport($stubs);
        $this->useTransport($fake);

        return $fake;
    }

    /**
     * Forget resolved connections (after a config change).
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->connections = [];
            $this->schema = null;

            return;
        }

        unset($this->connections[$name]);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->connection()->{$method}(...$arguments);
    }

    private function make(string $name): Connection
    {
        $settings = $this->config("connections.{$name}");

        if (! is_array($settings)) {
            throw new InvalidConfiguration("Luca: connection [{$name}] is not configured (luca.connections.{$name}).");
        }

        /** @var array<string, mixed> $settings */
        $config = ConnectionConfig::fromArray($name, $settings);
        $schema = $this->schema();
        $codec = $this->codec();

        /** @var Factory $http */
        $http = $this->app->make(Factory::class);

        if ($this->transport instanceof FakeTransport) {
            $this->transport->useBuilder(new EnvelopeBuilder($schema, $codec, strict: false));
        }

        /** @var list<string> $redact */
        $redact = (array) $this->config('logging.redact', ['UserPassword', 'Password']);

        return new Connection(
            config: $config,
            schema: $schema,
            transport: $this->transport ?? new HttpTransport($http, $config),
            builder: new EnvelopeBuilder($schema, $codec, (bool) $this->config('schema.strict', true)),
            parser: new ResponseParser($schema, $codec),
            modules: $this->modules,
            guardSupplier: (bool) $this->config('guards.supplier_matches_company', true),
            events: $this->app->bound('events') ? $this->app->make(Dispatcher::class) : null,
            logger: $this->logger(),
            logBodies: (bool) $this->config('logging.bodies', false),
            redactor: new Redactor($redact),
            forceHttpsLinks: (bool) $this->config('viewer_link.force_https', true),
        );
    }

    private function codec(): ValueCodec
    {
        return new ValueCodec((string) $this->config('timezone', 'Europe/Istanbul'));
    }

    private function logger(): ?LoggerInterface
    {
        if (! (bool) $this->config('logging.enabled', false) || ! $this->app->bound('log')) {
            return null;
        }

        /** @var LogManager $log */
        $log = $this->app->make('log');
        $channel = $this->config('logging.channel');

        return is_string($channel) && $channel !== '' ? $log->channel($channel) : $log->channel();
    }

    private function config(string $key, mixed $default = null): mixed
    {
        /** @var Repository $config */
        $config = $this->app->make('config');

        return $config->get('luca.'.$key, $default);
    }
}
