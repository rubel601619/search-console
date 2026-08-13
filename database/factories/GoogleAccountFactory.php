<?php

namespace Database\Factories;

use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<GoogleAccount>
 */
class GoogleAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'google_id' => fake()->numerify('################'),
            'email' => fake()->safeEmail(),
            'access_token' => Hash::make(fake()->sha256()),
            'refresh_token' => Hash::make(fake()->sha256()),
            'expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        ];
    }
}
