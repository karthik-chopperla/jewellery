@extends('layouts.app')

@section('header', 'Gold, Silver & Metal Rate Management')

@section('content')
<div class="space-y-6">
    <!-- Top Action Bar -->
    <div class="flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-amber-500/10 via-yellow-500/5 to-transparent border border-amber-200/80 rounded-xl p-4">
        <div>
            <h2 class="text-sm font-bold text-slate-900">Today's Live Showroom Board Rates</h2>
            <p class="text-xs text-slate-500 mt-0.5">Rates used across POS billing, jewellery valuation, and old gold exchange.</p>
        </div>
        <form action="{{ route('rates.sync') }}" method="POST" class="flex flex-wrap items-center gap-2">
            @csrf
            <div class="flex items-center bg-white border border-amber-300 rounded-lg px-2.5 py-1.5 text-xs shadow-xs">
                <span class="text-slate-500 mr-1.5">📍 Market Hub:</span>
                <select name="city" class="bg-transparent font-semibold text-slate-800 focus:outline-none">
                    @foreach($cities as $slug => $cName)
                        <option value="{{ $slug }}" {{ ($selectedCity ?? 'guwahati') === $slug ? 'selected' : '' }}>{{ $cName }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-gradient-to-r from-amber-600 to-yellow-600 hover:from-amber-700 hover:to-yellow-700 text-white font-semibold text-xs px-3.5 py-2 rounded-lg shadow-xs flex items-center gap-1.5 transition">
                <span>⚡</span> Sync Live Market Rates
            </button>
        </form>
    </div>

    <!-- Live Rate Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
        @foreach($currentRates as $label => $val)
            <div class="bg-white rounded-xl border border-amber-200/80 p-3.5 shadow-xs">
                <div class="text-xs font-medium text-slate-500">{{ $label }}</div>
                <div class="text-lg font-bold text-amber-700 mt-1">₹{{ number_format($val, 2) }}</div>
                <div class="text-[11px] text-slate-400">per gram</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Update Rate Form -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-4">Publish / Update Metal Rate</h2>
            <form action="{{ route('rates.store') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Branch (leave blank for all branches)</label>
                    <select name="branch_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">All Branches (Global Rate)</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Metal Type</label>
                        <select name="metal_type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="Gold">Gold</option>
                            <option value="Silver">Silver</option>
                            <option value="Platinum">Platinum</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Purity</label>
                        <select name="purity" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="24K">24K</option>
                            <option value="22K">22K</option>
                            <option value="20K">20K</option>
                            <option value="18K">18K</option>
                            <option value="14K">14K</option>
                            <option value="9K">9K</option>
                            <option value="999">999 (Fine Silver)</option>
                            <option value="925">925 (Sterling)</option>
                            <option value="950">950 (Platinum)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Rate per Gram (₹)</label>
                    <input type="number" step="0.01" name="rate_per_gram" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="7850.00">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Effective Date & Time</label>
                    <input type="datetime-local" name="effective_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-700 pt-1">
                    <input type="checkbox" name="auto_calculate_purities" value="1" checked class="rounded text-amber-600">
                    Auto-calculate 22K, 20K, 18K, 14K, 9K when entering 24K Gold
                </label>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
                    <input type="text" name="notes" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Morning IBJA opening rate">
                </div>
                <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 rounded-lg text-sm">Publish Rate</button>
            </form>

            <h3 class="text-sm font-semibold text-slate-800 mt-6 mb-2">Diamond Pricing Config</h3>
            <div class="space-y-2 text-xs">
                @foreach($diamondConfigs as $dc)
                    <div class="flex justify-between border-b border-slate-100 pb-1.5">
                        <span>{{ $dc->shape }} · {{ $dc->cut }} · {{ $dc->color }}/{{ $dc->clarity }}</span>
                        <span class="font-semibold">₹{{ number_format($dc->rate_per_carat, 0) }}/ct</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Rate History -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-4">Rate Update History</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Effective At</th>
                            <th class="px-3 py-2 text-left">Branch</th>
                            <th class="px-3 py-2 text-left">Metal & Purity</th>
                            <th class="px-3 py-2 text-right">Rate/g</th>
                            <th class="px-3 py-2 text-center">Status</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($rates as $r)
                            <tr>
                                <td class="px-3 py-2 text-xs text-slate-600">{{ $r->effective_at->format('d M Y, h:i A') }}</td>
                                <td class="px-3 py-2 text-xs">{{ $r->branch->name ?? 'Global' }}</td>
                                <td class="px-3 py-2 font-medium">{{ $r->metal_type }} {{ $r->purity }}</td>
                                <td class="px-3 py-2 text-right font-semibold">₹{{ number_format($r->rate_per_gram, 2) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-0.5 text-xs rounded-full {{ $r->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ ucfirst($r->status) }}</span>
                                </td>
                                <td class="px-3 py-2 text-right space-x-1 whitespace-nowrap">
                                    @if($r->status === 'pending')
                                        <form action="{{ route('rates.approve', $r) }}" method="POST" class="inline">
                                            @csrf
                                            <button class="text-xs bg-emerald-600 text-white px-2 py-0.5 rounded hover:bg-emerald-700">Approve</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('rates.destroy', $r) }}" method="POST" class="inline" onsubmit="return confirm('Delete this rate entry ({{ $r->metal_type }} {{ $r->purity }} @ ₹{{ $r->rate_per_gram }})?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-600 hover:text-white px-2 py-0.5 rounded transition">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $rates->links() }}</div>
        </div>
    </div>
</div>
@endsection

