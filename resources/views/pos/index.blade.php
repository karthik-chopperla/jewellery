@extends('layouts.app')

@section('header', 'Jewellery POS & Advanced Price Calculation Engine')

@section('content')
<div x-data="jewelleryPos()" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <!-- Left 2 Columns: Barcode / Item Picker & Cart -->
    <div class="xl:col-span-2 space-y-5">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[240px]">
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Scan Barcode / Search Piece (Item Code, HUID, Name)</label>
                    <input type="text" x-model="search" @keydown.enter.prevent="addFirstMatch()" placeholder="Scan barcode e.g. 890100000001 or type 'Ring'..." class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div class="w-48">
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Branch</label>
                    <select x-model="branchId" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Quick Pick Grid -->
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-52 overflow-y-auto">
                <template x-for="item in filteredCatalog()" :key="item.id">
                    <button type="button" @click="addItem(item)" class="text-left p-2.5 rounded-lg border border-slate-200 hover:border-amber-400 hover:bg-amber-50/50 transition">
                        <div class="flex justify-between items-start gap-1">
                            <span class="text-xs font-bold text-slate-800 truncate" x-text="item.name"></span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold" x-text="item.purity"></span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-1">
                            <span x-text="item.item_code"></span> · Net: <span x-text="item.net_weight.toFixed(3)"></span>g · ₹<span x-text="item.metal_rate"></span>/g
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <!-- Cart Table -->
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-slate-800">Billing Items (<span x-text="cart.length"></span>)</h2>
                <button type="button" @click="addCustomItem()" class="text-xs text-amber-700 font-semibold hover:underline">+ Add Manual Item</button>
            </div>

            <div class="space-y-3" x-show="cart.length > 0">
                <template x-for="(row, idx) in cart" :key="idx">
                    <div class="border border-slate-200 rounded-lg p-3 bg-slate-50/50">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <div class="font-semibold text-sm text-slate-900" x-text="row.product_name"></div>
                            <button type="button" @click="cart.splice(idx, 1)" class="text-xs text-rose-600 hover:underline">Remove</button>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2 text-xs">
                            <div>
                                <label class="text-slate-500">Gross (g)</label>
                                <input type="number" step="0.001" x-model.number="row.gross_weight" @input="row.net_weight = Math.max(0, +(row.gross_weight - row.stone_weight).toFixed(3))" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Stone (g)</label>
                                <input type="number" step="0.001" x-model.number="row.stone_weight" @input="row.net_weight = Math.max(0, +(row.gross_weight - row.stone_weight).toFixed(3))" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Net Wt (g)</label>
                                <input type="number" step="0.001" x-model.number="row.net_weight" class="w-full border rounded px-2 py-1 bg-white font-semibold">
                            </div>
                            <div>
                                <label class="text-slate-500">Rate (₹/g)</label>
                                <input type="number" step="0.01" x-model.number="row.metal_rate" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Making Type</label>
                                <select x-model="row.making_charge_type" class="w-full border rounded px-2 py-1 bg-white">
                                    <option value="per_gram">₹/Gram</option>
                                    <option value="percentage">% of Gold</option>
                                    <option value="fixed">Fixed ₹</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-slate-500">Making Val</label>
                                <input type="number" step="0.01" x-model.number="row.making_charge_rate" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Wastage %</label>
                                <input type="number" step="0.1" x-model.number="row.wastage_pct" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Stone ₹</label>
                                <input type="number" step="1" x-model.number="row.stone_charge" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Diamond ₹</label>
                                <input type="number" step="1" x-model.number="row.diamond_charge" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Hallmark ₹</label>
                                <input type="number" step="1" x-model.number="row.hallmark_charge" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Discount ₹</label>
                                <input type="number" step="1" x-model.number="row.discount" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div class="flex flex-col justify-end">
                                <div class="text-[11px] text-slate-500">Line Total (incl. GST)</div>
                                <div class="font-bold text-amber-700 text-sm">₹<span x-text="calcItem(row).line_total.toLocaleString('en-IN', {minimumFractionDigits: 2})"></span></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div x-show="cart.length === 0" class="py-10 text-center text-sm text-slate-400">
                Scan a barcode or click any jewellery piece above to start billing.
            </div>
        </div>
    </div>

    <!-- Right Column: Customer, Adjustments, Payment & Checkout -->
    <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4 h-fit">
        <h2 class="text-base font-semibold text-slate-800 border-b border-slate-100 pb-2">Checkout & Settlement</h2>

        <form action="{{ route('pos.store') }}" method="POST" @submit="prepareSubmit($event)">
            @csrf
            <input type="hidden" name="branch_id" :value="branchId">

            <div class="space-y-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Customer</label>
                    <select name="customer_id" x-model="customerId" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Walk-in Customer</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone }}) — {{ strtoupper($c->group_tier) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Salesperson</label>
                        <select name="salesperson_id" class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs">
                            @foreach($salespersons as $sp)
                                <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Bill Type</label>
                        <select name="invoice_type" class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs">
                            <option value="sale">Tax Invoice (Sale)</option>
                            <option value="credit">Credit Sale</option>
                            <option value="estimate">Estimate / Quotation</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">GST Mode</label>
                        <select name="gst_type" x-model="gstType" class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs">
                            <option value="intra">Intra-State (CGST + SGST)</option>
                            <option value="inter">Inter-State (IGST)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Invoice Discount (₹)</label>
                        <input type="number" step="1" name="discount_amount" x-model.number="discountAmount" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Old Gold ₹</label>
                        <input type="number" step="1" name="old_gold_deduction" x-model.number="oldGoldDeduction" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Advance ₹</label>
                        <input type="number" step="1" name="advance_adjusted" x-model.number="advanceAdjusted" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Scheme ₹</label>
                        <input type="number" step="1" name="scheme_adjusted" x-model.number="schemeAdjusted" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-xs">
                    </div>
                </div>

                <!-- Summary Box -->
                <div class="bg-slate-900 text-slate-100 rounded-xl p-4 space-y-1.5 text-xs">
                    <div class="flex justify-between"><span>Taxable Subtotal</span><span>₹<span x-text="totals().subtotal.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-slate-300"><span>Making Charges</span><span>₹<span x-text="totals().makingTotal.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-slate-300" x-show="gstType === 'intra'"><span>CGST (1.5%) + SGST (1.5%)</span><span>₹<span x-text="totals().gstTotal.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-slate-300" x-show="gstType === 'inter'"><span>IGST (3%)</span><span>₹<span x-text="totals().gstTotal.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-amber-300" x-show="oldGoldDeduction > 0"><span>Old Gold Adjusted</span><span>- ₹<span x-text="oldGoldDeduction.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-base font-bold text-amber-400 border-t border-slate-700 pt-2 mt-2">
                        <span>Net Payable</span>
                        <span>₹<span x-text="totals().grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2})"></span></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Payment Mode</label>
                        <select name="payment_method" class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs">
                            <option value="upi">UPI</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Paid Amount (₹)</label>
                        <input type="number" step="0.01" name="paid_amount" x-model.number="paidAmount" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-sm font-semibold">
                    </div>
                </div>

                <input type="text" name="payment_reference" placeholder="UPI / Card /UTR Reference (optional)" class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-xs">

                <!-- Hidden serialized items -->
                <div id="pos-hidden-items"></div>

                <button type="submit" :disabled="cart.length === 0" class="w-full bg-amber-600 hover:bg-amber-700 disabled:opacity-40 text-white font-semibold py-2.5 rounded-lg text-sm transition">
                    Generate Invoice & Print
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function jewelleryPos() {
    return {
        catalog: @json($availableItems),
        branchId: '{{ $branchId }}',
        search: '',
        cart: [],
        customerId: '',
        gstType: 'intra',
        discountAmount: 0,
        oldGoldDeduction: 0,
        advanceAdjusted: 0,
        schemeAdjusted: 0,
        paidAmount: 0,

        filteredCatalog() {
            const q = this.search.toLowerCase().trim();
            if (!q) return this.catalog.slice(0, 12);
            return this.catalog.filter(i =>
                i.name.toLowerCase().includes(q) ||
                i.item_code.toLowerCase().includes(q) ||
                i.barcode.toLowerCase().includes(q) ||
                (i.huid && i.huid.toLowerCase().includes(q))
            );
        },

        addFirstMatch() {
            const list = this.filteredCatalog();
            if (list.length > 0) {
                this.addItem(list[0]);
                this.search = '';
            }
        },

        addItem(item) {
            if (this.cart.some(c => c.inventory_item_id === item.id)) return;
            this.cart.push({
                inventory_item_id: item.id,
                product_name: item.name,
                metal_type: item.metal_type,
                purity: item.purity,
                gross_weight: item.gross_weight,
                stone_weight: item.stone_weight,
                net_weight: item.net_weight,
                metal_rate: item.metal_rate,
                making_charge_type: item.making_charge_type,
                making_charge_rate: item.making_charge_rate,
                wastage_pct: item.wastage_pct,
                stone_charge: item.stone_charge,
                diamond_charge: item.diamond_charge,
                hallmark_charge: item.hallmark_charge,
                other_charge: item.other_charge,
                discount: 0,
                gst_rate: item.gst_rate
            });
            this.syncPaid();
        },

        addCustomItem() {
            this.cart.push({
                inventory_item_id: null,
                product_name: 'Custom 22K Gold Jewellery',
                metal_type: 'Gold',
                purity: '22K',
                gross_weight: 10,
                stone_weight: 0,
                net_weight: 10,
                metal_rate: {{ \App\Models\MetalRate::latestRate('Gold', '22K') }},
                making_charge_type: 'per_gram',
                making_charge_rate: 600,
                wastage_pct: 0,
                stone_charge: 0,
                diamond_charge: 0,
                hallmark_charge: 45,
                other_charge: 0,
                discount: 0,
                gst_rate: 3
            });
            this.syncPaid();
        },

        calcItem(r) {
            const metalVal = r.net_weight * r.metal_rate;
            const wastageVal = (r.net_weight * (r.wastage_pct / 100)) * r.metal_rate;
            let making = 0;
            if (r.making_charge_type === 'percentage') making = metalVal * (r.making_charge_rate / 100);
            else if (r.making_charge_type === 'fixed') making = r.making_charge_rate;
            else making = r.net_weight * r.making_charge_rate;

            const taxable = Math.max(0, metalVal + wastageVal + making + r.stone_charge + r.diamond_charge + r.hallmark_charge + r.other_charge - r.discount);
            const gst = taxable * (r.gst_rate / 100);
            return { making, taxable, gst, line_total: +(taxable + gst).toFixed(2) };
        },

        totals() {
            let subtotal = 0, makingTotal = 0, gstTotal = 0;
            this.cart.forEach(r => {
                const c = this.calcItem(r);
                subtotal += c.taxable;
                makingTotal += c.making;
                gstTotal += c.gst;
            });
            const netTaxable = Math.max(0, subtotal - this.discountAmount);
            if (subtotal > 0 && this.discountAmount > 0) {
                gstTotal = gstTotal * (netTaxable / subtotal);
            }
            const gross = Math.round(netTaxable + gstTotal);
            const grandTotal = Math.max(0, gross - this.oldGoldDeduction - this.advanceAdjusted - this.schemeAdjusted);
            return { subtotal: netTaxable, makingTotal, gstTotal, grandTotal };
        },

        syncPaid() {
            this.paidAmount = this.totals().grandTotal;
        },

        prepareSubmit() {
            const container = document.getElementById('pos-hidden-items');
            container.innerHTML = '';
            this.cart.forEach((item, idx) => {
                Object.entries(item).forEach(([k, v]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `items[${idx}][${k}]`;
                    input.value = v ?? '';
                    container.appendChild(input);
                });
            });
        }
    };
}
</script>
@endpush

