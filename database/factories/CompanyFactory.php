<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = 'Empresa ' . Str::random(5);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'document' => $this->faker->numerify('##############'),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'plan' => $this->faker->randomElement(['free', 'pro', 'enterprise']),
            'trial_ends_at' => $this->faker->optional()->dateTimeBetween('-7 days', '+30 days'),
            'subscription_ends_at' => $this->faker->optional()->dateTimeBetween('now', '+60 days'),
            'is_blocked' => false,
            'blocked_at' => null,
            'blocked_reason' => null,
        ];
    }
}
