<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Modules;

/**
 * Company-level information: credit balance, portal users, sub-dealers.
 */
class Account extends Module
{
    /**
     * Remaining document credits ("kontör") of the company.
     */
    public function balance(): int
    {
        $balance = $this->call('GetCompanyBalance')->get('Balance');

        return is_numeric($balance) ? (int) $balance : 0;
    }

    /**
     * Whether a portal user (TCKN + password) belongs to the company.
     */
    public function validateUser(string $userTaxCode, string $password): bool
    {
        return $this->connection->attempt('ValidateUserCompany', [
            'UserPassword' => $password,
            'UserTaxCode' => $userTaxCode,
        ])->successful();
    }

    /**
     * Sub-dealers ("alt bayi") of the company, as read.
     *
     * @return array<string, mixed>
     */
    public function vendors(): array
    {
        $data = $this->call('GetCompanyVendor')->data();
        unset($data['Result'], $data['ErrorMessage']);

        return $data;
    }
}
