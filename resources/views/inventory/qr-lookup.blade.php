<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jewellery Item Details</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 p-4 text-slate-800">
    <main class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Abharan ERP · Jewellery details</p>
        <h1 class="mt-2 text-xl font-bold text-slate-900">{{ $item['name'] }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $item['category'] }} · {{ $item['metal_type'] }} · {{ $item['purity'] }}</p>
        <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Item code</dt><dd class="mt-1 font-semibold">{{ $item['item_code'] }}</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Barcode</dt><dd class="mt-1 font-mono font-semibold">{{ $item['barcode'] }}</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Gross weight</dt><dd class="mt-1 font-semibold">{{ number_format((float) $item['gross_weight'], 3) }} g</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Net weight</dt><dd class="mt-1 font-semibold">{{ number_format((float) $item['net_weight'], 3) }} g</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Stone weight</dt><dd class="mt-1 font-semibold">{{ number_format((float) $item['stone_weight'], 3) }} g</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Diamond weight</dt><dd class="mt-1 font-semibold">{{ number_format((float) $item['diamond_weight'], 3) }} ct</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">HUID</dt><dd class="mt-1 font-semibold">{{ $item['huid'] ?: 'Not recorded' }}</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Inventory status</dt><dd class="mt-1 font-semibold">{{ ucfirst(str_replace('_', ' ', $item['status'])) }}</dd></div>
        </dl>
    </main>
</body>
</html>
