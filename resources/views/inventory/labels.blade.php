@extends('layouts.app')

@section('header', 'Barcode, QR Code & Jewellery Tag Printing')

@section('content')
<div class="space-y-4">
    <div class="no-print flex items-center justify-between">
        <p class="text-sm text-slate-600">Bulk jewellery hang-tags with Item Code, Barcode, Purity, Gross/Net Weight, and HUID.</p>
        <button onclick="window.print()" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-xs font-semibold">🖨️ Print All Tags</button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach($items as $item)
            <div class="bg-white border-2 border-dashed border-slate-300 rounded-xl p-3.5 text-xs space-y-1.5">
                <div class="flex justify-between items-center border-b border-slate-100 pb-1">
                    <span class="font-bold text-slate-900">{{ strtoupper($businessName ?? 'JEWELERP') }}</span>
                    <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px]">{{ $item->purity }} {{ $item->product->metal_type ?? '' }}</span>
                </div>
                <div class="font-semibold text-slate-800 truncate">{{ $item->product->name ?? $item->item_code }}</div>
                <div class="grid grid-cols-2 gap-1 text-[11px] text-slate-600">
                    <div>GW: <span class="font-semibold text-slate-900">{{ number_format($item->gross_weight, 3) }}g</span></div>
                    <div>NW: <span class="font-semibold text-slate-900">{{ number_format($item->net_weight, 3) }}g</span></div>
                    <div>SKU: {{ $item->product->sku ?? '' }}</div>
                    <div>HUID: {{ $item->huid ?? 'N/A' }}</div>
                </div>
                <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] tracking-widest bg-slate-900 text-white px-2 py-0.5 rounded">||| || ||| ||||</div>
                        <div class="font-mono text-[10px] text-slate-500 mt-0.5">{{ $item->barcode }}</div>
                    </div>
                    <div class="w-10 h-10 border border-slate-300 rounded flex items-center justify-center text-[9px] font-mono text-slate-500 bg-slate-50">
                        QR<br>{{ $item->item_code }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

