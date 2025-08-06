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
            ]
        );
        $this->call([
            DishSeeder::class,
            TableSeeder::class,
        ]);
    }
}
