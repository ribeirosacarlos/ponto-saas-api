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
        $faker = fake();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'document' => $faker->numerify('##############'),
            'email' => $faker->unique()->safeEmail(),
            'phone' => $faker->phoneNumber(),
            'address' => $faker->streetAddress(),
            'city' => $faker->city(),
            'state' => $faker->stateAbbr(),
            'is_blocked' => false,
            'blocked_at' => null,
            'blocked_reason' => null,
        ];
    }
}
