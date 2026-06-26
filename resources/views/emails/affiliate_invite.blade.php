<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Convite de afiliado — {{ $appName }}</title>
</head>
<body style="font-family:'Segoe UI', system-ui, -apple-system, Arial, sans-serif; margin:0; padding:0; background:#f4f4f6;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f4f4f6;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;">

                <tr>
                    <td style="padding:0 0 12px;">
                        <div style="font-size:14px; color:#6b7280;">
                            {{ $appName }} · Programa de Afiliados
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 12px 30px rgba(17,24,39,0.08);">

                        <p style="margin:0 0 10px; font-size:20px; font-weight:700; color:#111827;">
                            Olá, {{ $affiliateName }}!
                        </p>

                        <p style="margin:0 0 16px; font-size:16px; line-height:1.5; color:#374151;">
                            Você foi convidado para fazer parte do programa de afiliados da <strong>{{ $appName }}</strong>.
                            Clique no botão abaixo para criar sua senha e acessar o painel.
                        </p>

                        @if (!empty($inviteUrl))
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $inviteUrl }}"
                                           style="background:#2f6de9; color:#ffffff; text-decoration:none; padding:14px 26px; border-radius:10px; display:inline-block; font-weight:700; font-size:14px;">
                                            Criar minha senha
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <div style="margin:0 0 18px; padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px;">
                            <div style="font-size:13px; font-weight:700; color:#111827; margin-bottom:6px;">
                                Código de convite
                            </div>
                            <div style="font-size:22px; font-weight:800; letter-spacing:2px; color:#111827;">
                                {{ $inviteCode }}
                            </div>
                            <div style="margin-top:6px; font-size:12px; color:#6b7280;">
                                Use este código caso o botão acima não funcione.
                            </div>
                        </div>

                        <p style="margin:0 0 6px; font-size:13px; line-height:1.5; color:#374151;">
                            Este convite expira em <strong>7 dias</strong>. Em caso de dúvidas, entre em contato:
                            <a href="mailto:{{ $supportEmail }}" style="color:#2f6de9; text-decoration:none; font-weight:600;">
                                {{ $supportEmail }}
                            </a>.
                        </p>

                        <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">
                            Se você não esperava este e-mail, pode ignorá-lo com segurança.
                        </p>

                    </td>
                </tr>

                <tr>
                    <td style="padding:14px 6px 0; font-size:12px; color:#9ca3af; text-align:center;">
                        © {{ date('Y') }} {{ $appName }}. Todos os direitos reservados.
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
