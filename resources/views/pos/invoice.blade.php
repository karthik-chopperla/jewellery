@extends('layouts.app')

@section('header', 'Tax Invoice — ' . $invoice->invoice_no)

@section('content')
<div class="space-y-4">
    <div class="no-print flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <a href="{{ route('pos.invoice', ['invoice' => $invoice, 'format' => 'a4']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $format === 'a4' ? 'bg-slate-900 text-white' : 'bg-white border border-slate-300' }}">A4 Tax Invoice</a>
            <a href="{{ route('pos.invoice', ['invoice' => $invoice, 'format' => 'thermal']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $format === 'thermal' ? 'bg-slate-900 text-white' : 'bg-white border border-slate-300' }}">80mm Thermal Receipt</a>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-1.5 rounded-lg text-xs font-semibold">🖨️ Print Invoice</button>
            <a href="{{ route('pos.index') }}" class="bg-white border border-slate-300 px-3 py-1.5 rounded-lg text-xs font-medium">← Back to POS</a>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6 mx-auto {{ $format === 'thermal' ? 'max-w-[340px] text-xs' : 'max-w-4xl text-sm' }}">
        <div class="text-center border-b border-slate-200 pb-4 mb-4">
            <div class="text-xl font-bold text-slate-900">{{ strtoupper($businessName) }}</div>
            <div class="text-xs text-slate-500">{{ $invoice->branch->name }} · {{ $invoice->branch->address }}, {{ $invoice->branch->city }}</div>
            <div class="text-xs text-slate-500">GSTIN: {{ $invoice->branch->gstin }} · Ph: {{ $invoice->branch->phone }}</div>
            <div class="mt-2 inline-block px-3 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold text-xs uppercase">{{ $invoice->invoice_type }} Invoice</div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4 text-xs">
            <div>
                <div><span class="text-slate-500">Invoice No:</span> <span class="font-bold">{{ $invoice->invoice_no }}</span></div>
                <div><span class="text-slate-500">Date:</span> {{ $invoice->billed_at->format('d M Y, h:i A') }}</div>
                <div><span class="text-slate-500">Salesperson:</span> {{ $invoice->salesperson->name ?? '-' }}</div>
            </div>
            <div class="text-right">
                <div><span class="text-slate-500">Customer:</span> <span class="font-bold">{{ $invoice->customer->name ?? 'Walk-in Customer' }}</span></div>
                <div><span class="text-slate-500">Phone:</span> {{ $invoice->customer->phone ?? '-' }}</div>
                <div><span class="text-slate-500">PAN:</span> {{ $invoice->customer->pan_number ?? '-' }}</div>
            </div>
        </div>

        <table class="w-full border-collapse text-xs mb-4">
            <thead>
                <tr class="border-y border-slate-300 bg-slate-50">
                    <th class="py-2 px-2 text-left">Item / HUID</th>
                    <th class="py-2 px-2 text-right">Net Wt</th>
                    <th class="py-2 px-2 text-right">Rate</th>
                    <th class="py-2 px-2 text-right">Making</th>
                    <th class="py-2 px-2 text-right">GST</th>
                    <th class="py-2 px-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($invoice->items as $item)
                    <tr>
                        <td class="py-2 px-2">
                            <div class="font-medium">{{ $item->product_name }} ({{ $item->purity }})</div>
                            @if($item->inventoryItem?->huid)
                                <div class="text-[10px] text-slate-500">HUID: {{ $item->inventoryItem->huid }}</div>
                            @endif
                        </td>
                        <td class="py-2 px-2 text-right">{{ number_format($item->net_weight, 3) }}g</td>
                        <td class="py-2 px-2 text-right">₹{{ number_format($item->metal_rate, 0) }}</td>
                        <td class="py-2 px-2 text-right">₹{{ number_format($item->making_charge_amount, 2) }}</td>
                        <td class="py-2 px-2 text-right">₹{{ number_format($item->gst_amount, 2) }}</td>
                        <td class="py-2 px-2 text-right font-semibold">₹{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="border-t border-slate-300 pt-3 space-y-1 text-xs max-w-xs ml-auto">
            <div class="flex justify-between"><span>Taxable Subtotal:</span><span>₹{{ number_format($invoice->subtotal, 2) }}</span></div>
            @if($invoice->cgst_amount > 0)
                <div class="flex justify-between"><span>CGST (1.5%):</span><span>₹{{ number_format($invoice->cgst_amount, 2) }}</span></div>
                <div class="flex justify-between"><span>SGST (1.5%):</span><span>₹{{ number_format($invoice->sgst_amount, 2) }}</span></div>
            @endif
            @if($invoice->igst_amount > 0)
                <div class="flex justify-between"><span>IGST (3%):</span><span>₹{{ number_format($invoice->igst_amount, 2) }}</span></div>
            @endif
            @if($invoice->old_gold_deduction > 0)
                <div class="flex justify-between text-emerald-700"><span>Old Gold Exchange:</span><span>- ₹{{ number_format($invoice->old_gold_deduction, 2) }}</span></div>
            @endif
            <div class="flex justify-between text-base font-bold border-t border-slate-300 pt-1.5">
                <span>Grand Total:</span><span>₹{{ number_format($invoice->grand_total, 2) }}</span>
            </div>
            <div class="flex justify-between text-slate-600"><span>Paid:</span><span>₹{{ number_format($invoice->paid_amount, 2) }}</span></div>
            @if($invoice->balance_due > 0)
                <div class="flex justify-between text-rose-600 font-semibold"><span>Balance Due:</span><span>₹{{ number_format($invoice->balance_due, 2) }}</span></div>
            @endif
        </div>

        <div class="mt-6 pt-3 border-t border-slate-200 text-center text-[11px] text-slate-500">
            Thank you for shopping with {{ $businessName ?? 'JewelERP' }}. BIS 100% Hallmarked Jewellery.
        </div>
    </div>
</div>
@endsection

