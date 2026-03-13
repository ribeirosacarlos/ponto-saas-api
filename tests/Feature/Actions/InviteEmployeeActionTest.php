<?php

namespace Tests\Feature\Actions;

use App\Actions\Employees\InviteEmployeeAction;
use App\Jobs\SendEmployeeInviteJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class InviteEmployeeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_employee_and_dispatches_invite_job()
    {
        Bus::fake();
        config(['app.invite_url' => 'https://app.jornafy.com/activate-account']);

        $inviter = User::factory()->create();

        $data = [
            'name' => 'Convite',
            'email' => 'convite@example.com',
            'role' => 'employee',
        ];

        $action = app(InviteEmployeeAction::class);

        $employee = $action->execute($inviter, $data);

        $this->assertEquals($inviter->company_id, $employee->company_id);
        $this->assertTrue($employee->must_change_password);
        $this->assertNotNull($employee->invite_code_hash);

        Bus::assertDispatched(SendEmployeeInviteJob::class, function (SendEmployeeInviteJob $job) use ($employee) {
            if ($job->userId !== $employee->id) {
                return false;
            }

            return ($job->payload['inviteUrl'] ?? null) === 'https://app.jornafy.com/activate-account?email=convite%40example.com'
                && ! empty($job->payload['inviteCode'])
                && ! str_contains($job->payload['inviteUrl'] ?? '', 'invite_code=');
        });
    }
}
