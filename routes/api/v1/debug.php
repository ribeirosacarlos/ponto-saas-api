<?php

use App\Jobs\SendEmployeeInviteJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

// DEBUG / TESTE DO TENANT
Route::get('/tenant-check', function (\App\Services\TenantManager $tm) {
    return [
        'tenant' => $tm->tenant()?->slug,
        'tenant_name' => $tm->tenant()?->name,
    ];
});

Route::get('/debug/send-employee-invite-job', function (Request $request) {
    $param = strtolower($request->query('send_email', $request->query('enviar_email', 'sim')));
    $truthyValues = ['sim', 's', 'yes', 'y', 'true', '1'];

    if (! in_array($param, $truthyValues, true)) {
        return response()->json([
            'job_created' => false,
            'reason' => 'send_email parameter is not affirmative',
        ]);
    }

    $user = null;

    if ($request->filled('user_id')) {
        $user = User::query()->find($request->query('user_id'));
    }

    if (! $user) {
        $user = User::query()->inRandomOrder()->first();
    }

    if (! $user) {
        $user = User::factory()->create([
            'name' => 'Debug Invite ' . Str::random(4),
            'email' => 'debug-invite+' . Str::random(6) . '@example.com',
        ]);
    }

    $payload = [
        'companyName' => $user->company?->name,
        'inviteUrl' => $request->query('invite_url', config('app.invite_url')),
        'inviteCode' => $request->query('invite_code', Str::upper(Str::random(8))),
        'temporaryPassword' => $request->query('temporary_password', 'TempP@ss123'),
        'supportEmail' => config('app.support_email'),
    ];

    $recipientEmail = $request->query('recipient_email', $request->query('override_email', 'dev.carlosdesa@gmail.com'));

    SendEmployeeInviteJob::dispatch($user->id, $payload, $recipientEmail);

    return response()->json([
        'job_created' => true,
        'user_id' => $user->id,
        'recipient_email' => $recipientEmail,
        'payload' => $payload,
    ]);
});
