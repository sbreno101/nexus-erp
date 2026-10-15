{{-- PDF da proposta (Dompdf). CSS simples e inline: o Dompdf não suporta Tailwind. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Proposta {{ $proposal->number }}</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #1e293b; margin: 0; }
        .header { border-bottom: 3px solid #4f46e5; padding-bottom: 12px; margin-bottom: 18px; }
        .header table { width: 100%; }
        .company { font-size: 16px; font-weight: bold; color: #312e81; }
        .muted { color: #64748b; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-bottom: 14px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th { background: #eef2ff; color: #3730a3; text-align: left; padding: 6px; font-size: 10px; text-transform: uppercase; }
        table.items td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .totals { width: 40%; margin-left: 60%; margin-top: 10px; }
        .totals td { padding: 4px 6px; }
        .grand { font-size: 14px; font-weight: bold; border-top: 2px solid #4f46e5; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    @if ($logo)
                        <img src="data:image/png;base64,{{ base64_encode($logo) }}" style="max-height: 50px; margin-bottom: 6px;" alt="Logotipo"><br>
                    @endif
                    <span class="company">{{ $company->display_name }}</span><br>
                    <span class="muted">{{ $company->legal_name }} · CNPJ {{ document_format($company->cnpj) }}</span><br>
                    <span class="muted">{{ collect([$company->street.($company->number ? ', '.$company->number : ''), $company->city.($company->state ? '/'.$company->state : '')])->filter()->implode(' — ') }}</span>
                </td>
                <td class="right">
                    <h1>Proposta comercial</h1>
                    <strong>{{ $proposal->number }}</strong><br>
                    <span class="muted">Emissão: {{ br_date($proposal->issue_date) }}</span><br>
                    <span class="muted">Validade: {{ br_date($proposal->valid_until) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="box">
        <strong>Cliente:</strong> {{ $proposal->customer->name }}<br>
        <span class="muted">{{ $proposal->customer->type->documentLabel() }} {{ $proposal->customer->formatted_document }}
            @if ($proposal->customer->email) · {{ $proposal->customer->email }} @endif</span>
        @if ($proposal->customer->primaryAddress)
            <br><span class="muted">{{ $proposal->customer->primaryAddress->oneLine() }}</span>
        @endif
    </div>

    <h2 style="font-size: 13px; margin: 0 0 4px;">{{ $proposal->title }}</h2>

    <table class="items">
        <thead>
            <tr><th>Descrição</th><th class="right">Qtd.</th><th class="right">Preço unit.</th><th class="right">Desconto</th><th class="right">Total</th></tr>
        </thead>
        <tbody>
            @foreach ($proposal->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->formattedQuantity() }} {{ $item->unit }}</td>
                    <td class="right">{{ money($item->unit_price_cents) }}</td>
                    <td class="right">{{ $item->discount_cents ? money($item->discount_cents) : '—' }}</td>
                    <td class="right">{{ money($item->total_cents) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ money($proposal->subtotal_cents) }}</td></tr>
        @if ($proposal->discount_cents)
            <tr><td>Desconto</td><td class="right">- {{ money($proposal->discount_cents) }}</td></tr>
        @endif
        <tr class="grand"><td>Total</td><td class="right">{{ money($proposal->total_cents) }}</td></tr>
    </table>

    @if ($proposal->payment_terms)
        <div class="box" style="margin-top: 18px;"><strong>Condições comerciais</strong><br>{!! nl2br(e($proposal->payment_terms)) !!}</div>
    @endif
    @if ($proposal->notes)
        <div class="box"><strong>Observações</strong><br>{!! nl2br(e($proposal->notes)) !!}</div>
    @endif

    <p class="muted" style="margin-top: 24px;">Responsável: {{ $proposal->owner?->name ?? '—' }} · {{ $company->email }} · {{ $company->phone }}</p>

    <div class="footer">Documento gerado pelo Nexus ERP em {{ now()->format('d/m/Y H:i') }}.</div>
</body>
</html>
