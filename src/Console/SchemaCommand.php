<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Console;

use Aenzenith\LucaEFatura\Exceptions\LucaException;
use Aenzenith\LucaEFatura\Luca;
use Aenzenith\LucaEFatura\Schema\Schema;
use Aenzenith\LucaEFatura\Schema\SchemaCompiler;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Factory;

/**
 * Compiles the live WSDL of every configured service into a schema file.
 * Must run on a server whose IP Luca has registered, or through the
 * connection's proxy.
 */
final class SchemaCommand extends Command
{
    protected $signature = 'luca:schema
        {--connection= : Connection whose endpoints to read (default: luca.default)}
        {--from=* : Read WSDL files instead of fetching ?singleWsdl}
        {--output= : File to write (default: luca.schema.path, else storage/app/luca-schema.php)}
        {--dry-run : Show the differences without writing}';

    protected $description = 'Compile the Luca WSDL into the schema the envelopes are built from';

    public function handle(Luca $luca, Factory $http): int
    {
        $connectionName = $this->option('connection');
        $connection = $luca->connection(is_string($connectionName) && $connectionName !== '' ? $connectionName : null);
        $compiler = new SchemaCompiler;
        $compiled = Schema::fromArray([]);

        try {
            foreach ($this->sources($connection->config->services, $connection->config->url(...)) as $source => $service) {
                $wsdl = is_file($source) ? (string) file_get_contents($source) : $this->fetch($http, $source, $connection->config->proxy, $connection->config->verify);

                /** @phpstan-ignore argument.type */
                $compiled = $compiled->merge($compiler->compile($wsdl, $service));
                $this->components->task($source);
            }
        } catch (HttpConnectionException $e) {
            $this->components->error('No response: '.$e->getMessage());
            $this->line('Run this on a server whose IP Luca has registered, or set luca.connections.*.proxy.');

            return self::FAILURE;
        } catch (LucaException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->report(Schema::bundled(), $compiled);

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $output = $this->outputPath();

        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }

        file_put_contents($output, SchemaCompiler::export($compiled->toArray(), 'Luca WSDL'));

        $this->components->info("Written to {$output}.");

        if ($this->laravel->make('config')->get('luca.schema.path') !== $output) {
            $this->line("Set LUCA_SCHEMA_PATH={$output} to use it.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $services
     * @param  callable(string): string  $url
     * @return array<string, string|null> source (URL or file) => service name
     */
    private function sources(array $services, callable $url): array
    {
        /** @var list<string> $files */
        $files = (array) $this->option('from');

        if ($files !== []) {
            return array_fill_keys($files, null);
        }

        $sources = [];

        foreach (array_keys($services) as $service) {
            $sources[$url($service).'?singleWsdl'] = $service;
        }

        return $sources;
    }

    private function fetch(Factory $http, string $url, ?string $proxy, bool $verify): string
    {
        $options = ['verify' => $verify];

        if ($proxy !== null) {
            $options['proxy'] = $proxy;
        }

        return $http->timeout(60)->withOptions($options)->get($url)->throw()->body();
    }

    private function report(Schema $current, Schema $compiled): void
    {
        $currentOps = array_keys($current->toArray()['operations']);
        $compiledOps = array_keys($compiled->toArray()['operations']);

        $this->components->twoColumnDetail('Operations', (string) count($compiledOps));
        $this->components->twoColumnDetail('Types', (string) count($compiled->toArray()['types']));

        foreach (array_diff($compiledOps, $currentOps) as $operation) {
            $this->components->twoColumnDetail('<fg=green>+ operation</>', $operation);
        }

        foreach (array_diff($currentOps, $compiledOps) as $operation) {
            $this->components->twoColumnDetail('<fg=yellow>- operation (bundled only)</>', $operation);
        }

        $currentTypes = $current->toArray()['types'];

        foreach ($compiled->toArray()['types'] as $key => $type) {
            $before = $currentTypes[$key] ?? null;

            if ($before !== null && ($before['fields'] ?? null) !== ($type['fields'] ?? null) && $type['kind'] === 'complex') {
                $added = array_diff_key($type['fields'] ?? [], $before['fields'] ?? []);
                $removed = array_diff_key($before['fields'] ?? [], $type['fields'] ?? []);

                $this->components->twoColumnDetail(
                    "<fg=cyan>~ {$key}</>",
                    trim(($added !== [] ? '+'.implode(' +', array_keys($added)) : '').' '.($removed !== [] ? '-'.implode(' -', array_keys($removed)) : '')) ?: 'order',
                );
            }
        }
    }

    private function outputPath(): string
    {
        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            return $output;
        }

        $configured = $this->laravel->make('config')->get('luca.schema.path');

        return is_string($configured) && $configured !== '' ? $configured : $this->laravel->storagePath('app/luca-schema.php');
    }
}
