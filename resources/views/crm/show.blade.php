@extends('layouts.app')

@section('header', 'Customer Profile — ' . $customer->name)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
        <div>
            <div class="text-xs text-slate-400">{{ $customer->customer_code }}</div>
            <h2 class="text-lg font-bold text-slate-900">{{ $customer->name }}</h2>
            <div class="text-xs text-slate-600 mt-1">{{ $customer->phone }} · {{ $customer->email }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $customer->address }}</div>
            <div class="mt-2 inline-block px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 text-xs font-bold uppercase">{{ $customer->group_tier }} Member</div>
        </div>

        <div class="border-t border-slate-100 pt-3 text-xs space-y-1">
            <div><span class="text-slate-500">PAN:</span> {{ $customer->pan_number ?? '-' }}</div>
            <div><span class="text-slate-500">DOB:</span> {{ $customer->dob?->format('d M Y') ?? '-' }}</div>
            <div><span class="text-slate-500">Anniversary:</span> {{ $customer->anniversary?->format('d M Y') ?? '-' }}</div>
            <div><span class="text-slate-500">Loyalty Points:</span> <strong class="text-emerald-700">{{ $customer->loyalty_points }}</strong></div>
            <div><span class="text-slate-500">Outstanding:</span> <strong class="text-rose-600">₹{{ number_format($customer->outstanding_balance, 2) }}</strong></div>
            <div><span class="text-slate-500">Available advance:</span> <strong class="text-emerald-700">₹{{ number_format($customer->advance_balance, 2) }}</strong></div>
        </div>

        <form action="{{ route('crm.advance', $customer) }}" method="POST" class="border-t border-slate-100 pt-3 space-y-2 text-xs">
            @csrf
            <div class="font-semibold text-slate-700">Record Customer Advance</div>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" name="amount" min="0.01" step="0.01" required placeholder="Amount received ₹" class="border border-slate-300 rounded-lg px-2 py-1.5">
                <select name="payment_method" required class="border border-slate-300 rounded-lg px-2 py-1.5">
                    <option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option>
                </select>
            </div>
            <input type="text" name="reference_no" maxlength="80" placeholder="Transaction reference (optional)" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
            <input type="text" name="description" maxlength="255" placeholder="Note (optional)" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
            <button class="w-full bg-emerald-700 text-white py-1.5 rounded-lg font-semibold">Record Advance</button>
        </form>

        <form action="{{ route('crm.loyalty', $customer) }}" method="POST" class="border-t border-slate-100 pt-3 space-y-2 text-xs">
            @csrf
            <div class="font-semibold text-slate-700">Adjust / Redeem Loyalty Points</div>
            <div class="grid grid-cols-2 gap-2">
                <select name="type" class="border border-slate-300 rounded-lg px-2 py-1.5">
                    <option value="earn">Add Points</option>
                    <option value="redeem">Redeem Points</option>
                </select>
                <input type="number" name="points" min="1" required placeholder="Points" class="border border-slate-300 rounded-lg px-2 py-1.5">
            </div>
            <input type="text" name="description" required placeholder="Reason / Reference" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5">
            <button class="w-full bg-slate-900 text-white py-1.5 rounded-lg font-semibold">Update Points</button>
        </form>
    </div>

    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Purchase History</h3>
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr><th class="px-2 py-2 text-left">Invoice</th><th class="px-2 py-2 text-left">Date</th><th class="px-2 py-2 text-right">Amount</th><th class="px-2 py-2 text-center">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($customer->invoices as $inv)
                        <tr>
                            <td class="px-2 py-2 font-medium"><a href="{{ route('pos.invoice', $inv) }}" class="text-amber-700 hover:underline">{{ $inv->invoice_no }}</a></td>
                            <td class="px-2 py-2">{{ $inv->billed_at->format('d M Y') }}</td>
                            <td class="px-2 py-2 text-right font-semibold">₹{{ number_format($inv->grand_total, 2) }}</td>
                            <td class="px-2 py-2 text-center">{{ ucfirst($inv->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Customer Advance Ledger</h3>
            <div class="space-y-1.5 text-xs">
                @forelse($customer->advanceTransactions as $advance)
                    <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 pb-1">
                        <span>{{ $advance->description ?? ucfirst($advance->type) }} · {{ $advance->created_at->format('d M Y') }} · {{ ucfirst(str_replace('_', ' ', $advance->payment_method ?? 'invoice adjustment')) }}</span>
                        <span class="font-semibold {{ $advance->type === 'applied' ? 'text-rose-600' : 'text-emerald-700' }}">{{ $advance->type === 'applied' ? '-' : '+' }}₹{{ number_format($advance->amount, 2) }} <span class="font-normal text-slate-500">(balance ₹{{ number_format($advance->balance_after, 2) }})</span></span>
                    </div>
                @empty
                    <p class="text-slate-500">No customer advances recorded.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Loyalty Ledger</h3>
            <div class="space-y-1.5 text-xs">
                @foreach($customer->loyaltyTransactions as $lt)
                    <div class="flex justify-between border-b border-slate-100 pb-1">
                        <span>{{ $lt->description }} ({{ ucfirst($lt->type) }})</span>
                        <span class="font-semibold {{ $lt->type === 'redeem' ? 'text-rose-600' : 'text-emerald-700' }}">{{ $lt->type === 'redeem' ? '-' : '+' }}{{ $lt->points }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

