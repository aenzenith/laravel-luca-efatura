<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Modules;

/**
 * Address codes (il, ilçe, vergi dairesi) and the company's address book
 * (AddressBookService).
 *
 * Response shapes of this service are not in the WSDL Luca published; the
 * lists are returned as read, one array per record.
 */
class AddressBook extends Module
{
    /**
     * @return list<array<string, mixed>>
     */
    public function cities(): array
    {
        return self::records($this->call('GetCityList'), 'CityCodeList', 'CityList', 'Cities');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function towns(): array
    {
        return self::records($this->call('GetTownList'), 'TownCodeList', 'TownList', 'Towns');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function taxOffices(): array
    {
        return self::records($this->call('GetTaxOfficeList'), 'TaxOffice', 'TaxOfficeList', 'TaxOffices');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchTaxOffices(int|string|null $cityCode = null, ?string $name = null): array
    {
        return self::records(
            $this->call('GetAllBySearchTaxOfficeNameAndCityCode', ['CityCode' => $cityCode, 'TaxOfficeName' => $name]),
            'TaxOffice', 'TaxOfficeList', 'TaxOffices',
        );
    }

    /**
     * @param  array<string, mixed>  $filter  GetAddressBookRequest members
     * @return list<array<string, mixed>>
     */
    public function entries(array $filter = []): array
    {
        return self::records($this->call('GetAddressBook', $filter), 'AddressBookEntires', 'AddressBookEntries');
    }

    /**
     * @param  list<array<string, mixed>>  $entries  AddressBookEntry members
     */
    public function save(array $entries, int|string|null $companyType = null): void
    {
        $this->call('SaveAddressBookEntry', ['AddressBookEntires' => $entries, 'CompanyType' => $companyType]);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    public function delete(array $entries): void
    {
        $this->call('DeleteAddressBookEntry', ['AddressBookEntires' => $entries]);
    }
}
