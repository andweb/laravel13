<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'anna@example.com'],
            ['name' => 'Anna', 'password' => 'password'],
        );

        User::query()->updateOrCreate(
            ['email' => 'andrey@example.com'],
            ['name' => 'Andrey', 'password' => 'password'],
        );
    }
}
