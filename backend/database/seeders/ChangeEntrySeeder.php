<?php

namespace Database\Seeders;

use App\Models\ChangeEntry;
use Illuminate\Database\Seeder;

class ChangeEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ChangeEntry::factory()->count(5)->create();
    }
}
