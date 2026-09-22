<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'username' => env('ADMIN_USERNAME'),
            'display_name' => 'Baradox',
            'password' => env('ADMIN_PASSWORD'),
            'is_preview' => false,
        ]);

        User::create([
            'username' => env('PREVIEW_USERNAME'),
            'display_name' => 'Guest',
            'password' => env('PREVIEW_PASSWORD'),
            'is_preview' => true,
        ]);
    }
}
