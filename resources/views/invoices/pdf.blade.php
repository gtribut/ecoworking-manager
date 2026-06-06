@php
    /** @var \App\Models\Invoice $invoice */
    $company = config('company');
    $addr = $invoice->billing_address ?? [];
    $isCredit = $invoice->is_credit_note;
    $money = fn ($v) => number_format((float) $v, 2, ',', ' ').' €';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1a1a1a; margin: 0; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .seller { font-size: 10px; line-height: 1.5; }
        .seller .name { font-size: 14px; font-weight: bold; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 22px; margin: 0 0 4px; color: #2f6f4f; }
        .doc-title .number { font-size: 13px; font-weight: bold; }
        .parties { width: 100%; margin: 16px 0 24px; }
        .parties td { vertical-align: top; width: 50%; }
        .box { border: 1px solid #ddd; padding: 10px; }
        .box .label { font-size: 9px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.lines th { background: #2f6f4f; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
        table.lines td { padding: 6px 8px; border-bottom: 1px solid #eee; }
        table.lines td.num, table.lines th.num { text-align: right; }
        .totals { width: 45%; float: right; border-collapse: collapse; }
        .totals td { padding: 4px 8px; }
        .totals .grand { font-weight: bold; font-size: 13px; border-top: 2px solid #2f6f4f; }
        .meta { clear: both; padding-top: 24px; font-size: 10px; color: #444; line-height: 1.5; }
        .legal { margin-top: 18px; font-size: 8px; color: #888; line-height: 1.4; border-top: 1px solid #eee; padding-top: 8px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="seller">
                <div class="name">{{ $company['legal_name'] }}</div>
                @if($company['legal_form']) {{ $company['legal_form'] }}@if($company['capital']) au capital de {{ $company['capital'] }} €@endif<br>@endif
                @if($company['address']['line1']){{ $company['address']['line1'] }}<br>@endif
                @if($company['address']['line2']){{ $company['address']['line2'] }}<br>@endif
                {{ $company['address']['postal_code'] }} {{ $company['address']['city'] }}<br>
                @if($company['siret'])SIRET : {{ $company['siret'] }}<br>@endif
                @if($company['vat_number'])TVA : {{ $company['vat_number'] }}<br>@endif
                @if($company['email']){{ $company['email'] }}@endif @if($company['phone']) · {{ $company['phone'] }}@endif
            </td>
            <td class="doc-title">
                <h1>{{ $isCredit ? 'AVOIR' : 'FACTURE' }}</h1>
                <div class="number">{{ $invoice->number ?? 'BROUILLON' }}</div>
                <div>Date : {{ $invoice->issued_at?->format('d/m/Y') }}</div>
                @unless($isCredit)<div>Échéance : {{ $invoice->due_at?->format('d/m/Y') }}</div>@endunless
                @if($isCredit && $invoice->creditNoteForInvoice)
                    <div>Annule la facture {{ $invoice->creditNoteForInvoice->number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td></td>
            <td>
                <div class="box">
                    <div class="label">{{ $isCredit ? 'Avoir établi pour' : 'Facturé à' }}</div>
                    <strong>{{ $invoice->billing_name }}</strong><br>
                    @if(!empty($addr['line1'])){{ $addr['line1'] }}<br>@endif
                    @if(!empty($addr['line2'])){{ $addr['line2'] }}<br>@endif
                    @if(!empty($addr['postal_code']) || !empty($addr['city'])){{ $addr['postal_code'] ?? '' }} {{ $addr['city'] ?? '' }}<br>@endif
                    @if($invoice->billing_siret)SIRET : {{ $invoice->billing_siret }}<br>@endif
                    @if($invoice->billing_vat_number)TVA : {{ $invoice->billing_vat_number }}@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="num">Qté</th>
                <th class="num">P.U. HT</th>
                <th class="num">Remise</th>
                <th class="num">TVA</th>
                <th class="num">Total HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $line)
                <tr>
                    <td>
                        {{ $line->description }}
                        @if($line->period_start)
                            <br><span style="font-size:9px;color:#888;">Période : {{ $line->period_start->format('d/m/Y') }}@if($line->period_end) → {{ $line->period_end->format('d/m/Y') }}@endif</span>
                        @endif
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', ' '), '0'), ',') }}</td>
                    <td class="num">{{ $money($line->unit_price_ht) }}</td>
                    <td class="num">{{ $line->discount_rate ? rtrim(rtrim(number_format((float) $line->discount_rate, 2, ',', ' '), '0'), ',').' %' : '—' }}</td>
                    <td class="num">{{ number_format((float) $line->vat_rate, 1, ',', ' ') }} %</td>
                    <td class="num">{{ $money($line->line_total_ht) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Total HT</td><td class="num" style="text-align:right">{{ $money($invoice->subtotal_ht) }}</td></tr>
        <tr><td>TVA</td><td class="num" style="text-align:right">{{ $money($invoice->total_vat) }}</td></tr>
        <tr class="grand"><td>Total TTC</td><td class="num" style="text-align:right">{{ $money($invoice->total_ttc) }}</td></tr>
    </table>

    <div class="meta">
        @unless($isCredit)
            <strong>Conditions de règlement :</strong> paiement à 14 jours.<br>
            En cas de retard : pénalités au taux de 3 fois le taux d'intérêt légal et indemnité forfaitaire pour frais de recouvrement de 40 € (art. L441-10 et D441-5 du Code de commerce). Pas d'escompte pour paiement anticipé.
        @else
            Le présent avoir annule et remplace la facture référencée ci-dessus.
        @endunless
    </div>

    <div class="legal">
        {{ $company['legal_name'] }}@if($company['legal_form']) — {{ $company['legal_form'] }}@endif @if($company['rcs']) — RCS {{ $company['rcs'] }}@endif @if($company['siret']) — SIRET {{ $company['siret'] }}@endif @if($company['vat_number']) — TVA intracommunautaire {{ $company['vat_number'] }}@endif.
    </div>
</body>
</html>
