@extends('layouts.app')

@section('header', 'Jewellery Tag Print Preview')

@section('content')
@php
    $effectiveMargin = $paper_type === 'a4' ? $margin_mm : 0;
    $effectiveGap = $paper_type === 'a4' ? $gap_mm : 0;
@endphp
<style>
    @if($paper_type === 'roll')
    @page { size: {{ number_format($width_mm, 2, '.', '') }}mm {{ number_format($height_mm, 2, '.', '') }}mm; margin: 0; }
    @else
    @page { size: A4 {{ $orientation }}; margin: {{ number_format($effectiveMargin, 2, '.', '') }}mm; }
    @endif
    .label-sheet { display: grid; grid-template-columns: repeat(auto-fill, {{ number_format($width_mm, 2, '.', '') }}mm); gap: {{ number_format($effectiveGap, 2, '.', '') }}mm; align-content: start; }
    .jewellery-label { box-sizing: border-box; width: {{ number_format($width_mm, 2, '.', '') }}mm; height: {{ number_format($height_mm, 2, '.', '') }}mm; overflow: hidden; border: 0.25mm solid #475569; border-radius: 1mm; padding: 1.5mm; display: flex; flex-direction: column; justify-content: space-between; background: #fff; break-inside: avoid; }
    .template-gold { border-color: #b7791f; background: #fffdf5; }
    .template-silver { border-color: #64748b; background: #f8fafc; }
    .template-diamond { border-color: #6d28d9; background: #fcfaff; }
    .template-qr { border-color: #0369a1; background: #f5fbff; }
    .template-minimal { border-style: dashed; padding: 1mm; }
    .jewellery-label canvas { max-width: 100%; height: auto; image-rendering: pixelated; }
    .label-code { min-height: 8mm; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .label-qr canvas { width: 12mm; height: 12mm; }
    .label-error { color: #b91c1c; font-size: 7pt; }
    @media screen {
        .jewellery-label { box-shadow: 0 1px 5px #0002; }
        .label-sheet { padding: 4mm; }
    }
    @media print {
        html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
        main { padding: 0 !important; }
        .label-controls, .no-print { display: none !important; }
        .label-sheet { gap: 2mm; padding: 0; }
        .jewellery-label { border-radius: 0; box-shadow: none; }
        @if($paper_type === 'roll')
        .label-sheet { display: block; }
        .jewellery-label { margin: 0; border: 0; }
        .jewellery-label:not(:last-child) { break-after: page; page-break-after: always; }
        @endif
    }
</style>

<div class="label-controls no-print mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4">
    <div>
        <h2 class="font-semibold text-slate-900">Print preview</h2>
        <p class="text-xs text-slate-500">{{ $items->count() }} item(s) · {{ $totalLabels }} label(s) · {{ $width_mm }} × {{ $height_mm }} mm · {{ $paper_type === 'a4' ? 'A4 sheet' : 'thermal roll' }}@if($paper_type === 'a4') · {{ $effectiveMargin }} mm margin / {{ $effectiveGap }} mm spacing @endif</p>
        @if($truncated)<p class="mt-1 text-xs font-medium text-amber-800">This job was limited to the first 5,000 matching items. Refine your filters for the remaining items.</p>@endif
    </div>
    <div class="flex gap-2">
        <button type="button" onclick="window.print()" class="rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white">Print / Save as PDF</button>
        <button type="button" onclick="window.close()" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700">Close</button>
    </div>
</div>

<div class="label-sheet">
    @foreach($items as $item)
        @for($copy = 0; $copy < $copies; $copy++)
            <article class="jewellery-label template-{{ $template }}">
                <header class="flex min-w-0 items-center justify-between gap-1 border-b border-slate-200 pb-1">
                    <div class="flex min-w-0 items-center gap-1">
                        @if($logoPath)<img src="{{ asset('storage/'.$logoPath) }}" alt="" class="max-h-4 max-w-8 object-contain">@endif
                        <span class="truncate text-[8px] font-bold uppercase tracking-wide text-slate-900">{{ $businessName }}</span>
                    </div>
                    @if(in_array('purity', $fields, true) && $template !== 'minimal')
                        <span class="shrink-0 rounded bg-amber-100 px-1 py-0.5 text-[7px] font-bold text-amber-900">{{ $item->purity }}</span>
                    @endif
                </header>

                @if($template !== 'minimal')
                    <div class="min-w-0">
                        <div class="truncate text-[8px] font-semibold text-slate-900">{{ $item->product->name ?? $item->item_code }}</div>
                        <div class="flex flex-wrap gap-x-1 text-[7px] leading-tight text-slate-600">
                            <span class="font-semibold text-slate-800">{{ $item->item_code }}</span>
                            @if(in_array('category', $fields, true))<span>{{ $item->product->category->name ?? '' }}</span>@endif
                            @if(in_array('metal', $fields, true))<span>{{ $item->product->metal_type ?? '' }}</span>@endif
                        </div>
                        @if(in_array('weights', $fields, true))
                            <div class="text-[7px] leading-tight text-slate-700">G {{ number_format((float) $item->gross_weight, 3) }}g · N {{ number_format((float) $item->net_weight, 3) }}g · St {{ number_format((float) $item->stone_weight, 3) }}g</div>
                        @endif
                        @if(in_array('huid', $fields, true) && $item->huid)<div class="text-[7px] leading-tight text-slate-700">HUID {{ $item->huid }}</div>@endif
                    </div>
                @else
                    <div class="text-center text-[8px] font-bold">{{ $item->item_code }}</div>
                @endif

                <div class="flex min-w-0 items-end justify-between gap-1 border-t border-slate-200 pt-1">
                    <div data-label-code class="label-code min-w-0 flex-1 flex-col">
                        <canvas data-barcode-canvas data-barcode-format="{{ $item->barcode_format ?? 'code128' }}" data-barcode-value="{{ $item->barcode }}" aria-label="Barcode {{ $item->barcode }}"></canvas>
                        <span data-code-error hidden class="label-error"></span>
                        <span class="font-mono text-[6px] text-slate-700">{{ $item->barcode }}</span>
                    </div>
                    @if($include_qr)
                        <div data-label-code class="label-qr flex shrink-0 flex-col items-center">
                            @if($item->qr_token)
                                <canvas data-qr-canvas data-qr-value="{{ route('inventory.qr.lookup', ['token' => $item->qr_token]) }}" aria-label="Secure item QR code"></canvas>
                                <span data-code-error hidden class="label-error"></span>
                            @else
                                <span class="text-[7px] text-amber-800">QR missing</span>
                            @endif
                        </div>
                    @endif
                </div>
            </article>
        @endfor
    @endforeach
</div>

@if($isPrint)
    <script>
        window.addEventListener('load', () => window.print());
    </script>
@endif
@endsection
