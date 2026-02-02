<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $userName,
        public string $userEmail,
        public ?string $companyName,
        public ?string $token,
        public ?string $email,
        public ?string $resetCode,
        public ?string $supportEmail,
    ) {}

    public function envelope(): Envelope
    {
        $brand = $this->companyName ?? config('app.name', 'Jornafy');

        return new Envelope(
            subject: "Recuperação de senha - {$brand}",
        );
    }

    public function content(): Content
    {
        $frontendUrl = config('app.frontend_url');

        $resetUrl = "{$frontendUrl}/reset-password"
            . "?token={$this->token}"
            . "&email=" . urlencode($this->email);

        return new Content(
            view: 'emails.reset_password',
            with: [
                'userName' => $this->userName,
                'companyName' => $this->companyName,
                'resetUrl' => $resetUrl,
                'resetCode' => $this->resetCode,
                'supportEmail' => $this->supportEmail ?? config('app.support_email'),
            ],
        );
    }
}
