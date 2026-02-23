<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Inventory\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findByIdForUpdate(int $id): ?array
    {
        $product = DB::table('products')
            ->where('id', $id)
            ->lockForUpdate()
            ->first();

        return $product ? $this->castProduct($product) : null;
    }

    public function findById(int $id): ?array
    {
        $product = DB::table('products')->where('id', $id)->first();

        return $product ? $this->castProduct($product) : null;
    }

    public function decrementStock(int $productId, int $quantity): void
    {
        DB::table('products')
            ->where('id', $productId)
            ->decrement('stock', $quantity);
    }

    public function incrementStock(int $productId, int $quantity): void
    {
        DB::table('products')
            ->where('id', $productId)
            ->increment('stock', $quantity);
    }

    public function findAll(): array
    {
        return DB::table('products')
            ->get()
            ->map(fn ($p) => $this->castProduct($p))
            ->all();
    }

    private function castProduct(object $product): array
    {
        $data = (array) $product;
        $data['price'] = (string) $data['price'];
        $data['stock'] = (int) $data['stock'];

        return $data;
    }
}
