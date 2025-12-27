<?php

namespace Tests\Feature\Mail;

use App\Mail\EmployeeInviteMail;
use Tests\TestCase;

class EmployeeInviteMailTest extends TestCase
{
    public function test_it_renders_cta_when_invite_url_is_present()
    {
        $mail = new EmployeeInviteMail(
            userName: 'Teste',
            userEmail: 'teste@example.com',
            companyName: 'Empresa X',
            inviteUrl: 'https://app.test/invite?invite_code=ABC123',
            inviteCode: 'ABC123',
            temporaryPassword: 'S3nh@Temp',
            supportEmail: 'suporte@teste.com'
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Aceitar convite', $rendered);
        $this->assertStringContainsString('https://app.test/invite?invite_code=ABC123', $rendered);
        $this->assertStringContainsString('Código do convite', $rendered);
    }

    public function test_it_shows_temporary_password_when_link_is_absent()
    {
        $mail = new EmployeeInviteMail(
            userName: 'Teste',
            userEmail: 'teste@example.com',
            companyName: 'Empresa X',
            inviteUrl: null,
            inviteCode: 'XYZ789',
            temporaryPassword: 'Outra123',
            supportEmail: 'suporte@teste.com'
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Senha provisória', $rendered);
        $this->assertStringContainsString('Outra123', $rendered);
        $this->assertStringContainsString('Código do convite', $rendered);
    }
}
