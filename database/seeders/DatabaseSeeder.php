<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'full_name' => 'Administrator',
        ]);

        // Create Regular User
        User::create([
            'name' => 'Bendahara',
            'email' => 'bendahara@example.com',
            'password' => Hash::make('password'),
            'role' => 'anggota',
            'full_name' => 'Bendahara Program',
        ]);

        User::create([
            'name' => 'Anggota',
            'email' => 'anggota@example.com',
            'password' => Hash::make('password'),
            'role' => 'anggota',
            'full_name' => 'Anggota Program',
        ]);
    }
}
