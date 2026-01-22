<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCompanyTimezone;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CompanyTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_default_timezone_is_europe_madrid()
    {
        $company = Company::factory()->create();

        $this->assertSame('Europe/Madrid', $company->timezone);
    }

    public function test_set_company_timezone_middleware_applies_company_timezone()
    {
        $role = Role::updateOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin']
        );

        $company = Company::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('admin');

        $request = Request::create('/v1/employee/entries', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new SetCompanyTimezone();
        $middleware->handle($request, fn ($req) => response()->json([
            'current_timezone' => CarbonImmutable::now()->getTimezone()->getName(),
        ]));

        $this->assertEquals('America/Sao_Paulo', config('app.timezone'));
        $this->assertEquals('America/Sao_Paulo', date_default_timezone_get());

        config(['app.timezone' => CompanyTime::DEFAULT_TIMEZONE]);
        date_default_timezone_set(CompanyTime::DEFAULT_TIMEZONE);
    }

    public function test_put_timezone_validates_invalid_value()
    {
        $role = Role::updateOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin']
        );

        $company = Company::factory()->create([
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->putJson('/v1/admin/company/timezone', [
            'timezone' => 'Invalid/Timezone',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('timezone');
    }
}
