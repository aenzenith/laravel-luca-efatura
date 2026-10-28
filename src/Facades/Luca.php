<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Facades;

use Aenzenith\LucaEFatura\Company;
use Aenzenith\LucaEFatura\Connection;
use Aenzenith\LucaEFatura\Luca as Manager;
use Aenzenith\LucaEFatura\Modules\Account;
use Aenzenith\LucaEFatura\Modules\AddressBook;
use Aenzenith\LucaEFatura\Modules\Despatches;
use Aenzenith\LucaEFatura\Modules\Documents;
use Aenzenith\LucaEFatura\Modules\EArchive;
use Aenzenith\LucaEFatura\Modules\EInvoices;
use Aenzenith\LucaEFatura\Modules\Esmm;
use Aenzenith\LucaEFatura\Modules\Products;
use Aenzenith\LucaEFatura\Modules\TaxPayers;
use Aenzenith\LucaEFatura\Result;
use Aenzenith\LucaEFatura\Schema\Schema;
use Aenzenith\LucaEFatura\Testing\FakeTransport;
use Aenzenith\LucaEFatura\Transport\Transport;
use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Connection connection(?string $name = null)
 * @method static Connection company(Company|string $taxCode, ?string $vendorNumber = null)
 * @method static Manager extend(string $name, string|Closure $concrete)
 * @method static Manager extendSchema(array<string, mixed> $definition)
 * @method static Schema schema()
 * @method static Manager useTransport(?Transport $transport)
 * @method static void purge(?string $name = null)
 * @method static Result call(string $operation, array<string, mixed> $payload = [])
 * @method static Result attempt(string $operation, array<string, mixed> $payload = [])
 * @method static object module(string $name)
 * @method static EInvoices eInvoices()
 * @method static EArchive eArchive()
 * @method static Despatches despatches()
 * @method static Esmm esmm()
 * @method static TaxPayers taxPayers()
 * @method static AddressBook addressBook()
 * @method static Products products()
 * @method static Account account()
 * @method static Documents documents()
 * @method static Company|null currentCompany()
 *
 * @see Manager
 */
final class Luca extends Facade
{
    /**
     * Swap the transport for a fake and return it.
     *
     * @param  array<string, mixed>  $stubs
     */
    public static function fake(array $stubs = []): FakeTransport
    {
        /** @var Manager $manager */
        $manager = self::getFacadeRoot();

        return $manager->fake($stubs);
    }

    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
