@php
    $brand = $companyName ?? config('app.name', 'Jornafy');
    $supportEmail = $supportEmail ?? config('app.support_email', 'soporte@jornafy.com');
@endphp

<!DOCTYPE html>
<html lang="es-ES">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Bienvenido a {{ $brand }}</title>
</head>
<body style="font-family: 'Segoe UI', system-ui, -apple-system, Arial, sans-serif; margin:0; padding:0; background:#f4f4f6;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f4f4f6;">
    <tr>
        <td align="center" style="padding:32px 16px;">

            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;">
                {{-- Header --}}
                <tr>
                    <td style="padding:0 0 12px;">
                        <div style="font-size:14px; color:#666; letter-spacing:0.2px;">
                            {{ $brand }} · Acceso administrativo
                        </div>
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 12px 30px rgba(17,24,39,0.08);">

                        {{-- Title --}}
                        <p style="margin:0 0 10px; font-size:20px; font-weight:700; color:#111827;">
                            Hola {{ $userName }},
                        </p>

                        {{-- Intro --}}
                        <p style="margin:0 0 16px; font-size:16px; line-height:1.5; color:#374151;">
                            Te damos la bienvenida a <strong>{{ $brand }}</strong> 👋
                            Tu acceso administrativo ya está listo. En pocos minutos podrás empezar a gestionar el registro horario
                            de tu empresa de forma sencilla, segura y conforme a la normativa laboral.
                        </p>

                        {{-- Value bullets --}}
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:14px 0 22px;">
                            <tr>
                                <td style="font-size:14px; line-height:1.55; color:#111827;">
                                    <div style="margin:0 0 8px;">✅ Administrar empleados y jornadas laborales</div>
                                    <div style="margin:0 0 8px;">✅ Registrar y auditar el fichaje horario</div>
                                    <div style="margin:0 0 8px;">✅ Cumplir sin papel, sin Excel, sin complicaciones</div>
                                    <div style="margin:0;">✅ Tener control total del tiempo de trabajo del equipo</div>
                                </td>
                            </tr>
                        </table>

                        {{-- CTA --}}
                        @if (!empty($inviteUrl))
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 18px;">
                                <tr>
                                    <td align="center" style="padding:6px 0 0;">
                                        <a href="{{ $inviteUrl }}"
                                           style="background:#2f6de9; color:#ffffff; text-decoration:none; padding:14px 26px; border-radius:10px; display:inline-block; font-weight:700; font-size:14px;">
                                            Activar mi cuenta
                                        </a>
                                        <div style="margin-top:10px; font-size:12px; color:#6b7280;">
                                            Enlace válido solo para activar tu cuenta.
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        {{-- Code box --}}
                        <div style="margin:0 0 18px; padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px;">
                            <div style="font-size:13px; color:#111827; font-weight:700; margin:0 0 6px;">
                                Código de activación
                            </div>

                            <div style="font-size:20px; font-weight:800; letter-spacing:0.6px; color:#111827;">
                                {{ $inviteCode ?? '—' }}
                            </div>

                            <div style="margin-top:6px; font-size:12px; color:#6b7280;">
                                Si el sistema te lo solicita, introduce este código para completar la activación.
                            </div>
                        </div>

                        {{-- Help --}}
                        <p style="margin:0 0 6px; font-size:13px; line-height:1.55; color:#374151;">
                            ¿Necesitas ayuda? Responde a este correo o contáctanos en
                            <a href="mailto:{{ $supportEmail }}" style="color:#2f6de9; text-decoration:none; font-weight:600;">
                                {{ $supportEmail }}
                            </a>.
                        </p>

                        <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">
                            Si no solicitaste este acceso, puedes ignorar este mensaje con total tranquilidad.
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
