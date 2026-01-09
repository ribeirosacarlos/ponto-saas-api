<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendCompanyAdminInviteJob;
use App\Mail\CompanyAdminInviteMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendCompanyAdminInviteJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_company_admin_invite_mail()
    {
        Mail::fake();

        config(['app.invite_url' => 'https://app.test/invite']);

        $user = User::factory()->create();

        $payload = [
            'companyName' => $user->company?->name,
            'inviteCode' => 'ABC123',
            'supportEmail' => 'suporte@teste.com',
        ];

        dispatch_sync(new SendCompanyAdminInviteJob($user->id, $payload));

        Mail::assertQueued(CompanyAdminInviteMail::class, function (CompanyAdminInviteMail $mail) use ($user) {
            $hasInviteUrl = $mail->inviteUrl && str_contains($mail->inviteUrl, 'invite_code=ABC123');

            return $mail->hasTo($user->email)
                && $mail->inviteCode === 'ABC123'
                && $hasInviteUrl;
        });
    }
}
