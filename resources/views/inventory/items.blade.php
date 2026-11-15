@extends('layouts.app')

@section('header', 'Unique Item Inventory, Transfers & Stock Control')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Register Individual Piece -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Register Unique Jewellery Piece</h2>
            <form action="{{ route('inventory.store') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <select name="product_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->purity }} {{ $p->metal_type }})</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select name="branch_id" required class="border border-slate-300 rounded-lg px-2.5 py-2">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                    <select name="counter_id" class="border border-slate-300 rounded-lg px-2.5 py-2">
                        <option value="">Counter (optional)</option>
                        @foreach($counters as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <input type="number" step="0.001" name="gross_weight" required placeholder="Gross Wt (g)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="number" step="0.001" name="stone_weight" value="0" placeholder="Stone Wt (g)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="number" step="0.001" name="diamond_weight" value="0" placeholder="Dia (ct)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <select name="purity" class="border border-slate-300 rounded-lg px-2.5 py-2">
                        <option>22K</option><option>24K</option><option>20K</option><option>18K</option><option>14K</option><option>999</option><option>950</option>
                    </select>
                    <input type="text" name="huid" placeholder="HUID (6-char)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <input type="number" step="1" name="purchase_cost" value="0" placeholder="Cost ₹" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="number" step="1" name="stone_charge" value="0" placeholder="Stone ₹" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="number" step="1" name="diamond_charge" value="0" placeholder="Diamond ₹" class="border border-slate-300 rounded-lg px-2 py-2">
                </div>
                <button class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 rounded-lg">Register Piece & Generate Barcode</button>
            </form>
        </div>

        <!-- Branch / Counter Transfer -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Inter-Branch / Counter Transfer</h2>
            <form action="{{ route('inventory.transfer') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <select name="inventory_item_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    @foreach($items->where('status', 'available') as $it)
                        <option value="{{ $it->id }}">{{ $it->item_code }} — {{ $it->product->name ?? '' }} ({{ $it->branch->code ?? '' }})</option>
                    @endforeach
                </select>
                <select name="to_branch_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">To Branch: {{ $b->name }}</option>
                    @endforeach
                </select>
                <select name="to_counter_id" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    <option value="">To Counter (optional)</option>
                    @foreach($counters as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="notes" placeholder="Transfer notes..." class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <button class="w-full bg-slate-900 text-white font-semibold py-2 rounded-lg">Transfer Item</button>
            </form>

            <h3 class="text-xs font-semibold text-slate-600 uppercase mt-5 mb-2">Consumable Reorder Alerts</h3>
            <div class="space-y-1.5 text-xs">
                @foreach($consumables as $con)
                    <div class="flex justify-between {{ $con->quantity <= $con->reorder_level ? 'text-rose-600 font-semibold' : 'text-slate-600' }}">
                        <span>{{ $con->name }}</span>
                        <span>{{ $con->quantity }} {{ $con->unit }} (Min: {{ $con->reorder_level }})</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Stock Adjustment & Verification -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Stock Adjustment & Audit</h2>
            <form action="{{ route('inventory.adjust') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <select name="inventory_item_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    @foreach($items as $it)
                        <option value="{{ $it->id }}">{{ $it->item_code }} — {{ $it->net_weight }}g ({{ $it->status }})</option>
                    @endforeach
                </select>
                <select name="type" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    <option value="verification">Physical Stock Verification</option>
                    <option value="weight_correction">Weight Correction</option>
                    <option value="damaged">Mark Damaged</option>
                    <option value="lost">Mark Lost</option>
                    <option value="found">Mark Found</option>
                </select>
                <input type="number" step="0.001" name="new_weight" placeholder="Corrected Net Weight (g, optional)" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <input type="text" name="reason" required placeholder="Audit / adjustment reason..." class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <button class="w-full bg-slate-800 text-white font-semibold py-2 rounded-lg">Record Adjustment</button>
            </form>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-base font-semibold text-slate-800">Individual Jewellery Pieces (Item-Level Vault)</h2>
            <form method="GET" class="flex flex-wrap gap-2 text-xs">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Item code, Barcode, HUID..." class="border border-slate-300 rounded-lg px-3 py-1.5">
                <select name="branch_id" class="border border-slate-300 rounded-lg px-2.5 py-1.5">
                    <option value="">All Branches</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="border border-slate-300 rounded-lg px-2.5 py-1.5">
                    <option value="">All Status</option>
                    @foreach(['available', 'reserved', 'sold', 'damaged', 'lost'] as $st)
                        <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
                <button class="bg-slate-800 text-white px-3 py-1.5 rounded-lg">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-slate-500 uppercase bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Item / Barcode</th>
                        <th class="px-3 py-2 text-left">Product</th>
                        <th class="px-3 py-2 text-left">Branch / Counter</th>
                        <th class="px-3 py-2 text-right">Gross / Net Wt</th>
                        <th class="px-3 py-2 text-left">HUID</th>
                        <th class="px-3 py-2 text-center">Status</th>
                        <th class="px-3 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($items as $it)
                        <tr>
                            <td class="px-3 py-2">
                                <div class="font-bold text-xs text-slate-900">{{ $it->item_code }}</div>
                                <div class="font-mono text-[11px] text-slate-400">{{ $it->barcode }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $it->product->name ?? '' }}</div>
                                <div class="text-xs text-slate-400">{{ $it->product->metal_type ?? '' }} · {{ $it->purity }}</div>
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $it->branch->code ?? '' }} · {{ $it->counter->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-right text-xs">
                                <div>G: {{ number_format($it->gross_weight, 3) }}g</div>
                                <div class="font-semibold text-amber-700">N: {{ number_format($it->net_weight, 3) }}g</div>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $it->huid ?? '-' }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $it->status === 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">{{ ucfirst($it->status) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <form action="{{ route('inventory.destroy', $it) }}" method="POST" class="inline" onsubmit="return confirm('Delete piece {{ $it->item_code }} ({{ $it->net_weight }}g)?');">
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
        <div class="mt-4">{{ $items->links() }}</div>
    </div>
</div>
@endsection

