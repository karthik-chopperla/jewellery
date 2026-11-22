@extends('layouts.app')

@section('header', 'Barcode, QR Code & Jewellery Tags')

@section('content')
<div
    x-data="inventoryLabels()"
    data-generate-barcode="{{ route('inventory.labels.barcodes.generate') }}"
    data-generate-qr="{{ route('inventory.labels.qr.generate') }}"
    data-print="{{ route('inventory.labels.print') }}"
    data-preview="{{ route('inventory.labels.preview') }}"
    data-filtered-count="{{ min($items->total(), 5000) }}"
    class="space-y-5"
>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Inventory items</p><p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($totalItems) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">With barcodes</p><p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($withBarcode) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Missing barcodes</p><p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($totalItems - $withBarcode) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">With secure QR IDs</p><p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($withQr) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Pending first print</p><p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($pendingLabels) }}</p></div>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <form id="label-filters" method="GET" action="{{ route('inventory.labels') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Item code, product or SKU" class="rounded-lg border border-slate-300 px-3 py-2 text-xs">
            <select name="branch_id" class="rounded-lg border border-slate-300 px-2 py-2 text-xs">
                <option value="">All branches</option>
                @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>@endforeach
            </select>
            <select name="category_id" class="rounded-lg border border-slate-300 px-2 py-2 text-xs">
                <option value="">All categories</option>
                @foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach
            </select>
            <select name="metal_type" class="rounded-lg border border-slate-300 px-2 py-2 text-xs">
                <option value="">All metals</option>
                @foreach(['Gold', 'Silver', 'Platinum', 'Diamond'] as $metal)<option value="{{ $metal }}" @selected(($filters['metal_type'] ?? '') === $metal)>{{ $metal }}</option>@endforeach
            </select>
            <select name="purity" class="rounded-lg border border-slate-300 px-2 py-2 text-xs">
                <option value="">All purities</option>
                @foreach($purities as $purity)<option value="{{ $purity }}" @selected(($filters['purity'] ?? '') === $purity)>{{ $purity }}</option>@endforeach
            </select>
            <select name="status" class="rounded-lg border border-slate-300 px-2 py-2 text-xs">
                <option value="">All statuses</option>
                @foreach(['available', 'reserved', 'sold', 'damaged', 'lost', 'in_transfer', 'repair'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach
            </select>
            <select name="missing" class="rounded-lg border border-slate-300 px-2 py-2 text-xs">
                <option value="">All identifiers</option>
                <option value="barcode" @selected(($filters['missing'] ?? '') === 'barcode')>Missing barcode</option>
                <option value="qr" @selected(($filters['missing'] ?? '') === 'qr')>Missing QR</option>
                <option value="either" @selected(($filters['missing'] ?? '') === 'either')>Missing barcode or QR</option>
            </select>
            <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Search inventory</button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-semibold text-slate-900">Bulk generation & printing</h2>
                <p class="mt-1 text-xs text-slate-500">Each barcode belongs to one inventory piece. QR tags link to a random, read-only item lookup.</p>
            </div>
            <div class="text-xs font-semibold text-slate-700" aria-live="polite">
                <span x-text="selected.length"></span> selected · <span x-text="selected.length * copies"></span> labels
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="text-xs text-slate-600">Tag template
                <select id="label-template" x-model="template" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2">
                    <option value="gold">Gold jewellery</option><option value="silver">Silver jewellery</option>
                    <option value="diamond">Diamond jewellery</option><option value="minimal">Barcode only</option><option value="qr">QR-enabled jewellery</option>
                </select>
            </label>
            <label class="text-xs text-slate-600">Paper / printer
                <select id="label-paper" x-model="paperType" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2">
                    <option value="a4">A4 label sheet</option><option value="roll">Thermal / single-label roll</option>
                </select>
            </label>
            <div class="grid grid-cols-3 gap-2">
                <label class="text-xs text-slate-600">Common size
                    <select @change="setPreset($event.target.value)" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2">
                        <option value="custom">Custom</option><option value="50x25">50 × 25</option><option value="50x30">50 × 30</option><option value="60x30">60 × 30</option><option value="70x25">70 × 25</option>
                    </select>
                </label>
                <label class="text-xs text-slate-600">Width mm<input id="label-width" type="number" min="15" max="100" step="0.5" x-model.number="width" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2"></label>
                <label class="text-xs text-slate-600">Height mm<input id="label-height" type="number" min="15" max="100" step="0.5" x-model.number="height" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2"></label>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-slate-600">Orientation
                    <select id="label-orientation" x-model="orientation" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select>
                </label>
                <label class="text-xs text-slate-600">Copies per item<input id="label-copies" type="number" min="1" max="50" x-model.number="copies" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2"></label>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-slate-600">A4 margin mm<input id="label-margin" type="number" min="0" max="20" step="0.5" x-model.number="margin" :disabled="paperType === 'roll'" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2 disabled:bg-slate-100"></label>
                <label class="text-xs text-slate-600">A4 spacing mm<input id="label-gap" type="number" min="0" max="10" step="0.5" x-model.number="gap" :disabled="paperType === 'roll'" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2 disabled:bg-slate-100"></label>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-600">
            <label><input id="label-include-qr" type="checkbox" x-model="includeQr" class="mr-1 rounded border-slate-300">Include QR code</label>
            <label><input type="checkbox" value="category" x-model="fields" class="mr-1 rounded border-slate-300">Category</label>
            <label><input type="checkbox" value="weights" x-model="fields" class="mr-1 rounded border-slate-300">Gross / net / stone weights</label>
            <label><input type="checkbox" value="huid" x-model="fields" class="mr-1 rounded border-slate-300">HUID</label>
            <label><input type="checkbox" value="metal" x-model="fields" class="mr-1 rounded border-slate-300">Metal</label>
            <label><input type="checkbox" value="purity" x-model="fields" class="mr-1 rounded border-slate-300">Purity</label>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" @click="submit('barcode', false)" :disabled="selected.length === 0" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-900 disabled:cursor-not-allowed disabled:opacity-40">Generate selected missing barcodes</button>
            <button type="button" @click="submit('qr', false)" :disabled="selected.length === 0" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 disabled:cursor-not-allowed disabled:opacity-40">Generate selected missing QR IDs</button>
            <button type="button" @click="submit('barcode', true)" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-50">Generate missing barcodes in filters</button>
            <button type="button" @click="submit('qr', true)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">Generate missing QR IDs in filters</button>
            <button type="button" @click="preview()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Preview selected tag</button>
            <button type="button" @click="submit('print', false)" :disabled="selected.length === 0" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Print selected</button>
            <button type="button" @click="submit('print', true)" class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-700">Print all filtered (<span x-text="filteredCount * copies"></span> labels)</button>
            <button type="button" @click="selectUntagged()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Select untagged on this page</button>
        </div>
        <p x-show="notice" x-text="notice" role="status" class="mt-2 text-xs text-amber-800"></p>
        @if($items->total() > 5000)
            <p class="mt-2 text-xs text-amber-800">A single print job is limited to 5,000 matching inventory pieces; refine the filters to print a smaller batch.</p>
        @endif
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div><h2 class="font-semibold text-slate-900">Inventory tags</h2><p class="text-xs text-slate-500">Showing {{ $items->count() }} of {{ number_format($items->total()) }} matching pieces.</p></div>
            <label class="text-xs text-slate-600"><input type="checkbox" @change="toggleVisible($event.target.checked)" class="mr-1 rounded border-slate-300">Select page</label>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[950px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500"><tr>
                    <th class="px-3 py-2">Select</th><th class="px-3 py-2">Item / barcode</th><th class="px-3 py-2">Jewellery</th><th class="px-3 py-2">Branch</th><th class="px-3 py-2">Weights / HUID</th><th class="px-3 py-2">Status / QR</th><th class="px-3 py-2">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $item)
                        <tr data-inventory-id="{{ $item->id }}" data-untagged="{{ !$item->barcode || !$item->qr_token ? '1' : '0' }}">
                            <td class="px-3 py-3"><input type="checkbox" value="{{ $item->id }}" x-model="selected" aria-label="Select {{ $item->item_code }}" class="rounded border-slate-300"></td>
                            <td class="px-3 py-3"><div class="font-semibold text-slate-900">{{ $item->item_code }}</div><div class="mt-1 font-mono text-slate-500">{{ $item->barcode ?: 'Barcode missing' }}</div><div class="text-[10px] text-slate-400">{{ strtoupper($item->barcode_format ?? 'code128') }}</div></td>
                            <td class="px-3 py-3"><div class="font-medium">{{ $item->product->name ?? '-' }}</div><div class="text-slate-500">{{ $item->product->metal_type ?? '-' }} · {{ $item->purity }}</div><div class="text-[10px] text-slate-400">{{ $item->product->category->name ?? '' }}</div></td>
                            <td class="px-3 py-3">{{ $item->branch->name ?? '-' }}</td>
                            <td class="px-3 py-3 text-slate-600">G {{ number_format((float) $item->gross_weight, 3) }}g · N {{ number_format((float) $item->net_weight, 3) }}g<br>HUID {{ $item->huid ?: '—' }}</td>
                            <td class="px-3 py-3"><span class="rounded-full bg-slate-100 px-2 py-1">{{ ucfirst(str_replace('_', ' ', $item->status)) }}</span><div class="mt-1 text-[10px] {{ $item->qr_token ? 'text-emerald-700' : 'text-amber-700' }}">{{ $item->qr_token ? 'QR ready' : 'QR missing' }}</div></td>
                            <td class="px-3 py-3"><button type="button" @click="preview({{ $item->id }})" class="rounded border border-slate-300 px-2 py-1 font-semibold text-slate-700 hover:bg-slate-50">Preview</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No inventory items match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-4 py-3">{{ $items->links() }}</div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="font-semibold text-slate-900">Recently generated identifiers</h2>
            <div class="mt-3 divide-y divide-slate-100 text-xs">
                @forelse($recentGenerated as $item)
                    <div class="flex justify-between gap-3 py-2"><span>{{ $item->item_code }} · {{ $item->product->name ?? '' }}</span><span class="text-slate-500">{{ optional($item->barcode_generated_at)->format('d M Y, H:i') }}</span></div>
                @empty<p class="py-2 text-slate-500">No generated identifiers recorded.</p>@endforelse
            </div>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="font-semibold text-slate-900">Recent print jobs</h2>
            <div class="mt-3 divide-y divide-slate-100 text-xs">
                @forelse($recentPrinted as $log)
                    <div class="flex justify-between gap-3 py-2"><span>{{ $log->inventoryItem->item_code ?? 'Deleted item' }} · {{ ucfirst($log->template ?? $log->label_type) }} · {{ $log->copies }} copy/copies</span><span class="text-slate-500">{{ $log->created_at->format('d M Y, H:i') }}</span></div>
                @empty<p class="py-2 text-slate-500">No print jobs have been requested.</p>@endforelse
            </div>
        </section>
    </div>
</div>
@endsection
