@extends('layouts.app')

@section('header', 'Accounts, Finance, Vouchers & GST Reports')

@section('content')
<div class="space-y-6">
    <!-- Financial Summary -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500">Total Sales Revenue</div>
            <div class="text-xl font-bold text-emerald-700 mt-1">₹{{ number_format($totalSales, 0) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500">Making Charge Income</div>
            <div class="text-xl font-bold text-amber-700 mt-1">₹{{ number_format($totalMakingRevenue, 0) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500">Total Purchases & Expenses</div>
            <div class="text-xl font-bold text-slate-900 mt-1">₹{{ number_format($totalPurchases + $totalExpenses, 0) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500">Output GST (CGST+SGST+IGST)</div>
            <div class="text-xl font-bold text-slate-900 mt-1">₹{{ number_format($totalCgst + $totalSgst + $totalIgst, 0) }}</div>
            <div class="text-[11px] text-slate-400">Input GST: ₹{{ number_format($inputGst, 0) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-500">Receivables / Payables</div>
            <div class="text-sm font-bold text-slate-800 mt-1">Rec: ₹{{ number_format($netReceivables, 0) }}</div>
            <div class="text-xs text-rose-600 font-semibold">Pay: ₹{{ number_format($netPayables, 0) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Post Voucher & Expense -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Post Payment / Receipt / Journal Voucher</h2>
                <form action="{{ route('finance.vouchers.store') }}" method="POST" class="space-y-2.5 text-xs">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <select name="branch_id" class="border border-slate-300 rounded-lg px-2 py-2">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                        <select name="type" class="border border-slate-300 rounded-lg px-2 py-2"><option value="receipt">Receipt</option><option value="payment">Payment</option><option value="journal">Journal</option><option value="contra">Contra</option></select>
                    </div>
                    <select name="account_id" class="w-full border border-slate-300 rounded-lg px-3 py-2">@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>@endforeach</select>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="0.01" name="amount" required placeholder="Amount ₹" class="border border-slate-300 rounded-lg px-2.5 py-2">
                        <input type="date" name="voucher_date" value="{{ date('Y-m-d') }}" required class="border border-slate-300 rounded-lg px-2.5 py-2">
                    </div>
                    <input type="text" name="narration" required placeholder="Narration..." class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    <button class="w-full bg-slate-900 text-white font-semibold py-2 rounded-lg">Post Voucher</button>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Record Showroom Expense</h2>
                <form action="{{ route('finance.expenses.store') }}" method="POST" class="space-y-2.5 text-xs">
                    @csrf
                    <select name="branch_id" class="w-full border border-slate-300 rounded-lg px-3 py-2">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    <input type="text" name="category" required placeholder="Expense Category (Rent, Security, Tea)" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    <div class="grid grid-cols-3 gap-2">
                        <input type="number" step="0.01" name="amount" required placeholder="Amount ₹" class="border border-slate-300 rounded-lg px-2 py-2">
                        <input type="date" name="expense_date" value="{{ date('Y-m-d') }}" required class="border border-slate-300 rounded-lg px-2 py-2">
                        <select name="paid_via" class="border border-slate-300 rounded-lg px-2 py-2"><option value="cash">Cash</option><option value="bank">Bank</option><option value="upi">UPI</option></select>
                    </div>
                    <input type="text" name="description" required placeholder="Description..." class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    <button class="w-full bg-amber-600 text-white font-semibold py-2 rounded-lg">Save Expense</button>
                </form>
            </div>
        </div>

        <!-- Chart of Accounts & Vouchers -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-base font-semibold text-slate-800 mb-3">Cashbook, Bankbook & Trial Balance Accounts</h2>
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase"><tr><th class="px-3 py-2 text-left">Code</th><th class="px-3 py-2 text-left">Account Name</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-right">Balance</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($accounts as $acc)
                            <tr>
                                <td class="px-3 py-2 font-mono">{{ $acc->code }}</td>
                                <td class="px-3 py-2 font-semibold">{{ $acc->name }}</td>
                                <td class="px-3 py-2 uppercase text-slate-500">{{ $acc->type }}</td>
                                <td class="px-3 py-2 text-right font-bold">₹{{ number_format($acc->balance, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-base font-semibold text-slate-800 mb-3">Recent Financial Vouchers</h2>
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase"><tr><th class="px-2 py-2 text-left">Voucher #</th><th class="px-2 py-2 text-left">Type</th><th class="px-2 py-2 text-left">Narration</th><th class="px-2 py-2 text-right">Amount</th><th class="px-2 py-2 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($vouchers as $v)
                            <tr>
                                <td class="px-2 py-2 font-bold">{{ $v->voucher_no }}</td>
                                <td class="px-2 py-2 uppercase">{{ $v->type }}</td>
                                <td class="px-2 py-2">{{ $v->narration }}</td>
                                <td class="px-2 py-2 text-right font-semibold">₹{{ number_format($v->amount, 2) }}</td>
                                <td class="px-2 py-2 text-right whitespace-nowrap">
                                    <form action="{{ route('finance.vouchers.destroy', $v) }}" method="POST" class="inline" onsubmit="return confirm('Delete voucher {{ $v->voucher_no }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-rose-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <h3 class="text-sm font-semibold text-slate-800 mt-6 mb-2">Recent Recorded Expenses</h3>
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase"><tr><th class="px-2 py-2 text-left">Date</th><th class="px-2 py-2 text-left">Category</th><th class="px-2 py-2 text-left">Description</th><th class="px-2 py-2 text-right">Amount</th><th class="px-2 py-2 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($expenses as $e)
                            <tr>
                                <td class="px-2 py-2 text-slate-500">{{ $e->expense_date->format('d M Y') }}</td>
                                <td class="px-2 py-2 font-medium">{{ $e->category }} ({{ $e->paid_via }})</td>
                                <td class="px-2 py-2">{{ $e->description }}</td>
                                <td class="px-2 py-2 text-right font-semibold text-rose-600">₹{{ number_format($e->amount, 2) }}</td>
                                <td class="px-2 py-2 text-right whitespace-nowrap">
                                    <form action="{{ route('finance.expenses.destroy', $e) }}" method="POST" class="inline" onsubmit="return confirm('Delete expense {{ $e->category }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-rose-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

