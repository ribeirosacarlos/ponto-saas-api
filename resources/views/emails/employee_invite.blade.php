@php
    $brand = $companyName ?? config('app.name', 'Jornafy');
    $supportEmail = $supportEmail ?? config('app.support_email', 'soporte@jornafy.com');
@endphp

<!DOCTYPE html>
<html lang="es-ES">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Invitación a {{ $brand }}</title>
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
                            {{ $brand }} · Invitación de acceso
                        </div>
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 12px 30px rgba(17,24,39,0.08);">

                        {{-- Greeting --}}
                        <p style="margin:0 0 10px; font-size:20px; font-weight:700; color:#111827;">
                            Hola {{ $userName }},
                        </p>

                        {{-- Intro --}}
                        <p style="margin:0 0 16px; font-size:16px; line-height:1.5; color:#374151;">
                            Tu empresa te ha invitado a acceder a <strong>{{ $brand }}</strong>, la plataforma donde podrás
                            registrar tu jornada laboral de forma sencilla y segura.
                        </p>

                        {{-- Explanation --}}
                        <p style="margin:0 0 18px; font-size:14px; line-height:1.6; color:#374151;">
                            Desde {{ $brand }} podrás fichar tu entrada y salida, consultar tu historial de horas
                            y gestionar tu jornada conforme a la normativa laboral.
                        </p>

                        {{-- CTA --}}
                        @if (!empty($inviteUrl))
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 20px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $inviteUrl }}"
                                           style="background:#2f6de9; color:#ffffff; text-decoration:none; padding:14px 26px; border-radius:10px; display:inline-block; font-weight:700; font-size:14px;">
                                            Aceptar invitación
                                        </a>
                                        <div style="margin-top:10px; font-size:12px; color:#6b7280;">
                                            Acceso rápido y seguro
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        {{-- Code --}}
                        <div style="margin:0 0 18px; padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px;">
                            <div style="font-size:13px; font-weight:700; color:#111827; margin-bottom:6px;">
                                Código de invitación
                            </div>
                            <div style="font-size:20px; font-weight:800; letter-spacing:0.6px; color:#111827;">
                                {{ $inviteCode ?? '—' }}
                            </div>
                            <div style="margin-top:6px; font-size:12px; color:#6b7280;">
                                Introduce este código si el sistema te lo solicita durante el acceso.
                            </div>
                        </div>

                        {{-- Temporary password --}}
                        @if (empty($inviteUrl) && !empty($temporaryPassword))
                            <div style="margin:0 0 18px; padding:14px 16px; background:#fff7ed; border:1px solid #fed7aa; border-radius:10px;">
                                <div style="font-size:13px; font-weight:700; color:#9a3412; margin-bottom:6px;">
                                    Contraseña provisional
                                </div>
                                <div style="font-size:18px; font-weight:700; color:#9a3412;">
                                    {{ $temporaryPassword }}
                                </div>
                                <div style="margin-top:6px; font-size:12px; color:#9a3412;">
                                    Podrás cambiarla después del primer acceso.
                                </div>
                            </div>
                        @endif

                        {{-- Help --}}
                        <p style="margin:0 0 6px; font-size:13px; line-height:1.5; color:#374151;">
                            Si tienes alguna duda, puedes responder a este correo o escribirnos a
                            <a href="mailto:{{ $supportEmail }}" style="color:#2f6de9; text-decoration:none; font-weight:600;">
                                {{ $supportEmail }}
                            </a>.
                        </p>

                        <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">
                            Si no esperabas este mensaje, puedes ignorarlo con total tranquilidad.
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
