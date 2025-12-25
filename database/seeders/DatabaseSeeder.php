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
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => 'ecorevive25',
                'role' => 'admin',
                'full_name' => 'Administrator',
            ]
        );

        // Create Regular Users
        User::updateOrCreate(
            ['email' => 'nanda@eco.com'],
            [
                'name' => 'Nanda',
                'password' => 'ecorevive25',
                'role' => 'anggota',
                'full_name' => 'Anggota Program',
            ]
        );

        User::updateOrCreate(
            ['email' => 'candra@eco.com'],
            [
                'name' => 'Candra',
                'password' => 'ecorevive25',
                'role' => 'anggota',
                'full_name' => 'Anggota Program',
            ]
        );

        User::updateOrCreate(
            ['email' => 'bendahara@example.com'],
            [
                'name' => 'Bendahara',
                'password' => 'ecorevive25',
                'role' => 'anggota',
                'full_name' => 'Bendahara Program',
            ]
        );

        User::updateOrCreate(
            ['email' => 'anggota@example.com'],
            [
                'name' => 'Anggota',
                'password' => 'ecorevive25',
                'role' => 'anggota',
                'full_name' => 'Anggota Program',
            ]
        );
    }
}
