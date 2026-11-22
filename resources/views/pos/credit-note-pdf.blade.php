<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; }
        .header { text-align: center; border-bottom: 1px solid #cbd5e1; padding-bottom: 12px; }
        .logo { max-width: 100px; max-height: 55px; }
        .shop { font-size: 18px; font-weight: bold; }
        .title { margin: 18px 0; text-align: center; font-size: 16px; font-weight: bold; }
        .details { width: 100%; margin: 12px 0; }
        .details td { vertical-align: top; padding: 4px; }
        .items { width: 100%; border-collapse: collapse; margin: 18px 0; }
        .items th, .items td { border: 1px solid #cbd5e1; padding: 7px; text-align: left; }
        .items th { background: #f1f5f9; }
        .right { text-align: right !important; }
        .summary { width: 50%; margin-left: auto; border-collapse: collapse; }
        .summary td { padding: 5px; border-bottom: 1px solid #e2e8f0; }
        .footer { margin-top: 40px; border-top: 1px solid #cbd5e1; padding-top: 10px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        @if($logoDataUri)<img class="logo" src="{{ $logoDataUri }}" alt="Shop logo"><br>@endif
        <div class="shop">{{ $profile['name'] ?: config('app.name') }}</div>
        <div>{{ $profile['address'] }}{{ $invoice->branch?->city ? ', '.$invoice->branch->city : '' }}</div>
        <div>{{ $profile['phone'] ?: '-' }} · GSTIN {{ $profile['gstin'] ?: '-' }}</div>
        <div>{{ $profile['state'] ?: '-' }} · State code {{ $profile['state_code'] ?: '-' }}</div>
    </div>
    <div class="title">CREDIT NOTE</div>

    <table class="details">
        <tr>
            <td>
                <strong>Credit note:</strong> {{ $return->credit_note_no }}<br>
                <strong>Return reference:</strong> {{ $return->return_no }}<br>
                <strong>Issued:</strong> {{ $return->created_at?->format('d M Y, h:i A') }}<br>
                <strong>Original invoice:</strong> {{ $invoice->invoice_no }}
            </td>
            <td class="right">
                <strong>Customer:</strong> {{ $invoice->customer_name_snapshot ?: $invoice->customer?->name ?: 'Walk-in Customer' }}<br>
                <strong>Mobile:</strong> {{ $invoice->customer_phone_snapshot ?: $invoice->customer?->phone ?: '-' }}<br>
                <strong>GSTIN:</strong> {{ $invoice->customer_gstin ?: $invoice->customer?->gstin ?: '-' }}<br>
                <strong>Reason:</strong> {{ $return->reason }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Returned item</th>
                <th>HSN</th>
                <th>Metal / purity</th>
                <th class="right">Qty / gross weight</th>
                <th class="right">Taxable value</th>
                <th class="right">GST rate / amount</th>
                <th class="right">Credit value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $return->invoiceItem?->product_name ?: 'Jewellery item' }}<br><span style="color:#64748b">{{ $return->condition }}</span></td>
                <td>{{ $return->invoiceItem?->hsn_code ?: '-' }}</td>
                <td>{{ $return->metal_type ?: $return->invoiceItem?->metal_type }} {{ $return->tested_purity }}</td>
                <td class="right">{{ $return->quantity }} / {{ number_format($return->return_weight, 3) }}g</td>
                <td class="right">₹{{ number_format($return->taxable_value, 2) }}</td>
                <td class="right">{{ number_format($return->invoiceItem?->gst_rate ?? 0, 2) }}% / ₹{{ number_format($return->cgst_amount + $return->sgst_amount + $return->igst_amount, 2) }}</td>
                <td class="right">₹{{ number_format($return->return_value, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="summary">
        @if($return->cgst_amount > 0)
            <tr><td>CGST reversed</td><td class="right">₹{{ number_format($return->cgst_amount, 2) }}</td></tr>
            <tr><td>SGST reversed</td><td class="right">₹{{ number_format($return->sgst_amount, 2) }}</td></tr>
        @endif
        @if($return->igst_amount > 0)
            <tr><td>IGST reversed</td><td class="right">₹{{ number_format($return->igst_amount, 2) }}</td></tr>
        @endif
        <tr><td><strong>Total credit value</strong></td><td class="right"><strong>₹{{ number_format($return->return_value, 2) }}</strong></td></tr>
        <tr><td>Refund amount</td><td class="right">₹{{ number_format($return->refund_amount, 2) }}</td></tr>
    </table>
    <p><strong>Amount in words:</strong> {{ $amountInWords }}</p>
    <div class="footer">This credit note references, and does not replace or alter, original invoice {{ $invoice->invoice_no }}.</div>
</body>
</html>
