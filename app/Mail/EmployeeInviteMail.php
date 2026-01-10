<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmployeeInviteMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $userName,
        public string $userEmail,
        public ?string $companyName,
        public ?string $inviteUrl,
        public ?string $inviteCode,
        public ?string $temporaryPassword,
        public ?string $supportEmail,
    ) {}

    public function envelope(): Envelope
    {
        $brand = $this->companyName ?? config('app.name', 'Jornafy');

        return new Envelope(
            subject: "Convite de acesso - {$brand}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employee_invite',
            with: [
                'userName' => $this->userName,
                'companyName' => $this->companyName,
                'inviteUrl' => $this->inviteUrl,
                'inviteCode' => $this->inviteCode,
                'temporaryPassword' => $this->temporaryPassword,
                'supportEmail' => $this->supportEmail ?? config('app.support_email'),
            ],
        );
    }
}
