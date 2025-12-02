<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Faker\Factory as FakerFactory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $faker = FakerFactory::create(); // instancia o Faker "na mão"

        return [
            'name' => $faker->company(),
            'slug' => $faker->unique()->slug(),
        ];
    }
}
