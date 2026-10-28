<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Modules;

use Aenzenith\LucaEFatura\Company;
use Aenzenith\LucaEFatura\Data\Page;
use Aenzenith\LucaEFatura\Data\TaxPayer;
use Aenzenith\LucaEFatura\Result;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * GİB taxpayer lookups (AddressBookService).
 */
class TaxPayers extends Module
{
    /**
     * İşNet answers Result=Failed with one of these when the tax code is
     * not registered; every other failure is an error and is thrown.
     */
    private const NOT_FOUND = '/bulunamad|not found|kay[ıi]tl[ıi] de[ğg]il|mevcut de[ğg]il/iu';

    /**
     * The e-Fatura registration of a VKN/TCKN, or null when it is not
     * registered (send e-Arşiv to it instead).
     */
    public function find(string $taxCode): ?TaxPayer
    {
        return $this->lookup('GetTaxPayer', $taxCode);
    }

    /**
     * The e-İrsaliye registration of a VKN/TCKN.
     */
    public function findDespatch(string $taxCode): ?TaxPayer
    {
        return $this->lookup('GetDespatchTaxPayer', $taxCode);
    }

    public function isEInvoiceUser(string $taxCode): bool
    {
        return $this->find($taxCode) !== null;
    }

    /**
     * Receiver aliases ("urn:mail:defaultpk@...") e-Fatura can be sent to.
     *
     * @return list<string>
     */
    public function aliases(string $taxCode): array
    {
        return $this->find($taxCode)->inboxTags ?? [];
    }

    /**
     * @return Page<TaxPayer>
     */
    public function search(
        ?string $name = null,
        int $page = 1,
        int $perPage = 50,
        ?DateTimeInterface $registeredFrom = null,
        ?DateTimeInterface $registeredUntil = null,
    ): Page {
        $result = $this->call('GetTaxPayerWithPaging', [
            'MaxRegistrationDate' => $registeredUntil,
            'MinRegistrationDate' => $registeredFrom,
            'PagingRequest' => self::paging($page, $perPage),
            'TaxPayerName' => $name,
        ]);

        return Page::from(TaxPayer::listFrom($result->data()), $result->get('PagingResponse'), $page, $perPage);
    }

    public static function isNotFoundMessage(?string $message): bool
    {
        return $message !== null && preg_match(self::NOT_FOUND, $message) === 1;
    }

    private function lookup(string $operation, string $taxCode): ?TaxPayer
    {
        $taxCode = trim($taxCode);

        // An empty TaxPayerTaxCode lists every registered taxpayer.
        if (! Company::isTaxCode($taxCode)) {
            throw new InvalidArgumentException("Luca: [{$taxCode}] is not a VKN (10 digits) or TCKN (11 digits).");
        }

        $result = $this->connection->attempt($operation, ['TaxPayerTaxCode' => $taxCode]);

        if ($result->failed()) {
            if (self::isNotFoundMessage($result->errorMessage())) {
                return null;
            }

            $result->throw();
        }

        return $this->matchingTaxPayer($result, $taxCode);
    }

    private function matchingTaxPayer(Result $result, string $taxCode): ?TaxPayer
    {
        return TaxPayer::merge(array_values(array_filter(
            TaxPayer::listFrom($result->data()),
            static fn (TaxPayer $taxPayer): bool => $taxPayer->taxCode === $taxCode,
        )));
    }
}
