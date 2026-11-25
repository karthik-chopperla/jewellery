@extends('layouts.app')

@section('header', 'Product & Jewellery Master')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 h-fit">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Add Jewellery Product</h2>
        <p class="mb-4 text-xs leading-5 text-slate-500">First save the reusable product model. Then register each physical piece in Inventory &amp; Stock; every piece gets its own barcode, QR code and weights. POS uses the current approved metal rate.</p>
        <form action="{{ route('products.store') }}" method="POST" class="space-y-3 text-sm">
            @csrf
            <div class="grid grid-cols-2 gap-2">
                <input type="text" name="sku" required placeholder="SKU e.g. SKU-RNG-009" class="border border-slate-300 rounded-lg px-3 py-2 text-xs">
                <select name="category_id" required class="border border-slate-300 rounded-lg px-2.5 py-2 text-xs">
                    <option value="" disabled @selected($categories->isEmpty())>Choose category</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($categories->isEmpty())
                <a href="{{ route('categories.index') }}" class="inline-flex text-xs font-semibold text-amber-800 hover:underline">No categories yet — create one first</a>
            @else
                <a href="{{ route('categories.index') }}" class="inline-flex text-xs text-amber-800 hover:underline">Manage categories</a>
            @endif
            <input type="text" name="name" required placeholder="Product Name" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs">
            <div class="grid grid-cols-3 gap-2">
                <select name="product_type" class="border border-slate-300 rounded-lg px-2 py-2 text-xs">
                    <option>Ring</option><option>Necklace</option><option>Chain</option><option>Bracelet</option><option>Earrings</option><option>Bangles</option><option>Pendant</option><option>Coins</option>
                </select>
                <select name="metal_type" class="border border-slate-300 rounded-lg px-2 py-2 text-xs">
                    <option>Gold</option><option>Silver</option><option>Diamond</option><option>Platinum</option>
                </select>
                <select name="purity" class="border border-slate-300 rounded-lg px-2 py-2 text-xs">
                    <option>22K</option><option>24K</option><option>20K</option><option>18K</option><option>14K</option><option>999</option><option>950</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="text" name="hsn_code" maxlength="20" placeholder="HSN code" class="border border-slate-300 rounded-lg px-3 py-2 text-xs">
                <input type="number" step="0.01" min="0" name="gst_rate" placeholder="GST % (category default)" class="border border-slate-300 rounded-lg px-3 py-2 text-xs">
            </div>
            <div class="grid grid-cols-3 gap-2">
                <select name="making_charge_type" class="border border-slate-300 rounded-lg px-2 py-2 text-xs">
                    <option value="per_gram">₹/Gram</option>
                    <option value="percentage">% Gold</option>
                    <option value="fixed">Fixed ₹</option>
                </select>
                <input type="number" step="0.01" name="making_charge_value" required placeholder="Making Val" class="border border-slate-300 rounded-lg px-2.5 py-2 text-xs">
                <input type="number" step="0.1" name="wastage_pct" value="2.0" placeholder="Wastage %" class="border border-slate-300 rounded-lg px-2.5 py-2 text-xs">
            </div>
            <select name="supplier_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs">
                <option value="">Select Supplier (optional)</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
            <textarea name="description" rows="2" placeholder="Description..." class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs"></textarea>
            <div class="grid grid-cols-2 gap-2">
                <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Save Product</button>
                <button name="next_step" value="inventory" class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-700">Save &amp; Add Stock</button>
            </div>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-slate-800">Product Catalog</h2>
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search SKU or name..." class="border border-slate-300 rounded-lg px-3 py-1.5 text-xs">
                <button class="bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs">Filter</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-slate-500 uppercase bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left">SKU</th>
                        <th class="px-3 py-2 text-left">Product</th>
                        <th class="px-3 py-2 text-left">Metal / Purity</th>
                        <th class="px-3 py-2 text-left">HSN / GST</th>
                        <th class="px-3 py-2 text-left">Making Charge</th>
                        <th class="px-3 py-2 text-center">In Stock</th>
                        <th class="px-3 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($products as $p)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs">{{ $p->sku }}</td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-slate-800">{{ $p->name }}</div>
                                <div class="text-xs text-slate-400">{{ $p->category->name ?? '' }} · {{ $p->product_type }}</div>
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $p->metal_type }} <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold">{{ $p->purity }}</span></td>
                            <td class="px-3 py-2 text-xs">{{ $p->hsn_code ?? '—' }} · {{ number_format($p->gst_rate, 2) }}%</td>
                            <td class="px-3 py-2 text-xs">{{ $p->making_charge_value }} ({{ $p->making_charge_type }}) · W: {{ $p->wastage_pct }}%</td>
                            <td class="px-3 py-2 text-center font-bold {{ $p->available_count > 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $p->available_count }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('inventory.index', ['product_id' => $p->id]) }}" class="mr-2 inline-block rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">Add Piece</a>
                                <form action="{{ route('products.destroy', $p) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete product {{ $p->sku }} ({{ $p->name }})?');">
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
        <div class="mt-4">{{ $products->links() }}</div>
    </div>
</div>
@endsection

