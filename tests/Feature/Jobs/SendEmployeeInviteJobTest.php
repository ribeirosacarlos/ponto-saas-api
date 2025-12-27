<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendEmployeeInviteJob;
use App\Mail\EmployeeInviteMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendEmployeeInviteJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_employee_invite_mail()
    {
        Mail::fake();

        $user = User::factory()->create();

        $payload = [
            'companyName' => $user->company?->name,
            'inviteUrl' => 'https://app.test/invite?invite_code=ABC123',
            'inviteCode' => 'ABC123',
            'temporaryPassword' => 'S3nh@Temp',
            'supportEmail' => 'suporte@teste.com',
        ];

        dispatch_sync(new SendEmployeeInviteJob($user->id, $payload));

        Mail::assertQueued(EmployeeInviteMail::class, function (EmployeeInviteMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }
}
