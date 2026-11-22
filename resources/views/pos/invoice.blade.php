@extends('layouts.app')

@section('header', 'Tax Invoice — ' . $invoice->invoice_no)

@section('content')
<style>
    @media print {
        @if($format === 'thermal')
            @page { size: 80mm auto; margin: 3mm; }
            #invoice-paper.thermal-invoice { width: 74mm; max-width: 74mm; border: 0; border-radius: 0; padding: 0; font-size: 9pt; }
            #invoice-paper table { font-size: 7pt; }
            #invoice-paper th, #invoice-paper td { padding-left: 1mm; padding-right: 1mm; }
        @else
            @page { size: A4; margin: 12mm; }
            #invoice-paper { width: 100%; max-width: none; border: 0; border-radius: 0; padding: 0; }
        @endif
    }
</style>
<div class="space-y-4">
    <div class="no-print flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <a href="{{ route('pos.invoice', ['invoice' => $invoice, 'format' => 'a4']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $format === 'a4' ? 'bg-slate-900 text-white' : 'bg-white border border-slate-300' }}">A4 Tax Invoice</a>
            <a href="{{ route('pos.invoice', ['invoice' => $invoice, 'format' => 'thermal']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $format === 'thermal' ? 'bg-slate-900 text-white' : 'bg-white border border-slate-300' }}">80mm Thermal Receipt</a>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('pos.invoice.pdf', ['invoice' => $invoice, 'format' => $format]) }}" target="_blank" rel="noopener noreferrer" class="bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs font-semibold">Open PDF</a>
            <a href="{{ route('pos.invoice.pdf', ['invoice' => $invoice, 'format' => $format, 'download' => 1]) }}" class="bg-slate-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold">Download PDF</a>
            <button onclick="window.print()" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-1.5 rounded-lg text-xs font-semibold">Print / Save PDF</button>
            <form action="{{ route('pos.invoice.whatsapp', $invoice) }}" method="POST">
                @csrf
                <button class="bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold">Share PDF on WhatsApp</button>
            </form>
            <a href="{{ route('pos.invoices', ['return_invoice' => $invoice->id]) }}" class="bg-white border border-slate-300 px-3 py-1.5 rounded-lg text-xs font-medium">Register Return</a>
            @if(auth()->user()->hasPermission('pos_manage'))
                <a href="{{ route('pos.invoice.exchange', $invoice) }}" class="bg-amber-100 text-amber-900 px-3 py-1.5 rounded-lg text-xs font-semibold">Exchange Jewellery</a>
            @endif
            <a href="{{ route('pos.index') }}" class="bg-white border border-slate-300 px-3 py-1.5 rounded-lg text-xs font-medium">← Back to POS</a>
        </div>
    </div>

    <div id="invoice-paper" class="bg-white border border-slate-200 rounded-xl p-6 mx-auto {{ $format === 'thermal' ? 'thermal-invoice max-w-[340px] text-xs' : 'max-w-4xl text-sm' }}">
        <div class="text-center border-b border-slate-200 pb-4 mb-4">
            <div class="text-xl font-bold text-slate-900">{{ strtoupper($businessName) }}</div>
            <div class="text-xs text-slate-500">{{ $invoice->branch->name }} · {{ $invoice->branch->address }}, {{ $invoice->branch->city }}</div>
            <div class="text-xs text-slate-500">GSTIN: {{ $invoice->branch->gstin }} · Ph: {{ $invoice->branch->phone }}</div>
            <div class="mt-2 inline-block px-3 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold text-xs uppercase">{{ $invoice->invoice_type === 'estimate' ? 'Estimate / Quotation' : ($invoice->invoice_type === 'credit' ? 'Credit Sale' : 'Tax Invoice') }}</div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4 text-xs">
            <div>
                <div><span class="text-slate-500">Invoice No:</span> <span class="font-bold">{{ $invoice->invoice_no }}</span></div>
                <div><span class="text-slate-500">Date:</span> {{ $invoice->billed_at->format('d M Y, h:i A') }}</div>
                <div><span class="text-slate-500">Salesperson:</span> {{ $invoice->salesperson->name ?? '-' }}</div>
            </div>
            <div class="text-right">
                <div><span class="text-slate-500">Customer:</span> <span class="font-bold">{{ $invoice->customer_name_snapshot ?? $invoice->customer->name ?? 'Walk-in Customer' }}</span></div>
                <div><span class="text-slate-500">Phone:</span> {{ $invoice->customer_phone_snapshot ?? $invoice->customer->phone ?? '-' }}</div>
                <div><span class="text-slate-500">GSTIN:</span> {{ $invoice->customer_gstin ?? $invoice->customer->gstin ?? '-' }}</div>
                @if($invoice->customer_address ?? $invoice->customer?->address)
                    <div><span class="text-slate-500">Address:</span> {{ $invoice->customer_address ?? $invoice->customer->address }}</div>
                @endif
            </div>
        </div>

        <table class="w-full border-collapse text-xs mb-4">
            <thead>
                <tr class="border-y border-slate-300 bg-slate-50">
                    <th class="py-2 px-2 text-left">Item / HSN</th>
                    <th class="py-2 px-2 text-right">Qty</th>
                    <th class="py-2 px-2 text-right">Gross / Net</th>
                    <th class="py-2 px-2 text-right">Rate</th>
                    <th class="py-2 px-2 text-right">Making</th>
                    <th class="py-2 px-2 text-right">Taxable / GST</th>
                    <th class="py-2 px-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($invoice->items as $item)
                    <tr>
                        <td class="py-2 px-2">
                            <div class="font-medium">{{ $item->product_name }} ({{ $item->metal_type }} {{ $item->purity }})</div>
                            <div class="text-[10px] text-slate-500">SKU: {{ $item->sku ?? '-' }} · HSN: {{ $item->hsn_code ?? '-' }}</div>
                            @if($item->huid ?? $item->inventoryItem?->huid)
                                <div class="text-[10px] text-slate-500">HUID: {{ $item->huid ?? $item->inventoryItem->huid }}</div>
                            @endif
                        </td>
                        <td class="py-2 px-2 text-right">{{ $item->quantity }}</td>
                        <td class="py-2 px-2 text-right">{{ number_format($item->gross_weight * $item->quantity, 3) }} / {{ number_format($item->net_weight * $item->quantity, 3) }}g</td>
                        <td class="py-2 px-2 text-right">₹{{ number_format($item->metal_rate, 0) }}</td>
                        <td class="py-2 px-2 text-right">₹{{ number_format($item->making_charge_amount, 2) }}</td>
                        <td class="py-2 px-2 text-right">₹{{ number_format($item->taxable_value, 2) }}<br><span class="text-slate-500">{{ number_format($item->gst_rate, 2) }}% · ₹{{ number_format($item->gst_amount, 2) }}</span></td>
                        <td class="py-2 px-2 text-right font-semibold">₹{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="border-t border-slate-300 pt-3 space-y-1 text-xs max-w-xs ml-auto">
            <div class="flex justify-between"><span>Taxable Subtotal:</span><span>₹{{ number_format($invoice->subtotal, 2) }}</span></div>
            @if($invoice->discount_amount > 0)
                <div class="flex justify-between"><span>Invoice Discount:</span><span>- ₹{{ number_format($invoice->discount_amount, 2) }}</span></div>
            @endif
            <div class="flex justify-between"><span>Making Charges:</span><span>₹{{ number_format($invoice->making_total, 2) }}</span></div>
            @foreach($invoice->oldGoldPurchases as $exchange)
                <div class="border-t border-slate-200 mt-1 pt-1">
                    <div class="font-semibold">Exchange received: {{ $exchange->item_description }} ({{ $exchange->metal_type }})</div>
                    <div>{{ number_format($exchange->gross_weight, 3) }}g gross · {{ number_format($exchange->net_weight, 3) }}g net · {{ $exchange->tested_purity }} ({{ number_format($exchange->purity_pct, 2) }}%)</div>
                    <div>Valuation rate: ₹{{ number_format($exchange->applied_rate, 2) }}/g · Value: ₹{{ number_format($exchange->valuation_amount, 2) }}</div>
                    <div>Customer acknowledgement recorded.</div>
                </div>
            @endforeach
            @if($invoice->cgst_amount > 0)
                <div class="flex justify-between"><span>CGST:</span><span>₹{{ number_format($invoice->cgst_amount, 2) }}</span></div>
                <div class="flex justify-between"><span>SGST:</span><span>₹{{ number_format($invoice->sgst_amount, 2) }}</span></div>
            @endif
            @if($invoice->igst_amount > 0)
                <div class="flex justify-between"><span>IGST:</span><span>₹{{ number_format($invoice->igst_amount, 2) }}</span></div>
            @endif
            @if($invoice->round_off != 0)
                <div class="flex justify-between"><span>Round-off:</span><span>₹{{ number_format($invoice->round_off, 2) }}</span></div>
            @endif
            @if($invoice->old_gold_deduction > 0)
                <div class="flex justify-between text-emerald-700"><span>Old Gold Exchange:</span><span>- ₹{{ number_format($invoice->old_gold_deduction, 2) }}</span></div>
            @endif
            @if($invoice->advance_adjusted > 0)
                <div class="flex justify-between"><span>Advance Adjusted:</span><span>- ₹{{ number_format($invoice->advance_adjusted, 2) }}</span></div>
            @endif
            @if($invoice->scheme_adjusted > 0)
                <div class="flex justify-between"><span>Scheme Adjusted:</span><span>- ₹{{ number_format($invoice->scheme_adjusted, 2) }}</span></div>
            @endif
            <div class="flex justify-between text-base font-bold border-t border-slate-300 pt-1.5">
                <span>Grand Total:</span><span>₹{{ number_format($invoice->grand_total, 2) }}</span>
            </div>
            <div class="flex justify-between text-slate-600"><span>Amount Received:</span><span>₹{{ number_format($invoice->amount_received, 2) }}</span></div>
            @if($invoice->change_return > 0)
                <div class="flex justify-between text-slate-600"><span>Change Returned:</span><span>₹{{ number_format($invoice->change_return, 2) }}</span></div>
            @endif
            <div class="flex justify-between text-slate-600"><span>Paid:</span><span>₹{{ number_format($invoice->paid_amount, 2) }}</span></div>
            @if($invoice->balance_due > 0)
                <div class="flex justify-between text-rose-600 font-semibold"><span>Balance Due:</span><span>₹{{ number_format($invoice->balance_due, 2) }}</span></div>
            @endif
        </div>

        <div class="mt-4 text-xs text-slate-600"><span class="font-semibold">Amount in words:</span> {{ $amountInWords }}</div>
        @if($invoice->payments->isNotEmpty())
            <div class="mt-3 border-t border-slate-200 pt-2 text-xs">
                <div class="font-semibold mb-1">Payment Details</div>
                @foreach($invoice->payments as $payment)
                    <div class="flex justify-between"><span>{{ str_replace('_', ' ', ucfirst($payment->method)) }} {{ $payment->reference_no ? '· ' . $payment->reference_no : '' }}</span><span>₹{{ number_format($payment->amount, 2) }}</span></div>
                @endforeach
            </div>
        @endif

        <div class="mt-6 pt-3 border-t border-slate-200 text-center text-[11px] text-slate-500">
            Thank you for shopping with {{ $businessName ?? 'Abharan ERP' }}. BIS 100% Hallmarked Jewellery.
        </div>
    </div>

    @if($invoice->shares->isNotEmpty())
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-xs">
            <h3 class="font-semibold text-slate-800 mb-2">Invoice sharing history</h3>
            <div class="space-y-2">
                @foreach($invoice->shares as $share)
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-2">
                        <span>{{ $share->phone_snapshot }} · {{ str_replace('_', ' ', ucfirst($share->status)) }} · {{ $share->created_at->format('d M Y, h:i A') }} · {{ $share->access_count }} PDF access(es)</span>
                        @if($share->status !== 'revoked' && $share->expires_at->isFuture() && auth()->user()->hasPermission('pos_manage'))
                            <form action="{{ route('pos.invoice-shares.revoke', $share) }}" method="POST">
                                @csrf
                                <button class="text-rose-700 hover:underline">Revoke link</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($invoice->exchangeAsReplacement || $invoice->exchangesAsOriginal->isNotEmpty())
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-xs">
            <h3 class="font-semibold text-slate-800 mb-2">Exchange history</h3>
            @if($invoice->exchangeAsReplacement)
                <p>Replacement sale for <a class="text-amber-700 underline" href="{{ route('pos.invoice', $invoice->exchangeAsReplacement->originalInvoice) }}">{{ $invoice->exchangeAsReplacement->originalInvoice->invoice_no }}</a></p>
            @endif
            @foreach($invoice->exchangesAsOriginal as $exchange)
                <p class="border-t border-slate-100 pt-2 mt-2">
                    {{ $exchange->exchange_no }} · credit note <a class="text-amber-700 underline" href="{{ route('pos.returns.credit-note', $exchange->salesReturn) }}" target="_blank" rel="noopener noreferrer">{{ $exchange->salesReturn->credit_note_no }}</a> ·
                    replacement <a class="text-amber-700 underline" href="{{ route('pos.invoice', $exchange->replacementInvoice) }}">{{ $exchange->replacementInvoice->invoice_no }}</a> ·
                    credit ₹{{ number_format($exchange->credit_applied, 2) }} · refund ₹{{ number_format($exchange->refund_amount, 2) }}
                </p>
            @endforeach
        </div>
    @endif
</div>
@endsection

