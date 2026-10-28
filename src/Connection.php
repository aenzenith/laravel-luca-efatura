<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura;

use Aenzenith\LucaEFatura\Events\RequestFailed;
use Aenzenith\LucaEFatura\Events\RequestSending;
use Aenzenith\LucaEFatura\Events\ResponseReceived;
use Aenzenith\LucaEFatura\Exceptions\ConnectionException;
use Aenzenith\LucaEFatura\Exceptions\MissingCompanyException;
use Aenzenith\LucaEFatura\Exceptions\OperationFailedException;
use Aenzenith\LucaEFatura\Exceptions\TenantMismatchException;
use Aenzenith\LucaEFatura\Exceptions\TransportException;
use Aenzenith\LucaEFatura\Exceptions\UnknownModuleException;
use Aenzenith\LucaEFatura\Guards\SupplierGuard;
use Aenzenith\LucaEFatura\Modules\Account;
use Aenzenith\LucaEFatura\Modules\AddressBook;
use Aenzenith\LucaEFatura\Modules\Despatches;
use Aenzenith\LucaEFatura\Modules\Documents;
use Aenzenith\LucaEFatura\Modules\EArchive;
use Aenzenith\LucaEFatura\Modules\EInvoices;
use Aenzenith\LucaEFatura\Modules\Esmm;
use Aenzenith\LucaEFatura\Modules\Products;
use Aenzenith\LucaEFatura\Modules\TaxPayers;
use Aenzenith\LucaEFatura\Schema\Operation;
use Aenzenith\LucaEFatura\Schema\Schema;
use Aenzenith\LucaEFatura\Soap\EnvelopeBuilder;
use Aenzenith\LucaEFatura\Soap\ResponseParser;
use Aenzenith\LucaEFatura\Support\Redactor;
use Aenzenith\LucaEFatura\Transport\SoapRequest;
use Aenzenith\LucaEFatura\Transport\SoapResponse;
use Aenzenith\LucaEFatura\Transport\Transport;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Sleep;
use Psr\Log\LoggerInterface;

/**
 * One Luca endpoint set, optionally scoped to a company.
 *
 * Every call goes through the same pipeline:
 *   1. the operation and its request type come from the schema;
 *   2. CompanyTaxCode / CompanyVendorNumber are filled from the scoped
 *      company, and a payload naming another company is refused;
 *   3. XML documents must be issued by the scoped company (SupplierGuard);
 *   4. the envelope is built from the schema (unknown fields refused);
 *   5. read operations are retried on connection failures, writes never;
 *   6. the response is parsed into a Result.
 * Steps 1–4 throw before anything leaves the process.
 *
 * Connections are immutable: company() returns a scoped copy.
 */
final class Connection
{
    /**
     * @var array<string, object>
     */
    private array $resolved = [];

    /**
     * @param  array<string, class-string|Closure(Connection): object>  $modules
     */
    public function __construct(
        public readonly ConnectionConfig $config,
        private readonly Schema $schema,
        private readonly Transport $transport,
        private readonly EnvelopeBuilder $builder,
        private readonly ResponseParser $parser,
        private readonly array $modules = [],
        private readonly bool $guardSupplier = true,
        private readonly ?Dispatcher $events = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $logBodies = false,
        private readonly Redactor $redactor = new Redactor,
        private readonly bool $forceHttpsLinks = true,
        private ?Company $company = null,
    ) {
        $this->company ??= $config->company;
    }

    public function __clone()
    {
        $this->resolved = [];
    }

    /**
     * A copy of this connection that acts for the given company.
     */
    public function company(Company|string $taxCode, ?string $vendorNumber = null): self
    {
        $clone = clone $this;
        $clone->company = $taxCode instanceof Company ? $taxCode : new Company($taxCode, $vendorNumber);

        return $clone;
    }

    public function currentCompany(): ?Company
    {
        return $this->company;
    }

    public function name(): string
    {
        return $this->config->name;
    }

    public function schema(): Schema
    {
        return $this->schema;
    }

    public function forcesHttpsLinks(): bool
    {
        return $this->forceHttpsLinks;
    }

    /**
     * Call an operation and require Result=Success.
     *
     * @param  array<string, mixed>  $payload  members of the request type
     *
     * @throws OperationFailedException
     * @throws TransportException
     */
    public function call(string $operation, array $payload = []): Result
    {
        return $this->attempt($operation, $payload)->throw();
    }

