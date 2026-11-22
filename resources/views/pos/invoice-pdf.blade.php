<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 9px; }
        .thermal { font-size: 7px; }
        .copy { page-break-after: always; }
        .copy:last-child { page-break-after: auto; }
        .heading { text-align: center; border-bottom: 1px solid #cbd5e1; padding-bottom: 10px; margin-bottom: 12px; }
        .logo { max-width: 100px; max-height: 55px; margin-bottom: 5px; }
        .shop-name { font-size: 17px; font-weight: bold; }
        .thermal .shop-name { font-size: 12px; }
        .copy-label { text-transform: uppercase; font-size: 8px; color: #64748b; font-weight: bold; }
        .row { width: 100%; }
        .row td { vertical-align: top; padding: 2px 0; }
        .right { text-align: right; }
        .muted { color: #64748b; }
        table.items { width: 100%; border-collapse: collapse; margin: 12px 0; }
        .items th, .items td { border: 1px solid #cbd5e1; padding: 4px; vertical-align: top; }
        .items th { background: #f1f5f9; text-align: left; }
        .items .right { text-align: right; }
        .summary { width: 48%; margin-left: auto; border-collapse: collapse; }
        .summary td { padding: 3px 4px; border-bottom: 1px solid #e2e8f0; }
        .total { font-weight: bold; font-size: 11px; }
        .thermal .items th, .thermal .items td { padding: 2px; font-size: 6px; }
        .thermal .summary { width: 100%; }
        .breakdown { font-size: 7px; color: #475569; }
        .payments { margin-top: 10px; border-top: 1px solid #cbd5e1; padding-top: 6px; }
        .footer { margin-top: 18px; border-top: 1px solid #cbd5e1; padding-top: 6px; text-align: center; color: #64748b; }
    </style>
</head>
<body class="{{ $format === 'thermal' ? 'thermal' : '' }}">
@php
    $customerName = $invoice->customer_name_snapshot ?: $invoice->customer?->name ?: 'Walk-in Customer';
    $customerPhone = $invoice->customer_phone_snapshot ?: $invoice->customer?->phone;
    $customerAddress = $invoice->customer_address ?: $invoice->customer?->address;
    $customerGstin = $invoice->customer_gstin ?: $invoice->customer?->gstin;
    $placeOfSupplyCode = preg_match('/^\d{2}/', (string) $customerGstin, $placeMatches)
        ? $placeMatches[0]
        : $profile['state_code'];
    $placeOfSupply = $invoice->branch?->state;
    $taxableSubtotal = max(0, (float) $invoice->subtotal - (float) $invoice->discount_amount);
@endphp
@foreach(($includeShopCopy ? ['Customer Copy', 'Shop Copy'] : ['Customer Copy']) as $copyLabel)
    <section class="copy">
        <div class="heading">
            @if($logoDataUri)
                <img class="logo" src="{{ $logoDataUri }}" alt="Shop logo"><br>
            @endif
            <div class="shop-name">{{ $profile['name'] ?: config('app.name') }}</div>
            <div>{{ $profile['address'] }}{{ $invoice->branch?->city ? ', '.$invoice->branch->city : '' }}</div>
            <div>Phone: {{ $profile['phone'] ?: '-' }} · GSTIN: {{ $profile['gstin'] ?: '-' }}</div>
            <div>State: {{ $profile['state'] ?: '-' }} · State code: {{ $profile['state_code'] ?: '-' }}</div>
            <div class="copy-label">{{ $invoice->invoice_type === 'estimate' ? 'Estimate / Quotation' : ($invoice->invoice_type === 'credit' ? 'Credit Sale' : 'Tax Invoice') }} · {{ $copyLabel }}</div>
        </div>

        <table class="row">
            <tr>
                <td>
                    <strong>Invoice:</strong> {{ $invoice->invoice_no }}<br>
                    <strong>Date:</strong> {{ $invoice->billed_at?->format('d M Y, h:i A') }}<br>
                    <strong>Branch:</strong> {{ $invoice->branch?->name ?: '-' }}
                </td>
                <td class="right">
                    <strong>Customer:</strong> {{ $customerName }}<br>
                    <strong>Mobile:</strong> {{ $customerPhone ?: '-' }}<br>
                    <strong>Address:</strong> {{ $customerAddress ?: '-' }}<br>
                    <strong>Customer GSTIN:</strong> {{ $customerGstin ?: '-' }}
                </td>
            </tr>
        </table>
        <div><strong>Place of supply:</strong> {{ $placeOfSupply ?: '-' }}{{ $placeOfSupplyCode ? ' ('.$placeOfSupplyCode.')' : '' }}</div>

        <table class="items">
            <thead>
                <tr>
                    <th>Jewellery / HSN</th>
                    <th class="right">Qty</th>
                    <th class="right">Gross / Stone / Net (g)</th>
                    <th class="right">Rate / Metal value</th>
                    <th class="right">Making / Wastage</th>
                    <th class="right">Stone / Diamond</th>
                    <th class="right">Discount</th>
                    <th class="right">Taxable / GST</th>
                    <th class="right">Line total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->product_name }}</strong><br>
                            <span class="muted">{{ $item->metal_type }} {{ $item->purity }} · SKU {{ $item->sku ?: $item->inventoryItem?->item_code ?: '-' }} · HSN {{ $item->hsn_code ?: '-' }}</span>
                            @if($item->huid ?? $item->inventoryItem?->huid)
                                <br><span class="muted">HUID {{ $item->huid ?: $item->inventoryItem?->huid }}</span>
                            @endif
                        </td>
                        <td class="right">{{ $item->quantity }}</td>
                        <td class="right">{{ number_format($item->gross_weight * $item->quantity, 3) }} / {{ number_format($item->stone_weight * $item->quantity, 3) }} / {{ number_format($item->net_weight * $item->quantity, 3) }}</td>
                        <td class="right">₹{{ number_format($item->metal_rate, 2) }}/g<br>₹{{ number_format($item->metal_value, 2) }}</td>
                        <td class="right">₹{{ number_format($item->making_charge_amount, 2) }}<br>{{ number_format($item->wastage_weight * $item->quantity, 3) }}g ({{ number_format($item->wastage_pct, 2) }}%) · ₹{{ number_format($item->wastage_value, 2) }}</td>
                        <td class="right">₹{{ number_format($item->stone_charge, 2) }} / ₹{{ number_format($item->diamond_charge, 2) }}</td>
                        <td class="right">₹{{ number_format($item->discount + $item->invoice_discount, 2) }}</td>
                        <td class="right">₹{{ number_format($item->taxable_value, 2) }}<br>{{ number_format($item->gst_rate, 2) }}% · ₹{{ number_format($item->gst_amount, 2) }}</td>
                        <td class="right">₹{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @foreach($invoice->oldGoldPurchases as $exchange)
            <div class="breakdown"><strong>Old gold received:</strong> {{ $exchange->item_description }} · {{ $exchange->metal_type }} {{ $exchange->tested_purity }} · {{ number_format($exchange->gross_weight, 3) }}g gross · {{ number_format($exchange->net_weight, 3) }}g net · ₹{{ number_format($exchange->valuation_amount, 2) }}</div>
        @endforeach

        <table class="summary">
            <tr><td>Taxable subtotal</td><td class="right">₹{{ number_format($taxableSubtotal, 2) }}</td></tr>
            <tr><td>Making charges (included above)</td><td class="right">₹{{ number_format($invoice->making_total, 2) }}</td></tr>
            @if($invoice->discount_amount > 0)
                <tr><td>Invoice discount</td><td class="right">- ₹{{ number_format($invoice->discount_amount, 2) }}</td></tr>
            @endif
            @if($invoice->cgst_amount > 0)
                <tr><td>CGST</td><td class="right">₹{{ number_format($invoice->cgst_amount, 2) }}</td></tr>
                <tr><td>SGST</td><td class="right">₹{{ number_format($invoice->sgst_amount, 2) }}</td></tr>
            @endif
            @if($invoice->igst_amount > 0)
                <tr><td>IGST</td><td class="right">₹{{ number_format($invoice->igst_amount, 2) }}</td></tr>
            @endif
            @if($invoice->old_gold_deduction > 0)
                <tr><td>Old gold exchange adjustment</td><td class="right">- ₹{{ number_format($invoice->old_gold_deduction, 2) }}</td></tr>
            @endif
            @if($invoice->advance_adjusted > 0)
                <tr><td>Advance adjustment</td><td class="right">- ₹{{ number_format($invoice->advance_adjusted, 2) }}</td></tr>
            @endif
            @if($invoice->scheme_adjusted > 0)
                <tr><td>Scheme adjustment</td><td class="right">- ₹{{ number_format($invoice->scheme_adjusted, 2) }}</td></tr>
            @endif
            <tr><td>Round-off</td><td class="right">₹{{ number_format($invoice->round_off, 2) }}</td></tr>
            <tr class="total"><td>Grand total</td><td class="right">₹{{ number_format($invoice->grand_total, 2) }}</td></tr>
            <tr><td>Amount received</td><td class="right">₹{{ number_format($invoice->amount_received, 2) }}</td></tr>
            <tr><td>Balance due</td><td class="right">₹{{ number_format($invoice->balance_due, 2) }}</td></tr>
        </table>

        <div class="payments">
            <strong>Payment method:</strong>
            {{ $invoice->payments->isEmpty() ? 'Not paid' : $invoice->payments->map(fn ($payment) => ucfirst(str_replace('_', ' ', $payment->method)) . ' ₹' . number_format($payment->amount, 2))->join(', ') }}
            <br><strong>Amount in words:</strong> {{ $amountInWords }}
        </div>
        <div class="footer">Thank you for shopping with {{ $profile['name'] ?: config('app.name') }}.</div>
    </section>
@endforeach
</body>
</html>
