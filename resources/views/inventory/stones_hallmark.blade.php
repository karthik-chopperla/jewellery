@extends('layouts.app')

@section('header', 'Diamonds, Precious Stones & BIS Hallmarking (HUID)')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Diamonds & Precious Stones -->
    <div class="space-y-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Add Certified Diamond / Gemstone</h2>
            <form action="{{ route('stones.store') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <div class="grid grid-cols-3 gap-2">
                    <select name="stone_type" class="border border-slate-300 rounded-lg px-2.5 py-2">
                        <option>Diamond</option><option>Ruby</option><option>Emerald</option><option>Sapphire</option><option>Other</option>
                    </select>
                    <input type="text" name="shape" placeholder="Shape (Round/Oval)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="text" name="cut" placeholder="Cut (Ideal/Exc)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <input type="text" name="color" placeholder="Color (E/F/G)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="text" name="clarity" placeholder="Clarity (VVS1/VS1)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="number" step="0.001" name="carat_weight" required placeholder="Carat Wt" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="certification_lab" placeholder="Lab (IGI/GIA/SGL)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="text" name="certificate_no" placeholder="Certificate #" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" step="1" name="cost_price" required placeholder="Cost ₹" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="number" step="1" name="selling_price" required placeholder="Selling Price ₹" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <button class="w-full bg-slate-900 text-white py-2 rounded-lg font-semibold">Add Stone to Vault</button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Diamond & Gemstone Inventory</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-2 py-2 text-left">Code</th>
                            <th class="px-2 py-2 text-left">Specs (4Cs)</th>
                            <th class="px-2 py-2 text-left">Cert #</th>
                            <th class="px-2 py-2 text-right">Price</th>
                            <th class="px-2 py-2 text-center">Status</th>
                            <th class="px-2 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($stones as $s)
                            <tr>
                                <td class="px-2 py-2 font-bold">{{ $s->stone_code }}</td>
                                <td class="px-2 py-2">{{ $s->stone_type }} · {{ $s->carat_weight }}ct · {{ $s->color }}/{{ $s->clarity }}</td>
                                <td class="px-2 py-2">{{ $s->certification_lab }} {{ $s->certificate_no }}</td>
                                <td class="px-2 py-2 text-right font-semibold">₹{{ number_format($s->selling_price, 0) }}</td>
                                <td class="px-2 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-slate-100">{{ $s->status }}</span></td>
                                <td class="px-2 py-2 text-right whitespace-nowrap">
                                    <form action="{{ route('stones.destroy', $s) }}" method="POST" class="inline" onsubmit="return confirm('Delete diamond/gemstone {{ $s->stone_code }}?');">
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

    <!-- Hallmarking & HUID -->
    <div class="space-y-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">BIS Hallmarking & HUID Dispatch / Registration</h2>
            <form action="{{ route('hallmark.store') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <select name="inventory_item_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    @foreach($items as $it)
                        <option value="{{ $it->id }}">{{ $it->item_code }} — {{ $it->product->name ?? '' }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="center_name" required value="BIS Mumbai Assaying Center" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="date" name="dispatch_date" required value="{{ date('Y-m-d') }}" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <input type="text" name="huid" placeholder="HUID (6-digit)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="text" name="purity_verified" value="22K" placeholder="Purity" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="number" step="1" name="charges" value="45" placeholder="Charges ₹" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <select name="status" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    <option value="verified">Verified & HUID Linked</option>
                    <option value="received">Received from Center</option>
                    <option value="dispatched">Dispatched to Center</option>
                </select>
                <button class="w-full bg-amber-600 hover:bg-amber-700 text-white py-2 rounded-lg font-semibold">Save Hallmark Record</button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Hallmarking & HUID History</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-2 py-2 text-left">Item</th>
                            <th class="px-2 py-2 text-left">Center</th>
                            <th class="px-2 py-2 text-left">HUID</th>
                            <th class="px-2 py-2 text-center">Status</th>
                            <th class="px-2 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($hallmarks as $h)
                            <tr>
                                <td class="px-2 py-2 font-medium">{{ $h->inventoryItem->item_code ?? '' }}</td>
                                <td class="px-2 py-2">{{ $h->center_name }}</td>
                                <td class="px-2 py-2 font-mono font-bold text-amber-700">{{ $h->huid ?? '-' }}</td>
                                <td class="px-2 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">{{ ucfirst($h->status) }}</span></td>
                                <td class="px-2 py-2 text-right whitespace-nowrap">
                                    <form action="{{ route('hallmark.destroy', $h) }}" method="POST" class="inline" onsubmit="return confirm('Delete hallmark record #{{ $h->id }}?');">
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