    /**
     * Call an operation and return the Result whatever it says. Transport
     * failures and SOAP faults still throw.
     *
     * @param  array<string, mixed>  $payload  members of the request type
     *
     * @throws TransportException
     */
    public function attempt(string $operation, array $payload = []): Result
    {
        $operation = $this->schema->operation($operation);
        $payload = $this->scope($operation, $payload);

        if ($this->guardSupplier) {
            (new SupplierGuard)->check($operation, $payload, $this->company);
        }

        $request = new SoapRequest(
            $operation,
            $this->config->url($operation->service),
            $this->builder->request($operation, $payload),
            $payload,
            $this->company,
        );

        $this->events?->dispatch(new RequestSending($this->config->name, $request));

        $started = hrtime(true);

        try {
            $response = $this->send($request);
            $data = $this->parser->parse($operation, $response->body, $response->status);
        } catch (TransportException $e) {
            $this->events?->dispatch(new RequestFailed($this->config->name, $request, $e));
            $this->log($request, null, $e, $this->elapsed($started));

            throw $e;
        }

        $result = new Result($operation, $data, $response->body, $this->company, $this->elapsed($started));

        $this->events?->dispatch(new ResponseReceived($this->config->name, $request, $result));
        $this->log($request, $result, null, $result->durationMs);

        return $result;
    }

    /**
     * Resolve a module by name: eInvoices, eArchive, despatches, esmm,
     * taxPayers, addressBook, products, account, documents, or one added
     * with Luca::extend().
     */
    public function module(string $name): object
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $concrete = $this->modules[$name] ?? throw UnknownModuleException::named($name);

        return $this->resolved[$name] = $concrete instanceof Closure ? $concrete($this) : new $concrete($this);
    }

    public function eInvoices(): EInvoices
    {
        return $this->typed('eInvoices', EInvoices::class);
    }

    public function eArchive(): EArchive
    {
        return $this->typed('eArchive', EArchive::class);
    }

    public function despatches(): Despatches
    {
        return $this->typed('despatches', Despatches::class);
    }

    public function esmm(): Esmm
    {
        return $this->typed('esmm', Esmm::class);
    }

    public function taxPayers(): TaxPayers
    {
        return $this->typed('taxPayers', TaxPayers::class);
    }

    public function addressBook(): AddressBook
    {
        return $this->typed('addressBook', AddressBook::class);
    }

    public function products(): Products
    {
        return $this->typed('products', Products::class);
    }

    public function account(): Account
    {
        return $this->typed('account', Account::class);
    }

    public function documents(): Documents
    {
        return $this->typed('documents', Documents::class);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function typed(string $name, string $class): object
    {
        $module = $this->module($name);

        if (! $module instanceof $class) {
            throw new UnknownModuleException("Luca: module [{$name}] must be an instance of {$class}.");
        }

        return $module;
    }

    /**
     * Fill the company members of the request and refuse a payload that
     * names a company other than the scoped one.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function scope(Operation $operation, array $payload): array
    {
        $fields = array_column($this->schema->fields($operation->request), 'name');

        if (in_array('CompanyTaxCode', $fields, true)) {
            $given = $this->filled($payload['CompanyTaxCode'] ?? null);

            if ($this->company === null && $given === null) {
                throw MissingCompanyException::for($operation->name);
            }

            if ($this->company !== null && $given !== null && $given !== $this->company->taxCode) {
                throw TenantMismatchException::field($operation->name, 'CompanyTaxCode', $given, $this->company->taxCode);
            }

            $payload['CompanyTaxCode'] = $given ?? $this->company->taxCode;
        }

        if (in_array('CompanyVendorNumber', $fields, true) && $this->company !== null && $this->company->vendorNumber !== null) {
            $given = $this->filled($payload['CompanyVendorNumber'] ?? null);

            if ($given !== null && $given !== $this->company->vendorNumber) {
                throw TenantMismatchException::field($operation->name, 'CompanyVendorNumber', $given, $this->company->vendorNumber);
            }

            $payload['CompanyVendorNumber'] = $this->company->vendorNumber;
        }

        return $payload;
    }

    private function filled(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function send(SoapRequest $request): SoapResponse
    {
        $attempts = $request->operation->isMutating() ? 1 : 1 + $this->config->retryTimes;

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->transport->send($request);
            } catch (ConnectionException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }

                Sleep::for($this->config->retrySleepMs * $attempt)->milliseconds();
            }
        }
    }

    private function elapsed(int|float $started): float
    {
        return round((hrtime(true) - $started) / 1e6, 1);
    }

    private function log(SoapRequest $request, ?Result $result, ?TransportException $error, float $durationMs): void
    {
        if ($this->logger === null) {
            return;
        }

        $context = [
            'connection' => $this->config->name,
            'operation' => $request->operation->name,
            'company' => $request->company !== null ? (string) $request->company : null,
            'duration_ms' => $durationMs,
            'result' => $result !== null ? ($result->successful() ? 'Success' : 'Failed') : 'Error',
        ];

        if ($result?->errorMessage() !== null) {
            $context['error'] = $result->errorMessage();
        }

        if ($error !== null) {
            $context['error'] = $error->getMessage();
        }

        if ($this->logBodies) {
            $context['request'] = $this->redactor->redact($request->payload);

            if ($result !== null) {
                $context['response'] = $this->redactor->redact($result->data());
            }
        }

        $message = 'Luca '.$request->operation->name;

        $error !== null || $result?->failed()
            ? $this->logger->warning($message, $context)
            : $this->logger->info($message, $context);
    }
}
