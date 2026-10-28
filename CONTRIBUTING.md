# Contributing

1. Fork and create a branch from `main`.
2. `composer install`
3. Make your change, keep it small and focused.
4. `composer format` (Pint), `composer analyse` (PHPStan level 8), `composer test`.
5. Open a pull request with a Conventional Commits title (`feat:`, `fix:`, ...).

Compatibility promise: PHP 8.2+ and Laravel 12 and 13. Do not use language
features newer than PHP 8.2 (typed constants, `#[\Override]`, property hooks).

Schema changes: `resources/schema/invoice-service-2022.php` is generated; put
new fields and operations in `resources/schema/overlay.php` with their source
(sample request or document), and add the sample to `tests/Fixtures/official/`
when there is one.
