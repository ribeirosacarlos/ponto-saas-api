@php
    $brand = $companyName ?? config('app.name', 'SaaS');
    $supportEmail = $supportEmail ?? config('mail.from.address');
@endphp

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Acesso administrativo</title>
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
                                O acesso administrativo à {{ $brand }} está pronto. Clique no botão abaixo para ativar sua conta usando o código informado logo abaixo.
                            </p>
                        </td>
                    </tr>

                    @if ($inviteUrl)
                        <tr>
                            <td align="center" style="padding:16px 0;">
                                <a href="{{ $inviteUrl }}"
                                   style="background:#2f6de9; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; display:inline-block; font-weight:600;">
                                    Ativar minha conta
                                </a>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:0 0 16px;">
                            <p style="margin:0; font-size:14px; color:#111;">Código de ativação:</p>
                            <p style="margin:4px 0 0; font-size:18px; font-weight:600;">{{ $inviteCode ?? '—' }}</p>
                            <p style="margin:4px 0 0; font-size:14px; color:#666;">Insira esse código quando solicitado para completar o cadastro.</p>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <p style="margin:0 0 8px; font-size:14px; color:#555;">
                                Em caso de dúvidas, responda este e-mail para {{ $supportEmail }}.
                            </p>
                            <p style="margin:0; font-size:12px; color:#999;">
                                Se você não solicitou este acesso, ignore esta mensagem.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
