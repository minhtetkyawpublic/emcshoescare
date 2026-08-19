<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('packages')->insertOrIgnore([
            ['slug' => 'essential-clean', 'name' => 'Essential Clean', 'description' => 'A careful refresh for everyday shoes.', 'price_ks' => 15000, 'is_active' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'premium-care', 'name' => 'Premium Care', 'description' => 'Deeper care for pairs that need extra attention.', 'price_ks' => 25000, 'is_active' => true, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'full-restore', 'name' => 'Full Restore', 'description' => 'Complete care for worn and well-loved shoes.', 'price_ks' => 45000, 'is_active' => true, 'sort_order' => 30, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
