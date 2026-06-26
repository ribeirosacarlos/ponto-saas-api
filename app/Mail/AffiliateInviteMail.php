<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AffiliateInviteMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $affiliateName,
        public ?string $inviteUrl,
        public string $inviteCode,
        public ?string $supportEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Seu convite de afiliado — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.affiliate_invite',
            with: [
                'affiliateName' => $this->affiliateName,
                'inviteUrl'     => $this->inviteUrl,
                'inviteCode'    => $this->inviteCode,
                'supportEmail'  => $this->supportEmail ?? config('app.support_email'),
                'appName'       => config('app.name'),
            ],
        );
    }
}
