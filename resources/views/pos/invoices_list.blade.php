@extends('layouts.app')

@section('header', 'Invoices, Sales Returns & Exchanges')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-slate-800">All Invoices</h2>
            <form method="GET" class="flex flex-wrap gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice, customer or mobile" class="border border-slate-300 rounded-lg px-3 py-1.5 text-xs">
                <input type="date" name="from" value="{{ request('from') }}" class="border border-slate-300 rounded-lg px-2 py-1.5 text-xs" aria-label="From date">
                <input type="date" name="to" value="{{ request('to') }}" class="border border-slate-300 rounded-lg px-2 py-1.5 text-xs" aria-label="To date">
                <select name="status" class="border border-slate-300 rounded-lg px-2 py-1.5 text-xs">
                    <option value="">All statuses</option>
                    @foreach(['paid', 'partial', 'credit', 'estimate', 'returned', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status === 'cancelled' ? 'Voided' : ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button class="bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs">Search</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-slate-500 uppercase bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Invoice #</th>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Customer</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-center">Status</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($invoices as $inv)
                        <tr>
                            <td class="px-3 py-2 font-medium"><a href="{{ route('pos.invoice', $inv) }}" class="text-amber-700 hover:underline">{{ $inv->invoice_no }}</a></td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $inv->billed_at->format('d M Y') }}</td>
                            <td class="px-3 py-2">{{ $inv->customer->name ?? 'Walk-in' }}</td>
                            <td class="px-3 py-2 text-right font-semibold">₹{{ number_format($inv->grand_total, 2) }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $inv->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($inv->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">{{ ucfirst($inv->status) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right space-x-2">
                                <a href="{{ route('pos.invoice', $inv) }}" class="text-xs text-slate-600 hover:underline">Print</a>
                                @if($inv->status !== 'cancelled' && auth()->user()->hasPermission('pos_manage'))
                                    <form action="{{ route('pos.cancel', $inv) }}" method="POST" class="inline">
                                        @csrf
                                        <button class="text-xs text-rose-600 hover:underline">Cancel</button>
                                    </form>
                                @endif
                                @if(auth()->user()->hasPermission('pos_manage'))
                                <form action="{{ route('pos.invoices.destroy', $inv) }}" method="POST" class="inline" onsubmit="return confirm('Delete invoice {{ $inv->invoice_no }}? This will restore stock.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs text-rose-700 font-medium hover:underline">Delete</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Register Sales Return / Exchange</h2>
            <form action="{{ route('pos.returns.store') }}" method="POST" class="space-y-3 text-sm">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Invoice item to return</label>
                    <select name="invoice_item_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs">
                        <option value="">Select an invoice item</option>
                        @foreach($returnableItems as $item)
                            @if($item->returnable_quantity > 0)
                                <option value="{{ $item->id }}" @selected(request('return_item') == $item->id)>{{ $item->invoice->invoice_no }} — {{ $item->product_name }} ({{ $item->returnable_quantity }} remaining)</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <select name="return_type" required class="border border-slate-300 rounded-lg px-2.5 py-2 text-xs">
                        <option value="refund">Refund</option>
                        <option value="exchange">Exchange</option>
                        <option value="return">Return</option>
                    </select>
                    <input type="text" name="condition" value="Good / Intact" required maxlength="30" class="border border-slate-300 rounded-lg px-2.5 py-2 text-xs" placeholder="Condition">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" name="quantity" min="1" step="1" value="1" required placeholder="Quantity" class="border border-slate-300 rounded-lg px-2.5 py-2 text-xs">
                    <select name="refund_method" class="border border-slate-300 rounded-lg px-2.5 py-2 text-xs">
                        <option value="">Refund method (if refund)</option>
                        <option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option>
                    </select>
                </div>
                <input type="number" step="0.01" min="0" name="refund_amount" placeholder="Refund amount (blank = full returned value)" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs">
                <input type="text" name="reason" required maxlength="255" placeholder="Return reason..." class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs">
                <p class="text-[11px] text-slate-500">Return value is calculated from the original invoice item. Manager approval may be required.</p>
                <button class="w-full bg-slate-900 text-white py-2 rounded-lg text-xs font-semibold">Submit Return</button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Recent Returns</h3>
            <div class="space-y-2 text-xs">
                @forelse($returns as $ret)
                    <div class="border-b border-slate-100 pb-2 flex justify-between items-center">
                        <div>
                            <div class="font-semibold">{{ $ret->return_no }} ({{ $ret->return_type }})</div>
                            <div class="text-slate-500">{{ $ret->invoice->invoice_no ?? '' }} · {{ $ret->reason }}</div>
                            <div class="text-slate-500">{{ $ret->credit_note_no }} · {{ ucfirst($ret->status) }}
                                @if($ret->credit_note_no)
                                    · <a href="{{ route('pos.returns.credit-note', $ret) }}" target="_blank" rel="noopener noreferrer" class="text-amber-700 underline">Credit note</a>
                                @endif
                            </div>
                        </div>
                        <div class="text-right flex items-center gap-2">
                            <span class="font-semibold">₹{{ number_format($ret->refund_amount, 0) }}</span>
                            @if($ret->status === 'pending_approval' && auth()->user()->hasPermission('pos_manage'))
                                <form action="{{ route('pos.returns.approve', $ret) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="text-[11px] text-emerald-700 hover:underline">Approve</button>
                                </form>
                            @endif
                            @if(auth()->user()->hasPermission('pos_manage'))
                            <form action="{{ route('pos.returns.destroy', $ret) }}" method="POST" class="inline" onsubmit="return confirm('Delete return record {{ $ret->return_no }}?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-[11px] text-rose-600 hover:underline">Delete</button>
                            </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-slate-400">No returns recorded.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

