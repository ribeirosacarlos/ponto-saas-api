<?php

namespace App\Actions\Auth;

use App\Jobs\SendPasswordResetEmailJob;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SendPasswordResetAction
{
    public function execute(string $email): bool
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Return true even if user doesn't exist to prevent email enumeration
            return true;
        }

        // Generate a temporary reset code similar to the invite code
        $resetCodePlain = $this->generateResetCode();

        // Store the hashed code in the password_reset_tokens table
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => hash('sha256', $resetCodePlain),
                'created_at' => now(),
            ]
        );

        $brand = $user->company?->name ?? config('app.name', 'Jornafy');
        $supportEmail = config('app.support_email');

        $payload = [
            'userName' => $user->name,
            'userEmail' => $user->email,
            'companyName' => $brand,
            'resetCode' => $resetCodePlain,
            'supportEmail' => $supportEmail,
            'token' => $resetCodePlain, // For backward compatibility with the template
            'email' => $user->email,
        ];

        SendPasswordResetEmailJob::dispatch($user->id, $payload);

        return true;
    }

    protected function generateResetCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = strlen($characters);
        $resetCode = '';

        for ($i = 0; $i < 8; $i++) {
            $resetCode .= $characters[random_int(0, $length - 1)];
        }

        return $resetCode;
    }
}