@php
    $brand = $companyName ?? config('app.name', 'SaaS');
    $supportEmail = $supportEmail ?? config('mail.from.address');
@endphp

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Convite de Acesso</title>
</head>
<body style="font-family: 'Segoe UI', system-ui, sans-serif; margin:0; padding:0; background:#f4f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px; background:#ffffff; border-radius:10px; padding:32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 8px; font-size:18px; font-weight:600;">Olá {{ $userName }},</p>
                            <p style="margin:0 0 16px; font-size:16px;">
                                Você foi convidado(a) a acessar {{ $brand }}. Use o link abaixo para continuar o cadastro.
                            </p>
                        </td>
                    </tr>

                    @if ($inviteUrl)
                        <tr>
                            <td align="center" style="padding:16px 0;">
                                <a href="{{ $inviteUrl }}"
                                   style="background:#2f6de9; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; display:inline-block; font-weight:600;">
                                    Aceitar convite
                                </a>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:0 0 8px;">
                            <p style="margin:0; font-size:14px; color:#555;">
                                Código do convite:
                                <strong>{{ $inviteCode ?? '—' }}</strong>
                            </p>
                        </td>
                    </tr>

                    @if (! $inviteUrl && $temporaryPassword)
                        <tr>
                            <td style="padding:0 0 16px;">
                                <p style="margin:0 0 4px; font-size:14px; color:#111;">Senha provisória:</p>
                                <p style="margin:0 0 8px; font-size:16px; font-weight:600;">{{ $temporaryPassword }}</p>
                                <p style="margin:0; font-size:14px; color:#666;">
                                    Use essa senha para logar e atualize-a após o primeiro acesso.
                                </p>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td>
                            <p style="margin:0 0 8px; font-size:14px; color:#555;">
                                Em caso de dúvidas, responda este e-mail para {{ $supportEmail }}.
                            </p>
                            <p style="margin:0; font-size:12px; color:#999;">
                                Se você não esperava este e-mail, ignore esta mensagem.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
