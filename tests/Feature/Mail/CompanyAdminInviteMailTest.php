<?php

namespace Tests\Feature\Mail;

use App\Mail\CompanyAdminInviteMail;
use Tests\TestCase;

class CompanyAdminInviteMailTest extends TestCase
{
    public function test_it_renders_invite_link_and_code()
    {
        $mail = new CompanyAdminInviteMail(
            userName: 'Admin Teste',
            userEmail: 'admin@teste.com',
            companyName: 'Empresa X',
            inviteUrl: 'https://app.test/invite?invite_code=ABC123',
            inviteCode: 'ABC123',
            supportEmail: 'suporte@teste.com',
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Activar mi cuenta', $rendered);
        $this->assertStringContainsString('https://app.test/invite?invite_code=ABC123', $rendered);
        $this->assertStringContainsString('Código de activación', $rendered);
        $this->assertStringContainsString('ABC123', $rendered);
    }

    public function test_it_uses_default_support_email_when_missing()
    {
        $fromAddress = config('app.support_email');

        $mail = new CompanyAdminInviteMail(
            userName: 'Admin Teste',
            userEmail: 'admin@teste.com',
            companyName: null,
            inviteUrl: null,
            inviteCode: null,
            supportEmail: null,
        );

        $rendered = $mail->render();

        $this->assertStringContainsString($fromAddress, $rendered);
    }
}
