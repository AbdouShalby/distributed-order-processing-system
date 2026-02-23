<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create test users
        DB::table('users')->insert([
            [
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Charlie',
                'email' => 'charlie@example.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create test products with varying stock levels
        DB::table('products')->insert([
            [
                'name' => 'Laptop Pro',
                'price' => 999.99,
                'stock' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Wireless Mouse',
                'price' => 29.99,
                'stock' => 200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'USB-C Hub',
                'price' => 49.99,
                'stock' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mechanical Keyboard',
                'price' => 149.99,
                'stock' => 75,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Limited Edition Headphones',
                'price' => 299.99,
                'stock' => 1, // stock = 1 for overselling tests
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
