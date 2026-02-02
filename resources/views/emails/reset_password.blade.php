@php
    $brand = $companyName ?? config('app.name', 'Jornafy');
    $supportEmail = $supportEmail ?? config('app.support_email', 'soporte@jornafy.com');
@endphp

    <!DOCTYPE html>
<html lang="es-ES">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Recuperar contraseña · {{ $brand }}</title>
</head>
<body style="font-family:'Segoe UI', system-ui, -apple-system, Arial, sans-serif; margin:0; padding:0; background:#f4f4f6;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f4f4f6;">
    <tr>
        <td align="center" style="padding:32px 16px;">

            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;">
                {{-- Header --}}
                <tr>
                    <td style="padding:0 0 12px;">
                        <div style="font-size:14px; color:#6b7280;">
                            {{ $brand }} · Seguridad de la cuenta
                        </div>
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 12px 30px rgba(17,24,39,0.08);">

                        {{-- Greeting --}}
                        <p style="margin:0 0 10px; font-size:20px; font-weight:700; color:#111827;">
                            Hola {{ $userName ?? '' }},
                        </p>

                        {{-- Intro --}}
                        <p style="margin:0 0 16px; font-size:16px; line-height:1.5; color:#374151;">
                            Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en
                            <strong>{{ $brand }}</strong>.
                        </p>

                        {{-- Explanation --}}
                        <p style="margin:0 0 18px; font-size:14px; line-height:1.6; color:#374151;">
                            Para crear una nueva contraseña y recuperar el acceso, haz clic en el botón de abajo.
                            Este enlace es personal y tiene una validez limitada por motivos de seguridad.
                        </p>

                        {{-- CTA --}}
                        @if (!empty($resetUrl))
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 20px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $resetUrl }}"
                                           style="background:#2f6de9; color:#ffffff; text-decoration:none; padding:14px 26px; border-radius:10px; display:inline-block; font-weight:700; font-size:14px;">
                                            Restablecer contraseña
                                        </a>
                                        <div style="margin-top:10px; font-size:12px; color:#6b7280;">
                                            Enlace seguro · Uso único
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        {{-- Reset Code --}}
                        @if (!empty($resetCode))
                            <div style="margin:0 0 18px; padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px;">
                                <div style="font-size:13px; font-weight:700; color:#111827; margin-bottom:6px;">
                                    Código de recuperação
                                </div>
                                <div style="font-size:13px; line-height:1.6; color:#374151; letter-spacing: 0.1em; font-family: monospace;">
                                    {{ $resetCode }}
                                </div>
                                <div style="font-size:12px; color:#6b7280; margin-top:8px;">
                                    Insira este código no formulário de redefinição de senha
                                </div>
                            </div>
                        @endif

                        {{-- Security note --}}
                        <div style="margin:0 0 18px; padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px;">
                            <div style="font-size:13px; font-weight:700; color:#111827; margin-bottom:6px;">
                                Información importante
                            </div>
                            <div style="font-size:13px; line-height:1.6; color:#374151;">
                                Si no solicitaste este cambio, puedes ignorar este correo.
                                Tu contraseña actual seguirá siendo válida y segura.
                            </div>
                        </div>

                        {{-- Help --}}
                        <p style="margin:0 0 6px; font-size:13px; line-height:1.5; color:#374151;">
                            ¿Tienes alguna duda o problema con el acceso?
                            Escríbenos a
                            <a href="mailto:{{ $supportEmail }}" style="color:#2f6de9; text-decoration:none; font-weight:600;">
                                {{ $supportEmail }}
                            </a>.
                        </p>

                        <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">
                            Por tu seguridad, nunca compartas este enlace con nadie.
                        </p>

                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:14px 6px 0; font-size:12px; color:#9ca3af; text-align:center;">
                        © {{ date('Y') }} {{ $brand }}. Todos los derechos reservados.
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
</body>
</html>
