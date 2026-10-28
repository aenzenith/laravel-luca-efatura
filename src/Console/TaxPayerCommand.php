<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Console;

use Aenzenith\LucaEFatura\Exceptions\LucaException;
use Aenzenith\LucaEFatura\Luca;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class TaxPayerCommand extends Command
{
    protected $signature = 'luca:taxpayer
        {taxCode : VKN or TCKN to look up}
        {--despatch : Look up the e-İrsaliye registration instead of e-Fatura}
        {--connection= : Connection name (default: luca.default)}';

    protected $description = 'Show whether a VKN/TCKN is registered for e-Fatura (or e-İrsaliye) and its aliases';

    public function handle(Luca $luca): int
    {
        $connection = $this->option('connection');
        $taxPayers = $luca->connection(is_string($connection) && $connection !== '' ? $connection : null)->taxPayers();
        $taxCode = $this->argument('taxCode');

        try {
            $taxPayer = $this->option('despatch') ? $taxPayers->findDespatch($taxCode) : $taxPayers->find($taxCode);
        } catch (InvalidArgumentException|LucaException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($taxPayer === null) {
            $this->components->warn("{$taxCode} is not registered".($this->option('despatch') ? ' for e-İrsaliye.' : ' for e-Fatura; send e-Arşiv.'));

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Tax code', $taxPayer->taxCode);
        $this->components->twoColumnDetail('Name', $taxPayer->name ?? '-');
        $this->components->twoColumnDetail('Type', $taxPayer->type ?? '-');
        $this->components->twoColumnDetail('Registered', $taxPayer->registeredAt?->toDateTimeString() ?? '-');

        foreach ($taxPayer->inboxTags as $tag) {
            $this->components->twoColumnDetail('Inbox alias', $tag);
        }

        return self::SUCCESS;
    }
}
