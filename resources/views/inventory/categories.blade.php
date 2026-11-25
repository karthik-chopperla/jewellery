@extends('layouts.app')

@section('header', 'Jewellery Product Categories')

@section('content')
<div class="space-y-5">
    <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <h2 class="text-sm font-semibold text-amber-950">What are categories?</h2>
        <p class="mt-1 text-xs leading-5 text-amber-900">Create categories such as Rings, Necklaces or Bangles. Categories appear in Product Master and POS. Their GST, making-charge and wastage defaults are used when you select a category for a manual POS item.</p>
    </section>

    @if($errors->any())
        <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    @if(session('success'))
        <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-base font-semibold text-slate-900">Add a Category</h2>
        <form action="{{ route('categories.store') }}" method="POST" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            @csrf
            <label class="text-xs font-medium text-slate-600">Category name
                <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Rings" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="text-xs font-medium text-slate-600">Short code
                <input name="code" value="{{ old('code') }}" required maxlength="20" placeholder="RNG" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
            </label>
            <label class="text-xs font-medium text-slate-600">Making charge type
                <select name="default_making_charge_type" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="per_gram">Per gram</option>
                    <option value="percentage">Percentage</option>
                    <option value="fixed">Fixed amount</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Making charge default
                <input type="number" name="default_making_charge_value" min="0" step="0.01" value="{{ old('default_making_charge_value', 0) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="text-xs font-medium text-slate-600">Wastage default (%)
                <input type="number" name="default_wastage_pct" min="0" max="100" step="0.01" value="{{ old('default_wastage_pct', 0) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="text-xs font-medium text-slate-600">GST rate (%)
                <input type="number" name="gst_rate" min="0" max="100" step="0.01" value="{{ old('gst_rate', 3) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <div class="sm:col-span-2 xl:col-span-6">
                <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Add Category</button>
            </div>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Existing Categories</h2>
        @if($categories->isEmpty())
            <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">No categories yet. Add one above to make it available in Product Master and POS.</p>
        @else
            <div class="space-y-3">
                @foreach($categories as $category)
                    <div class="rounded-lg border border-slate-200 p-3">
                        <form action="{{ route('categories.update', $category) }}" method="POST" class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-7">
                            @csrf
                            @method('PUT')
                            <label class="text-xs font-medium text-slate-600">Name
                                <input name="name" value="{{ $category->name }}" required maxlength="255" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-2 text-sm">
                            </label>
                            <label class="text-xs font-medium text-slate-600">Code
                                <input name="code" value="{{ $category->code }}" required maxlength="20" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-2 text-sm uppercase">
                            </label>
                            <label class="text-xs font-medium text-slate-600">Making charge type
                                <select name="default_making_charge_type" required class="mt-1 w-full rounded border border-slate-300 px-2.5 py-2 text-sm">
                                    <option value="per_gram" @selected($category->default_making_charge_type === 'per_gram')>Per gram</option>
                                    <option value="percentage" @selected($category->default_making_charge_type === 'percentage')>Percentage</option>
                                    <option value="fixed" @selected($category->default_making_charge_type === 'fixed')>Fixed amount</option>
                                </select>
                            </label>
                            <label class="text-xs font-medium text-slate-600">Making default
                                <input type="number" name="default_making_charge_value" min="0" step="0.01" value="{{ $category->default_making_charge_value }}" required class="mt-1 w-full rounded border border-slate-300 px-2.5 py-2 text-sm">
                            </label>
                            <label class="text-xs font-medium text-slate-600">Wastage (%)
                                <input type="number" name="default_wastage_pct" min="0" max="100" step="0.01" value="{{ $category->default_wastage_pct }}" required class="mt-1 w-full rounded border border-slate-300 px-2.5 py-2 text-sm">
                            </label>
                            <label class="text-xs font-medium text-slate-600">GST (%)
                                <input type="number" name="gst_rate" min="0" max="100" step="0.01" value="{{ $category->gst_rate }}" required class="mt-1 w-full rounded border border-slate-300 px-2.5 py-2 text-sm">
                            </label>
                            <div class="flex items-center gap-2">
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Save</button>
                                <span class="text-[11px] text-slate-500">{{ $category->products_count }} product(s)</span>
                            </div>
                        </form>
                        <form action="{{ route('categories.destroy', $category) }}" method="POST" class="mt-2 text-right" onsubmit="return confirm('Delete category {{ $category->name }}?');">
                            @csrf
                            @method('DELETE')
                            <button @disabled($category->products_count > 0) class="text-xs font-medium text-rose-700 hover:underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline" title="{{ $category->products_count > 0 ? 'Reassign or remove its products first.' : 'Delete this category.' }}">Delete category</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
