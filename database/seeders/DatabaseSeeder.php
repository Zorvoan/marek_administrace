<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a few starter categories (safe to run more than once).
     */
    public function run(): void
    {
        foreach (['General', 'News', 'Tech', 'Life', 'Fun'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
