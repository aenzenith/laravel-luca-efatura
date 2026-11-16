<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Console;

use Aenzenith\LucaEFatura\Exceptions\ConnectionException;
use Aenzenith\LucaEFatura\Exceptions\LucaException;
use Aenzenith\LucaEFatura\Luca;
use Illuminate\Console\Command;

final class CheckCommand extends Command
{
    protected $signature = 'luca:check
        {--connection= : Connection name (default: luca.default)}
        {--company= : VKN/TCKN to check instead of the configured company}';

    protected $description = 'Check that this server can reach Luca and read the company balance';

    public function handle(Luca $luca): int
    {
        $connection = $luca->connection($this->stringOption('connection'));
        $company = $this->stringOption('company');

        if ($company !== null) {
            $connection = $connection->company($company);
        }

        $this->components->twoColumnDetail('Connection', $connection->name());
        $this->components->twoColumnDetail('Environment', $connection->config->environment);
        $this->components->twoColumnDetail('InvoiceService', $connection->config->url('InvoiceService'));
        $this->components->twoColumnDetail('Company', (string) ($connection->currentCompany() ?? '-'));
        $this->components->twoColumnDetail('Proxy', $connection->config->proxy ?? '-');

        // The balance is read for a company; without one nothing is sent to Luca.
        if ($connection->currentCompany() === null) {
            $this->components->error('No company to check. Pass --company=<VKN or TCKN>, or set the company tax code of this connection (LUCA_COMPANY_TAX_CODE).');

            return self::INVALID;
        }

        try {
            $balance = $connection->account()->balance();
        } catch (ConnectionException $e) {
            $this->components->error('No response from Luca.');
            $this->line($e->getMessage());
            $this->newLine();
            $this->line('Luca serves only the IP addresses on your Luca application; others are reset or time out.');
            $this->line('  curl -s https://api.ipify.org   outgoing IP of this server, compare it with the registered one');
            $this->line('  curl -sI https://example.com    whether this server can open HTTPS connections at all');

            return self::FAILURE;
        } catch (LucaException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Balance', (string) $balance);
        $this->components->info('Luca answered.');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
