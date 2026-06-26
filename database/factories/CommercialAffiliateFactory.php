<?php

namespace Database\Factories;

use App\Models\CommercialAffiliate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CommercialAffiliateFactory extends Factory
{
    protected $model = CommercialAffiliate::class;

    public function definition(): array
    {
        $name = 'Afiliado '.Str::random(6);

        return [
            'name' => $name,
            'email' => Str::lower(Str::random(8)).'@example.com',
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => 'active',
        ];
    }
}
