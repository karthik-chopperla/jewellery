@extends('layouts.app')

@section('header', 'Business Intelligence & Comprehensive Reports')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500">Daily Sales</div>
            <div class="text-xl font-bold text-slate-900 mt-1">₹{{ number_format($dailySales, 0) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500">Monthly Sales</div>
            <div class="text-xl font-bold text-emerald-700 mt-1">₹{{ number_format($monthlySales, 0) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500">Old Gold Exchange Total</div>
            <div class="text-xl font-bold text-amber-700 mt-1">₹{{ number_format($oldGoldTotal, 0) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500">Supplier Purchases</div>
            <div class="text-xl font-bold text-slate-900 mt-1">₹{{ number_format($purchaseTotal, 0) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Purity-wise Sales Report</h2>
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase"><tr><th class="px-3 py-2 text-left">Purity</th><th class="px-3 py-2 text-right">Net Weight</th><th class="px-3 py-2 text-right">Sales Value</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($puritySales as $ps)
                        <tr><td class="px-3 py-2 font-bold">{{ $ps->purity }}</td><td class="px-3 py-2 text-right">{{ number_format($ps->weight, 3) }}g</td><td class="px-3 py-2 text-right font-semibold">₹{{ number_format($ps->total, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Weight-wise Stock Report</h2>
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase"><tr><th class="px-3 py-2 text-left">Metal & Purity</th><th class="px-3 py-2 text-right">Pieces</th><th class="px-3 py-2 text-right">Net Weight</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($metalStock as $ms)
                        <tr><td class="px-3 py-2 font-bold">{{ $ms->metal_type }} {{ $ms->purity }}</td><td class="px-3 py-2 text-right">{{ $ms->pieces }}</td><td class="px-3 py-2 text-right font-semibold text-amber-700">{{ number_format($ms->net_weight, 3) }}g</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Branch Performance Comparison</h2>
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase"><tr><th class="px-3 py-2 text-left">Branch</th><th class="px-3 py-2 text-right">Making Revenue</th><th class="px-3 py-2 text-right">Total Sales</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($branchComparison as $bc)
                        <tr><td class="px-3 py-2 font-bold">{{ $bc->name }}</td><td class="px-3 py-2 text-right">₹{{ number_format($bc->making_revenue ?? 0, 2) }}</td><td class="px-3 py-2 text-right font-semibold">₹{{ number_format($bc->sales ?? 0, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Staff Sales Performance</h2>
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase"><tr><th class="px-3 py-2 text-left">Staff</th><th class="px-3 py-2 text-right">Bills</th><th class="px-3 py-2 text-right">Total Sales</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($staffPerformance as $sp)
                        <tr><td class="px-3 py-2 font-bold">{{ $sp->name }}</td><td class="px-3 py-2 text-right">{{ $sp->invoice_count }}</td><td class="px-3 py-2 text-right font-semibold text-emerald-700">₹{{ number_format($sp->total_sales ?? 0, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

