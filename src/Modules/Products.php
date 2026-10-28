<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Modules;

/**
 * The company's product catalogue (ProductService).
 */
class Products extends Module
{
    /**
     * @param  list<array<string, mixed>>  $products  Product members
     */
    public function save(array $products): void
    {
        $this->call('SaveProduct', ['Products' => $products]);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    public function delete(array $products): void
    {
        $this->call('DeleteProduct', ['Products' => $products]);
    }

    /**
     * @param  array<string, mixed>  $filter  GetProductRequest members
     * @return list<array<string, mixed>>
     */
    public function find(array $filter = []): array
    {
        return self::records($this->call('GetProduct', $filter), 'Products');
    }
}
