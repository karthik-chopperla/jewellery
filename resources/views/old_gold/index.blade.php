@extends('layouts.app')

@section('header', 'Old Gold Exchange & Purchase')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 h-fit" x-data="{ gross: 10, stone: 0, purityPct: 91.6, rate: {{ $rate24k }}, ded: 2, melt: 1 }">
        <h2 class="text-base font-semibold text-slate-800 mb-3">Old Gold Valuation & Registration</h2>
        <form action="{{ route('old_gold.store') }}" method="POST" class="space-y-2.5 text-xs">
            @csrf
            <div class="grid grid-cols-2 gap-2">
                <select name="branch_id" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
                <select name="customer_id" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <option value="">Walk-in Customer</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <input type="text" name="item_description" required placeholder="Item description (e.g. Old 22K Bangles)" class="w-full border border-slate-300 rounded-lg px-3 py-2">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-slate-500">Gross Wt (g)</label>
                    <input type="number" step="0.001" name="gross_weight" x-model.number="gross" required class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                </div>
                <div>
                    <label class="text-slate-500">Stone Wt (g)</label>
                    <input type="number" step="0.001" name="stone_weight" x-model.number="stone" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="text-slate-500">Tested Purity</label>
                    <input type="text" name="tested_purity" value="22K" required class="w-full border border-slate-300 rounded-lg px-2 py-1.5">
                </div>
                <div>
                    <label class="text-slate-500">Purity %</label>
                    <input type="number" step="0.01" name="purity_pct" x-model.number="purityPct" required class="w-full border border-slate-300 rounded-lg px-2 py-1.5">
                </div>
                <div>
                    <label class="text-slate-500">24K Rate</label>
                    <input type="number" step="0.01" name="applied_rate" x-model.number="rate" required class="w-full border border-slate-300 rounded-lg px-2 py-1.5">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="text-slate-500">Deduction %</label>
                    <input type="number" step="0.1" name="deduction_pct" x-model.number="ded" class="w-full border border-slate-300 rounded-lg px-2 py-1.5">
                </div>
                <div>
                    <label class="text-slate-500">Melting Loss %</label>
                    <input type="number" step="0.1" name="melting_loss_pct" x-model.number="melt" class="w-full border border-slate-300 rounded-lg px-2 py-1.5">
                </div>
                <div>
                    <label class="text-slate-500">Settlement</label>
                    <select name="settlement_type" class="w-full border border-slate-300 rounded-lg px-2 py-1.5">
                        <option value="exchange">Exchange</option>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                </div>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 space-y-1">
                <div class="flex justify-between"><span>Net Weight:</span><span class="font-semibold" x-text="(gross - stone).toFixed(3) + ' g'"></span></div>
                <div class="flex justify-between"><span>Fine Gold Weight:</span><span class="font-semibold" x-text="((gross - stone) * (purityPct/100)).toFixed(3) + ' g'"></span></div>
                <div class="flex justify-between text-sm font-bold text-amber-800 border-t border-amber-200 pt-1">
                    <span>Estimated Valuation:</span>
                    <span>₹<span x-text="Math.round(((gross - stone) * (purityPct/100)) * (1 - (ded + melt)/100) * rate).toLocaleString('en-IN')"></span></span>
                </div>
            </div>

            <label class="flex items-center gap-2 text-slate-600">
                <input type="checkbox" name="customer_declaration" value="1" checked required>
                Customer ownership consent & KYC declaration obtained
            </label>
            <button class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 rounded-lg">Generate Old Gold Receipt</button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Old Gold Receipts & Refinery Transfers</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left">Receipt #</th>
                        <th class="px-3 py-2 text-left">Customer & Item</th>
                        <th class="px-3 py-2 text-right">Net / Fine Wt</th>
                        <th class="px-3 py-2 text-right">Valuation</th>
                        <th class="px-3 py-2 text-center">Status</th>
                        <th class="px-3 py-2 text-right">Refinery</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($purchases as $p)
                        <tr>
                            <td class="px-3 py-2 font-bold">{{ $p->receipt_no }}</td>
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $p->customer->name ?? 'Walk-in' }}</div>
                                <div class="text-slate-500">{{ $p->item_description }} ({{ $p->tested_purity }})</div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($p->net_weight, 3) }}g / <span class="text-amber-700 font-semibold">{{ number_format($p->fine_gold_weight, 3) }}g</span></td>
                            <td class="px-3 py-2 text-right font-bold">₹{{ number_format($p->valuation_amount, 2) }}</td>
                            <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-slate-100">{{ ucfirst(str_replace('_', ' ', $p->status)) }}</span></td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                @if($p->status !== 'refinery_transferred')
                                    <form action="{{ route('old_gold.refinery', $p) }}" method="POST" class="inline-flex gap-1">
                                        @csrf
                                        <input type="text" name="refinery_name" value="MMTC-PAMP" class="border rounded px-1.5 py-0.5 w-24 text-[11px]">
                                        <button class="bg-slate-800 text-white px-2 py-0.5 rounded text-[11px]">Send</button>
                                    </form>
                                @else
                                    <span class="text-emerald-700 font-medium">{{ $p->refinery_name }}</span>
                                @endif
                                <form action="{{ route('old_gold.destroy', $p) }}" method="POST" class="inline" onsubmit="return confirm('Delete old gold purchase {{ $p->receipt_no }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-[11px] text-rose-600 hover:underline ml-1.5">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $purchases->links() }}</div>
    </div>
</div>
@endsection

