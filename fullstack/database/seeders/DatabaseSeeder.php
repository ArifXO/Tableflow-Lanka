<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\DishSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'anindokhan80@gmail.com'],
            [
                'name' => 'Anindo',
                'password' => Hash::make('anindo22'),
                'email_verified_at' => now(),
                'loyalty_points' => 5000,
                'role' => 'manager',
            ]
        );

        // Kitchen staff account
        User::updateOrCreate(
            ['email' => 'kitchen@example.com'],
            [
                'name' => 'Kitchen Staff',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'loyalty_points' => 0,
                'role' => 'kitchen',
            ]
        );

        // Sample diner account
        User::updateOrCreate(
            ['email' => 'diner@example.com'],
            [
                'name' => 'Sample Diner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'loyalty_points' => 200,
                'role' => 'diner',
            ]
        );
        $this->call([
            DishSeeder::class,
            TableSeeder::class,
        ]);
    }
}
