<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Contracts\ProductRepositoryInterface;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * GET /api/products
     */
    public function index(): JsonResponse
    {
        $products = $this->productRepository->findAll();

        return response()->json([
            'data' => array_map(fn ($p) => [
                'id' => $p['id'],
                'name' => $p['name'],
                'price' => $p['price'],
                'stock' => $p['stock'],
                'created_at' => $p['created_at'],
            ], $products),
        ]);
    }
}
