@extends('layouts.app')

@section('header', 'Gold Savings & Jewellery Purchase Schemes')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 h-fit">
        <h2 class="text-base font-semibold text-slate-800 mb-3">Enroll Customer in Gold Scheme</h2>
        <form action="{{ route('schemes.store') }}" method="POST" class="space-y-3 text-xs">
            @csrf
            <select name="branch_id" class="w-full border border-slate-300 rounded-lg px-3 py-2">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
            <select name="customer_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2">@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone }})</option>@endforeach</select>
            <input type="text" name="scheme_name" value="Jewel Dhan 11+1 Plan" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
            <div class="grid grid-cols-2 gap-2">
                <input type="number" name="monthly_amount" value="5000" required placeholder="Monthly ₹" class="border border-slate-300 rounded-lg px-2.5 py-2">
                <input type="number" name="duration_months" value="11" required placeholder="Months" class="border border-slate-300 rounded-lg px-2.5 py-2">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" name="bonus_amount" value="5000" placeholder="Maturity Bonus ₹" class="border border-slate-300 rounded-lg px-2.5 py-2">
                <input type="date" name="start_date" value="{{ date('Y-m-d') }}" required class="border border-slate-300 rounded-lg px-2.5 py-2">
            </div>
            <button class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 rounded-lg">Enroll & Generate Installments</button>
        </form>
    </div>

    <div class="lg:col-span-2 space-y-4">
        @foreach($schemes as $scheme)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 mb-3">
                    <div>
                        <div class="font-bold text-slate-900">{{ $scheme->scheme_no }} — {{ $scheme->scheme_name }}</div>
                        <div class="text-xs text-slate-500">{{ $scheme->customer->name ?? '' }} · ₹{{ number_format($scheme->monthly_amount, 0) }}/mo × {{ $scheme->duration_months }} mos · Bonus: ₹{{ number_format($scheme->bonus_amount, 0) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold text-emerald-700">Paid: ₹{{ number_format($scheme->total_paid, 0) }}</div>
                        <div class="text-[11px] text-slate-400">Matures: {{ $scheme->maturity_date->format('d M Y') }}</div>
                        <form action="{{ route('schemes.destroy', $scheme) }}" method="POST" class="inline" onsubmit="return confirm('Delete scheme {{ $scheme->scheme_no }} and all installment records?');">
                            @csrf
                            @method('DELETE')
                            <button class="text-[11px] text-rose-600 hover:underline">Delete Scheme</button>
                        </form>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2">
                    @foreach($scheme->installments as $inst)
                        <div class="border rounded-lg p-2 text-center text-xs {{ $inst->status === 'paid' ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200' }}">
                            <div class="font-semibold">Inst #{{ $inst->installment_no }}</div>
                            <div class="text-[11px] text-slate-500">{{ $inst->due_date->format('M Y') }}</div>
                            @if($inst->status === 'paid')
                                <div class="text-[11px] text-emerald-700 font-bold mt-1">✓ Paid</div>
                            @else
                                <form action="{{ route('schemes.pay', $inst) }}" method="POST" class="mt-1">
                                    @csrf
                                    <button class="bg-amber-600 text-white px-2 py-0.5 rounded text-[10px] font-semibold">Collect ₹{{ number_format($inst->amount, 0) }}</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

