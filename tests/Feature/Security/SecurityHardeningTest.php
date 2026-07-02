<?php

namespace Tests\Feature\Security;

use App\Enums\TimesheetStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\EmployeeTimesheetResource;
use App\Jobs\InitiatePasswordResetJob;
use App\Models\Company;
use App\Models\Document;
use App\Models\EmployeeTimesheet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
    }

    public function test_security_headers_are_applied_globally(): void
    {
        $this->getJson('/v1/public/plans')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_auth_me_uses_an_allowlist_without_internal_fields(): void
    {
        $company = Company::factory()->create([
            'stripe_customer_id' => 'cus_secret_internal',
            'current_plan_id' => null,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'invite_code_hash' => hash('sha256', 'secret-code'),
        ]);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $user->assignRole('employee');

        $response = $this->actingAs($user, 'sanctum')->getJson('/v1/auth/me')->assertOk();

        $response
            ->assertJsonPath('data.user.id', (string) $user->id)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.invite_code_hash')
            ->assertJsonMissingPath('data.user.password_set_at')
            ->assertJsonMissingPath('data.user.company.stripe_customer_id')
            ->assertJsonMissingPath('data.user.company.current_plan_id');
    }

    public function test_forgot_password_is_neutral_and_queued_for_unknown_email(): void
    {
        Bus::fake();

        $this->postJson('/v1/forgot-password', ['email' => 'unknown@example.test'])
            ->assertOk()
            ->assertExactJson(['message' => 'Se o e-mail existir, um link de recuperação foi enviado.']);

        Bus::assertDispatched(InitiatePasswordResetJob::class);
    }

    public function test_login_is_rate_limited_without_exposing_account_existence(): void
    {
        Log::spy();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('https://api.jornafy.com/api/v1/auth/login', [
                'email' => 'unknown@example.test',
                'password' => 'wrong-password',
            ])->assertUnauthorized()->assertExactJson(['message' => 'Credenciais inválidas']);
        }

        $this->postJson('https://api.jornafy.com/api/v1/auth/login', [
            'email' => 'unknown@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        Log::shouldHaveReceived('warning')
            ->with('security.rate_limit_exceeded', \Mockery::on(fn (array $context) => $context['scope'] === 'auth-login'
                && isset($context['email_hash'])
                && ! array_key_exists('email', $context)
                && ! array_key_exists('token', $context)
            ));
    }

    public function test_all_security_rate_limiters_are_registered(): void
    {
        foreach ([
            'auth-login',
            'auth-recovery',
            'invite-accept',
            'clock',
            'exports',
            'email-actions',
            'sensitive-admin',
            'public-read',
            'public-company-registration',
        ] as $limiter) {
            $this->assertIsCallable(RateLimiter::limiter($limiter), "Limiter {$limiter} não registrado.");
        }
    }

    public function test_tokens_older_than_thirty_days_are_rejected(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('expired')->plainTextToken;
        PersonalAccessToken::query()->where('tokenable_id', $user->id)->update([
            'created_at' => now()->subDays(31),
        ]);

        $this->withToken($plainTextToken)->getJson('/v1/auth/me')->assertUnauthorized();
    }

    public function test_password_change_revokes_all_personal_access_tokens(): void
    {
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $user = User::factory()->create();
        $user->assignRole('employee');
        $user->createToken('one');
        $user->createToken('two');

        $this->actingAs($user, 'sanctum')->putJson('/v1/employee/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('logout-test')->plainTextToken;

        $this->withToken($plainTextToken)->postJson('https://api.jornafy.com/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($plainTextToken)->getJson('https://api.jornafy.com/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_resources_do_not_expose_private_storage_paths(): void
    {
        $document = new Document([
            'path' => 'private/company/user/file.pdf',
            'storage_disk' => 's3',
            'title' => 'Documento',
        ]);
        $documentPayload = (new DocumentResource($document))->resolve();

        $timesheet = new EmployeeTimesheet([
            'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
            'pdf_path' => 'timesheets/private.pdf',
        ]);
        $timesheetPayload = (new EmployeeTimesheetResource($timesheet))->resolve();

        $this->assertArrayNotHasKey('storage_disk', $documentPayload);
        $this->assertArrayNotHasKey('storage_path', $documentPayload);
        $this->assertArrayNotHasKey('pdf_path', $timesheetPayload);
        $this->assertTrue($timesheetPayload['pdf_available']);
    }

    public function test_cors_allows_configured_local_origin_and_rejects_unknown_origin(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173'])
            ->options('https://api.jornafy.com/api/v1/public/plans')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');

        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->options('https://api.jornafy.com/api/v1/public/plans')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_malicious_blog_sort_is_rejected(): void
    {
        $this->getJson('/v1/public/blog/posts?sort=published_at_desc%3Bdrop%20table%20users')
            ->assertUnprocessable();
    }
}
