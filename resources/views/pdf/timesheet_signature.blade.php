<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Folha de Ponto Assinada</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.5; padding-bottom: 40px; }
        .header { background: #1e3a5f; color: white; padding: 16px 24px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; font-weight: bold; }
        .header p { font-size: 11px; opacity: 0.85; margin-top: 2px; }
        .section { margin: 0 24px 16px; }
        .section-title { font-size: 12px; font-weight: bold; color: #1e3a5f; border-bottom: 1px solid #c0c0c0; padding-bottom: 4px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; width: 40%; font-weight: bold; color: #555; padding: 3px 0; }
        .info-value { display: table-cell; color: #1a1a1a; padding: 3px 0; }
        table.entries { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.entries th { background: #e8eef5; color: #1e3a5f; text-align: left; padding: 5px 8px; font-weight: bold; }
        table.entries th.right { text-align: right; }
        table.entries td { padding: 4px 8px; border-bottom: 1px solid #f0f0f0; }
        table.entries td.right { text-align: right; }
        table.entries tr:nth-child(even) td { background: #fafafa; }
        table.entries tr.totals-row td { background: #e8eef5; font-weight: bold; color: #1e3a5f; border-top: 2px solid #c0cfe0; }
        .balance-positive { color: #155724; }
        .balance-negative { color: #721c24; }
        .balance-zero { color: #555; }
        .summary-box { background: #f4f7fa; border: 1px solid #d0dce8; border-radius: 4px; padding: 12px 16px; }
        .summary-grid { display: table; width: 100%; }
        .summary-item { display: table-cell; width: 25%; text-align: center; padding: 4px 8px; }
        .summary-item-label { font-size: 9px; color: #666; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
        .summary-item-value { font-size: 14px; font-weight: bold; color: #1e3a5f; }
        .summary-divider { display: table-cell; width: 1px; background: #d0dce8; }
        .declaration-box { background: #fffbec; border: 1px solid #e8d98a; border-radius: 4px; padding: 12px 16px; font-size: 10px; color: #555; margin: 0 24px 16px; }
        .signature-section { margin: 0 24px 16px; display: table; width: calc(100% - 48px); }
        .signature-block { display: table-cell; width: 48%; vertical-align: top; padding: 12px; border: 1px solid #d0dce8; border-radius: 4px; }
        .signature-block + .signature-block { margin-left: 4%; }
        .signature-image { max-width: 200px; max-height: 80px; margin: 8px 0; }
        .evidence-table { width: 100%; font-size: 9px; }
        .evidence-table td { padding: 2px 0; color: #555; }
        .evidence-table td:first-child { font-weight: bold; width: 40%; }
        .hash-value { font-family: Courier New, monospace; font-size: 8px; word-break: break-all; color: #777; }
        .footer { background: #f4f7fa; border-top: 1px solid #d0dce8; padding: 8px 24px; font-size: 9px; color: #888; position: fixed; bottom: 0; width: 100%; }
    </style>
</head>
<body>

<div class="header">
    <h1>Folha de Ponto — Assinatura Eletrônica</h1>
    @php $companyTz = $employee->company->timezone ?? 'UTC'; @endphp
    <p>{{ $employee->company->name ?? '' }} &bull; Documento gerado em {{ now($companyTz)->format('d/m/Y H:i:s') }} ({{ $companyTz }})</p>
</div>

<div class="section">
    <div class="section-title">Identificação</div>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Colaborador</div>
            <div class="info-value">{{ $employee->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Empresa</div>
            <div class="info-value">{{ $employee->company->name ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Período</div>
            <div class="info-value">
                {{ \Carbon\Carbon::create($closure->reference_year, $closure->reference_month)->locale('pt_BR')->translatedFormat('F \d\e Y') }}
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Hash do documento</div>
            <div class="info-value hash-value">{{ $employeeSignature->document_hash ?? '—' }}</div>
        </div>
    </div>
</div>

@if(!empty($snapshot['days']))
@php
    $totals = $snapshot['totals'] ?? [];
    $balanceClass = fn(string $hhmm) => str_starts_with($hhmm, '+') ? 'balance-positive' : (str_starts_with($hhmm, '-') ? 'balance-negative' : 'balance-zero');
@endphp
<div class="section">
    <div class="section-title">Registros Diários</div>
    <table class="entries">
        <thead>
            <tr>
                <th>Data</th>
                <th>Batidas</th>
                <th class="right">H. Trabalhadas</th>
                <th class="right">H. Previstas</th>
                <th class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($snapshot['days'] as $day)
            @php
                $summary = $day['summary'];
                $balanceHhmm = $summary['balance_hhmm'] ?? '00:00';
                $bClass = $balanceClass($balanceHhmm);
                $dayLabel = '';
                if ($summary['is_holiday']) $dayLabel = $summary['holiday_name'] ?? 'Feriado';
                elseif ($summary['is_day_off']) $dayLabel = 'Folga';
                elseif ($summary['is_vacation']) $dayLabel = 'Férias';
                elseif ($summary['is_absence']) $dayLabel = 'Ausência';
            @endphp
            <tr>
                <td>
                    {{ \Carbon\Carbon::parse($day['date'])->format('d/m') }}
                    {{ \Carbon\Carbon::parse($day['date'])->locale('pt_BR')->translatedFormat('(D)') }}
                    @if($dayLabel)
                        <span style="color:#888;font-size:9px;"> — {{ $dayLabel }}</span>
                    @endif
                </td>
                <td>
                    @if(!empty($day['entries']))
                        {{ implode('  ', array_map(fn($e) => \Carbon\Carbon::parse($e['clocked_at'])->format('H:i'), $day['entries'])) }}
                    @else
                        —
                    @endif
                </td>
                <td class="right">{{ $summary['worked_hhmm'] ?? '—' }}</td>
                <td class="right">{{ $summary['expected_hhmm'] ?? '—' }}</td>
                <td class="right {{ $bClass }}">{{ $balanceHhmm !== '00:00' ? $balanceHhmm : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
        @if(!empty($totals))
        <tfoot>
            <tr class="totals-row">
                <td colspan="2">Total do período</td>
                <td class="right">{{ $totals['worked_hhmm'] ?? '—' }}</td>
                <td class="right">{{ $totals['expected_hhmm'] ?? '—' }}</td>
                <td class="right {{ $balanceClass($totals['balance_hhmm'] ?? '00:00') }}">{{ $totals['balance_hhmm'] ?? '—' }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

@endif

<div class="declaration-box">
    <strong>Declaração:</strong> {{ $declarationText }}
</div>

<div class="section">
    <div class="section-title">Assinaturas Eletrônicas</div>
</div>

<div class="signature-section">
    <div class="signature-block">
        <strong>Colaborador</strong>
        @if($employeeSignature)
            @if($signatureImageBase64)
                <br><img src="{{ $signatureImageBase64 }}" class="signature-image" alt="Assinatura do colaborador">
            @endif
            <table class="evidence-table" style="margin-top:8px;">
                <tr><td>Assinado em</td><td>{{ $employeeSignature->signed_at->format('d/m/Y H:i:s') }} UTC</td></tr>
                <tr><td>IP</td><td>{{ $employeeSignature->ip_address ?? '—' }}</td></tr>
                @if($employeeSignature->latitude)
                <tr><td>Localização</td><td>{{ $employeeSignature->latitude }}, {{ $employeeSignature->longitude }}</td></tr>
                @endif
                <tr><td>Aceite de termos</td><td>{{ $employeeSignature->accepted_terms ? 'Sim' : 'Não' }}</td></tr>
                <tr><td>Hash da assinatura</td><td class="hash-value">{{ $employeeSignature->signature_hash ?? '—' }}</td></tr>
            </table>
        @else
            <p style="color:#888;margin-top:8px;">Pendente</p>
        @endif
    </div>

    <div class="signature-block" style="margin-left:4%;">
        <strong>Gestor</strong>
        @if($managerSignature)
            <table class="evidence-table" style="margin-top:8px;">
                <tr><td>Assinado por</td><td>{{ $managerSignature->signer->name ?? '—' }}</td></tr>
                <tr><td>Assinado em</td><td>{{ $managerSignature->signed_at->format('d/m/Y H:i:s') }} UTC</td></tr>
                <tr><td>IP</td><td>{{ $managerSignature->ip_address ?? '—' }}</td></tr>
            </table>
        @else
            <p style="color:#888;margin-top:8px;">Pendente</p>
        @endif
    </div>
</div>

<div class="footer">
    Documento gerado automaticamente pelo sistema Jornafy. Assinatura eletrônica simples com evidências técnicas para fins de auditoria.
</div>

</body>
</html>
