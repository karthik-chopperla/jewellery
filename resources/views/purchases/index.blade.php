@extends('layouts.app')

@section('header', 'Purchases & Supplier Management')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Record Purchase Invoice</h2>
            <form action="{{ route('purchases.store') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <select name="supplier_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select name="branch_id" class="border border-slate-300 rounded-lg px-2 py-2">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="bill_date" value="{{ date('Y-m-d') }}" required class="border border-slate-300 rounded-lg px-2 py-2">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <select name="metal_type" class="border border-slate-300 rounded-lg px-2 py-2"><option>Gold</option><option>Silver</option><option>Platinum</option></select>
                    <input type="text" name="purity" value="22K" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="number" step="0.001" name="metal_weight" required placeholder="Wt (g)" class="border border-slate-300 rounded-lg px-2 py-2">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" step="0.01" name="total_amount" required placeholder="Total Bill ₹" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="number" step="0.01" name="paid_amount" value="0" placeholder="Paid Now ₹" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <button class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 rounded-lg">Save Purchase Bill</button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Add New Supplier</h2>
            <form action="{{ route('suppliers.store') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <input type="text" name="name" required placeholder="Supplier Name" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="phone" placeholder="Phone" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="text" name="gstin" placeholder="GSTIN" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <select name="category" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    <option value="gold_supplier">Gold Supplier</option>
                    <option value="silver_supplier">Silver Supplier</option>
                    <option value="diamond_supplier">Diamond Supplier</option>
                    <option value="manufacturer">Jewellery Manufacturer</option>
                </select>
                <button class="w-full bg-slate-900 text-white font-semibold py-2 rounded-lg">Register Supplier</button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Purchase Bills & Supplier Payments</h2>
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr><th class="px-2 py-2 text-left">Bill #</th><th class="px-2 py-2 text-left">Supplier</th><th class="px-2 py-2 text-right">Weight</th><th class="px-2 py-2 text-right">Total</th><th class="px-2 py-2 text-right">Due</th><th class="px-2 py-2 text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($purchaseInvoices as $pi)
                        @php $due = max(0, $pi->total_amount - $pi->paid_amount); @endphp
                        <tr>
                            <td class="px-2 py-2 font-bold">{{ $pi->bill_no }}</td>
                            <td class="px-2 py-2">{{ $pi->supplier->name ?? '' }}</td>
                            <td class="px-2 py-2 text-right">{{ number_format($pi->metal_weight, 2) }}g</td>
                            <td class="px-2 py-2 text-right font-semibold">₹{{ number_format($pi->total_amount, 0) }}</td>
                            <td class="px-2 py-2 text-right text-rose-600 font-semibold">₹{{ number_format($due, 0) }}</td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                                @if($due > 0)
                                    <form action="{{ route('suppliers.pay') }}" method="POST" class="inline-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="purchase_invoice_id" value="{{ $pi->id }}">
                                        <input type="hidden" name="method" value="bank_transfer">
                                        <input type="hidden" name="payment_date" value="{{ date('Y-m-d') }}">
                                        <input type="number" name="amount" value="{{ $due }}" class="border rounded px-1.5 py-0.5 w-16 text-[11px]">
                                        <button class="bg-emerald-600 text-white px-2 py-0.5 rounded text-[11px]">Pay</button>
                                    </form>
                                @else
                                    <span class="text-emerald-700 font-semibold text-[11px]">Paid</span>
                                @endif
                                <form action="{{ route('purchases.destroy', $pi) }}" method="POST" class="inline" onsubmit="return confirm('Delete purchase bill {{ $pi->bill_no }}?');">
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

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Supplier Ledger Summary</h3>
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr><th class="px-2 py-2 text-left">Supplier</th><th class="px-2 py-2 text-left">Category / GSTIN</th><th class="px-2 py-2 text-right">Total Billed</th><th class="px-2 py-2 text-right">Outstanding</th><th class="px-2 py-2 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($suppliers as $s)
                        <tr>
                            <td class="px-2 py-2 font-semibold">{{ $s->name }}</td>
                            <td class="px-2 py-2">{{ $s->category }} · {{ $s->gstin }}</td>
                            <td class="px-2 py-2 text-right">₹{{ number_format($s->purchase_invoices_sum_total_amount ?? 0, 0) }}</td>
                            <td class="px-2 py-2 text-right text-rose-600 font-semibold">₹{{ number_format(($s->purchase_invoices_sum_total_amount ?? 0) - ($s->purchase_invoices_sum_paid_amount ?? 0), 0) }}</td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                                <form action="{{ route('suppliers.destroy', $s) }}" method="POST" class="inline" onsubmit="return confirm('Delete supplier {{ $s->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-[11px] text-rose-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

