<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CompanyAdminInviteMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $userName,
        public string $userEmail,
        public ?string $companyName,
        public ?string $inviteUrl,
        public ?string $inviteCode,
        public ?string $supportEmail,
    ) {}

    public function envelope(): Envelope
    {
        $brand = $this->companyName ?? config('app.name', 'SaaS');

        return new Envelope(
            subject: "Acesso administrativo - {$brand}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.company_admin_invite',
            with: [
                'userName' => $this->userName,
                'companyName' => $this->companyName,
                'inviteUrl' => $this->inviteUrl,
                'inviteCode' => $this->inviteCode,
                'supportEmail' => $this->supportEmail ?? config('mail.from.address'),
            ],
        );
    }
}
