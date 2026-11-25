<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters: sellers and products must exist before offers, and offers
     * before their digital keys. Every seeder is idempotent by a natural key.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => '2026-01-01 00:00:00',
            ],
        );

        $this->call([
            SellerSeeder::class,
            ProductSeeder::class,
            OfferSeeder::class,
            DigitalKeySeeder::class,
            HomepageBlockSeeder::class,
        ]);
    }
}
