<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* @chisel-roles-permissions */
        $this->call(AuthorizationSeeder::class);
        /* @end-chisel-roles-permissions */
    }
}
