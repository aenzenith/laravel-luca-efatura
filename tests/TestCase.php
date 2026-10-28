<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Tests;

use Aenzenith\LucaEFatura\LucaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const COMPANY = '1234567801';

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LucaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('luca.connections.luca.company.tax_code', self::COMPANY);
        $app['config']->set('luca.connections.luca.retry.sleep_ms', 0);
    }

    protected function fixture(string $path): string
    {
        return (string) file_get_contents(__DIR__.'/Fixtures/'.$path);
    }
}
