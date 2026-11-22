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
                            <input type="text" x-model="row.product_name" :readonly="row.inventory_item_id" class="font-semibold text-sm text-slate-900 bg-transparent border-b border-transparent focus:border-slate-300 focus:outline-none">
                            <button type="button" @click="cart.splice(idx, 1)" class="text-xs text-rose-600 hover:underline">Remove</button>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2 text-xs">
                            <div>
                                <label class="text-slate-500">Gross (g)</label>
                                <input type="number" min="0" step="0.001" x-model.number="row.gross_weight" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Stone (g)</label>
                                <input type="number" min="0" step="0.001" x-model.number="row.stone_weight" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Other Ded. (g)</label>
                                <input type="number" min="0" step="0.001" x-model.number="row.other_deductions" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Quantity</label>
                                <input type="number" min="1" step="1" x-model.number="row.quantity" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">SKU</label>
                                <input type="text" x-model="row.sku" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Barcode</label>
                                <input type="text" x-model="row.barcode" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">HUID</label>
                                <input type="text" x-model="row.huid" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Category</label>
                                <select x-model="row.category_id" @change="setCategory(row)" :disabled="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                                    <option value="">Select</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-slate-500">HSN</label>
                                <input type="text" x-model="row.hsn_code" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Metal / Purity</label>
                                <div class="flex gap-1">
                                    <select x-model="row.metal_type" @change="updateRate(row)" :disabled="row.inventory_item_id" class="w-1/2 border rounded px-1 py-1 bg-white">
                                        <option>Gold</option><option>Silver</option><option>Platinum</option><option>Diamond</option>
                                    </select>
                                    <select x-model="row.purity" @change="updateRate(row)" :disabled="row.inventory_item_id" class="w-1/2 border rounded px-1 py-1 bg-white">
                                        <template x-for="purity in puritiesFor(row.metal_type)" :key="purity">
                                            <option :value="purity" x-text="purity"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="text-slate-500">Net Wt (g)</label>
                                <input type="number" :value="netWeight(row).toFixed(3)" readonly class="w-full border rounded px-2 py-1 bg-slate-100 font-semibold">
                            </div>
                            <div>
                                <label class="text-slate-500">Rate (₹/g)</label>
                                <input type="number" step="0.01" :value="row.metal_rate" readonly class="w-full border rounded px-2 py-1 bg-slate-100">
                            </div>
                            <div>
                                <label class="text-slate-500">Making Type</label>
                                <select x-model="row.making_charge_type" :disabled="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                                    <option value="per_gram">₹/Gram</option>
                                    <option value="percentage">% of Gold</option>
                                    <option value="fixed">Fixed ₹</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-slate-500">Making Val</label>
                                <input type="number" min="0" step="0.01" x-model.number="row.making_charge_rate" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Wastage %</label>
                                <input type="number" min="0" max="100" step="0.1" x-model.number="row.wastage_pct" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Wastage Wt (g)</label>
                                <input type="number" min="0" step="0.001" x-model.number="row.wastage_weight" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white" placeholder="Use % if blank">
                            </div>
                            <div>
                                <label class="text-slate-500">Stone ₹</label>
                                <input type="number" min="0" step="0.01" x-model.number="row.stone_charge" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Diamond ₹</label>
                                <input type="number" min="0" step="0.01" x-model.number="row.diamond_charge" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Hallmark ₹</label>
                                <input type="number" min="0" step="0.01" x-model.number="row.hallmark_charge" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">Other ₹</label>
                                <input type="number" min="0" step="0.01" x-model.number="row.other_charge" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
                            </div>
                            <div>
                                <label class="text-slate-500">GST %</label>
                                <input type="number" min="0" max="100" step="0.01" x-model.number="row.gst_rate" :readonly="row.inventory_item_id" class="w-full border rounded px-2 py-1 bg-white">
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
                        <details class="mt-3 border-t border-slate-200 pt-2 text-xs">
                            <summary class="cursor-pointer font-semibold text-slate-600">View pricing breakdown</summary>
                            <div class="mt-2 grid grid-cols-2 gap-y-1 text-slate-600">
                                <span>Metal value</span><span class="text-right">₹<span x-text="calcItem(row).metal_value.toFixed(2)"></span></span>
                                <span>Wastage value</span><span class="text-right">₹<span x-text="calcItem(row).wastage_value.toFixed(2)"></span></span>
                                <span>Making charges</span><span class="text-right">₹<span x-text="calcItem(row).making.toFixed(2)"></span></span>
                                <span>Taxable value after item discount</span><span class="text-right">₹<span x-text="calcItem(row).taxable.toFixed(2)"></span></span>
                                <span>GST at <span x-text="row.gst_rate"></span>%</span><span class="text-right">₹<span x-text="calcItem(row).gst.toFixed(2)"></span></span>
                            </div>
                        </details>
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
                    <select name="customer_id" x-model="customerId" @change="customerChanged()" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
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
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Customer Name</label><input name="customer_name" x-model="customer.name" class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs" placeholder="Walk-in customer"></div>
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Mobile</label><input name="customer_phone" x-model="customer.phone" class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs" placeholder="Mobile number"></div>
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Customer GSTIN</label><input name="customer_gstin" x-model="customer.gstin" maxlength="15" class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs" placeholder="Optional"></div>
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Billing Address</label><input name="customer_address" x-model="customer.address" class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs" placeholder="Address"></div>
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

                <div class="border border-slate-200 rounded-lg p-3 space-y-2">
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="checkbox" x-model="oldGold.enabled"> Record old gold exchange</label>
                    <div x-show="oldGold.enabled" class="grid grid-cols-2 gap-2">
                        <input name="old_gold[item_description]" x-model="oldGold.description" :disabled="!oldGold.enabled" placeholder="Item description" class="border rounded px-2 py-1.5 text-xs">
                        <select name="old_gold[metal_type]" x-model="oldGold.metal_type" :disabled="!oldGold.enabled" class="border rounded px-2 py-1.5 text-xs"><option>Gold</option><option>Silver</option><option>Platinum</option></select>
                        <input name="old_gold[purity]" x-model="oldGold.purity" :disabled="!oldGold.enabled" placeholder="Purity (e.g. 22K)" class="border rounded px-2 py-1.5 text-xs">
                        <input type="number" name="old_gold[purity_pct]" x-model.number="oldGold.purity_pct" :disabled="!oldGold.enabled" min="0.01" max="100" step="0.01" placeholder="Assayed purity %" class="border rounded px-2 py-1.5 text-xs">
                        <input type="number" name="old_gold[gross_weight]" x-model.number="oldGold.gross_weight" :disabled="!oldGold.enabled" min="0.001" step="0.001" placeholder="Gross weight (g)" class="border rounded px-2 py-1.5 text-xs">
                        <input type="number" name="old_gold[stone_weight]" x-model.number="oldGold.stone_weight" :disabled="!oldGold.enabled" min="0" step="0.001" placeholder="Stone weight (g)" class="border rounded px-2 py-1.5 text-xs">
                        <input type="number" name="old_gold[other_deductions]" x-model.number="oldGold.other_deductions" :disabled="!oldGold.enabled" min="0" step="0.001" placeholder="Other deductions (g)" class="border rounded px-2 py-1.5 text-xs">
                        <input type="number" name="old_gold[deduction_pct]" x-model.number="oldGold.deduction_pct" :disabled="!oldGold.enabled" min="0" max="50" step="0.01" placeholder="Deduction %" class="border rounded px-2 py-1.5 text-xs">
                        <input type="number" name="old_gold[melting_loss_pct]" x-model.number="oldGold.melting_loss_pct" :disabled="!oldGold.enabled" min="0" max="25" step="0.01" placeholder="Melting loss %" class="border rounded px-2 py-1.5 text-xs">
                        <input type="hidden" name="old_gold[acknowledgement]" :disabled="!oldGold.enabled" :value="oldGold.acknowledgement ? '1' : ''">
                        <label class="col-span-2 flex items-center gap-2 text-xs"><input type="checkbox" x-model="oldGold.acknowledgement" :disabled="!oldGold.enabled"> Customer acknowledges the old-metal description, weight and valuation</label>
                        <div class="col-span-2 text-right text-xs font-semibold text-emerald-700">Exchange value: ₹<span x-text="oldGoldValue().toFixed(2)"></span></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Advance ₹</label>
                        <input type="number" min="0" step="0.01" name="advance_adjusted" x-model.number="advanceAdjusted" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Scheme ₹</label>
                        <input type="number" min="0" step="0.01" name="scheme_adjusted" x-model.number="schemeAdjusted" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-xs">
                    </div>
                </div>

                <!-- Summary Box -->
                <div class="bg-slate-900 text-slate-100 rounded-xl p-4 space-y-1.5 text-xs">
                    <div class="flex justify-between"><span>Taxable Subtotal</span><span>₹<span x-text="totals().subtotal.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-slate-300"><span>Making Charges</span><span>₹<span x-text="totals().makingTotal.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-slate-300" x-show="gstType === 'intra'"><span>CGST</span><span>₹<span x-text="totals().cgst.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-slate-300" x-show="gstType === 'intra'"><span>SGST</span><span>₹<span x-text="totals().sgst.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-slate-300" x-show="gstType === 'inter'"><span>IGST</span><span>₹<span x-text="totals().igst.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-slate-300"><span>Round-off</span><span>₹<span x-text="totals().roundOff.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-amber-300" x-show="oldGold.enabled && oldGoldValue() > 0"><span>Old Gold Adjusted</span><span>- ₹<span x-text="oldGoldValue().toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-amber-300" x-show="advanceAdjusted > 0"><span>Advance Adjusted</span><span>- ₹<span x-text="advanceAdjusted.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-amber-300" x-show="schemeAdjusted > 0"><span>Scheme Adjusted</span><span>- ₹<span x-text="schemeAdjusted.toFixed(2)"></span></span></div>
                    <div class="flex justify-between text-base font-bold text-amber-400 border-t border-slate-700 pt-2 mt-2">
                        <span>Net Payable</span>
                        <span>₹<span x-text="totals().grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2})"></span></span>
                    </div>
                    <div class="flex justify-between"><span>Balance Due</span><span>₹<span x-text="totals().balanceDue.toFixed(2)"></span></span></div>
                    <div class="flex justify-between"><span>Change Return</span><span>₹<span x-text="totals().changeReturn.toFixed(2)"></span></span></div>
                </div>

                <div class="space-y-2">
                    <div class="flex justify-between items-center"><label class="text-xs font-medium text-slate-600">Split Payments</label><div class="flex gap-2"><button type="button" @click="fillBalance()" class="text-xs text-emerald-700 font-semibold">Pay balance</button><button type="button" @click="payments.push({method:'cash',amount:0,reference_no:''})" class="text-xs text-amber-700 font-semibold">+ Add payment</button></div></div>
                    <template x-for="(payment,index) in payments" :key="index">
                        <div class="grid grid-cols-12 gap-1">
                            <select :name="`payments[${index}][method]`" x-model="payment.method" class="col-span-4 border rounded px-1 py-1.5 text-xs"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank</option></select>
                            <input type="number" min="0" step="0.01" :name="`payments[${index}][amount]`" x-model.number="payment.amount" class="col-span-4 border rounded px-2 py-1.5 text-xs" placeholder="Amount">
                            <input type="text" :name="`payments[${index}][reference_no]`" x-model="payment.reference_no" class="col-span-3 border rounded px-2 py-1.5 text-xs" placeholder="Reference">
                            <button type="button" @click="payments.splice(index,1)" class="col-span-1 text-rose-600" aria-label="Remove payment">×</button>
                        </div>
                    </template>
                </div>

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
        customers: @json($customers),
        categories: @json($categories),
        rateCatalog: @json($metalRateCatalog),
        branchId: '{{ $branchId }}',
        search: '',
        cart: [],
        customerId: '',
        customer: { name: '', phone: '', gstin: '', address: '' },
        gstType: 'intra',
        discountAmount: 0,
        advanceAdjusted: 0,
        schemeAdjusted: 0,
        payments: [{ method: 'cash', amount: 0, reference_no: '' }],
        oldGold: {
            enabled: false, description: '', metal_type: 'Gold', purity: '22K',
            purity_pct: 91.67, gross_weight: 0, stone_weight: 0, other_deductions: 0,
            deduction_pct: 2, melting_loss_pct: 1, acknowledgement: false
        },

        filteredCatalog() {
            const q = this.search.toLowerCase().trim();
            if (!q) return this.catalog.slice(0, 12);
            return this.catalog.filter(i =>
                i.name.toLowerCase().includes(q) ||
                i.item_code.toLowerCase().includes(q) ||
                (i.barcode || '').toLowerCase().includes(q) ||
                (i.huid && i.huid.toLowerCase().includes(q))
            );
        },

        customerChanged() {
            const selected = this.customers.find(customer => String(customer.id) === String(this.customerId));
            this.customer = selected
                ? { name: selected.name, phone: selected.phone, gstin: selected.gstin || '', address: selected.address || '' }
                : { name: '', phone: '', gstin: '', address: '' };
        },

        puritiesFor(metalType) {
            const purities = this.rateCatalog.filter(rate => rate.metal_type === metalType).map(rate => rate.purity);
            return [...new Set(purities)];
        },

        updateRate(row) {
            const rate = this.rateCatalog.find(entry => entry.metal_type === row.metal_type && entry.purity === row.purity);
            row.metal_rate = row.metal_type === 'Diamond' ? 0 : (rate ? rate.rate : 0);
        },

        setCategory(row) {
            const category = this.categories.find(entry => String(entry.id) === String(row.category_id));
            if (category) row.gst_rate = category.gst_rate;
        },

        netWeight(row) {
            return Math.max(0, Number(row.gross_weight || 0) - Number(row.stone_weight || 0) - Number(row.other_deductions || 0));
        },

        oldGoldValue() {
            const purity = this.oldGold.metal_type === 'Gold' ? '24K' : (this.oldGold.metal_type === 'Silver' ? '999' : '950');
            const rate = this.rateCatalog.find(entry => entry.metal_type === this.oldGold.metal_type && entry.purity === purity)?.rate || 0;
            const net = Math.max(0, Number(this.oldGold.gross_weight || 0) - Number(this.oldGold.stone_weight || 0) - Number(this.oldGold.other_deductions || 0));
            const fine = net * (Number(this.oldGold.purity_pct || 0) / 100);
            const effective = fine * (1 - (Number(this.oldGold.deduction_pct || 0) + Number(this.oldGold.melting_loss_pct || 0)) / 100);
            return Math.round(effective * rate * 100) / 100;
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
                sku: item.sku,
                barcode: item.barcode,
                huid: item.huid,
                category_name: item.category_name,
                hsn_code: item.hsn_code,
                category_id: '',
                metal_type: item.metal_type,
                purity: item.purity,
                quantity: 1,
                gross_weight: item.gross_weight,
                stone_weight: item.stone_weight,
                other_deductions: 0,
                metal_rate: item.metal_rate,
                making_charge_type: item.making_charge_type,
                making_charge_rate: item.making_charge_rate,
                wastage_pct: item.wastage_pct,
                wastage_weight: null,
                stone_charge: item.stone_charge,
                diamond_charge: item.diamond_charge,
                hallmark_charge: item.hallmark_charge,
                other_charge: item.other_charge,
                discount: 0,
                gst_rate: item.gst_rate
            });
        },

        addCustomItem() {
            const goldPurity = this.puritiesFor('Gold').includes('22K') ? '22K' : (this.puritiesFor('Gold')[0] || '');
            const rate = this.rateCatalog.find(entry => entry.metal_type === 'Gold' && entry.purity === goldPurity)?.rate || 0;
            this.cart.push({
                inventory_item_id: null,
                product_name: 'Custom Jewellery Item',
                sku: '',
                barcode: '',
                huid: '',
                category_name: '',
                hsn_code: '',
                category_id: '',
                metal_type: 'Gold',
                purity: goldPurity,
                quantity: 1,
                gross_weight: 0,
                stone_weight: 0,
                other_deductions: 0,
                metal_rate: rate,
                making_charge_type: 'per_gram',
                making_charge_rate: 0,
                wastage_pct: 0,
                wastage_weight: null,
                stone_charge: 0,
                diamond_charge: 0,
                hallmark_charge: 0,
                other_charge: 0,
                discount: 0,
                gst_rate: {{ (float) \App\Models\Setting::get('default_gst_rate', '3') }}
            });
        },

        calcItem(r) {
            const money = value => Math.round((value + Number.EPSILON) * 100) / 100;
            const quantity = Math.max(1, Number(r.quantity || 1));
            const netWeight = this.netWeight(r);
            const metalVal = money(netWeight * Number(r.metal_rate || 0) * quantity);
            const wastageWeight = Math.round(Number(r.wastage_weight || (netWeight * Number(r.wastage_pct || 0) / 100)) * 1000) / 1000;
            const wastageVal = money(wastageWeight * Number(r.metal_rate || 0) * quantity);
            let making = 0;
            if (r.making_charge_type === 'percentage') making = money(metalVal * Number(r.making_charge_rate || 0) / 100);
            else if (r.making_charge_type === 'fixed') making = money(Number(r.making_charge_rate || 0) * quantity);
            else making = money(netWeight * Number(r.making_charge_rate || 0) * quantity);

            const taxable = money(Math.max(0, metalVal + wastageVal + making +
                (Number(r.stone_charge || 0) + Number(r.diamond_charge || 0) + Number(r.hallmark_charge || 0) + Number(r.other_charge || 0)) * quantity -
                Number(r.discount || 0) * quantity));
            const gst = money(taxable * Number(r.gst_rate || 0) / 100);
            return {
                making: +making.toFixed(2),
                metal_value: +metalVal.toFixed(2),
                wastage_value: +wastageVal.toFixed(2),
                taxable: +taxable.toFixed(2),
                gst: +gst.toFixed(2),
                line_total: +(taxable + gst).toFixed(2)
            };
        },

        totals() {
            const rawItems = this.cart.map(row => this.calcItem(row));
            const rawSubtotal = rawItems.reduce((sum, item) => sum + item.taxable, 0);
            const invoiceDiscount = Math.min(rawSubtotal, Math.max(0, Number(this.discountAmount || 0)));
            let remainingDiscount = invoiceDiscount;
            let gstTotal = 0;
            rawItems.forEach((item, index) => {
                const discount = rawSubtotal === 0 ? 0 : (index === rawItems.length - 1
                    ? remainingDiscount
                    : Math.round(invoiceDiscount * item.taxable / rawSubtotal * 100) / 100);
                remainingDiscount = Math.round((remainingDiscount - discount) * 100) / 100;
                item.gst = Math.round((item.taxable - discount) * Number(this.cart[index].gst_rate || 0)) / 100;
                gstTotal += item.gst;
            });
            const subtotal = Math.max(0, rawSubtotal - invoiceDiscount);
            const makingTotal = rawItems.reduce((sum, item) => sum + item.making, 0);
            const unrounded = subtotal + gstTotal;
            const rounded = Math.round(unrounded);
            const roundOff = +(rounded - unrounded).toFixed(2);
            const cgst = this.gstType === 'intra' ? Math.round(gstTotal / 2 * 100) / 100 : 0;
            const sgst = this.gstType === 'intra' ? Math.round((gstTotal - cgst) * 100) / 100 : 0;
            const igst = this.gstType === 'inter' ? gstTotal : 0;
            const adjustments = (this.oldGold.enabled ? this.oldGoldValue() : 0) + Number(this.advanceAdjusted || 0) + Number(this.schemeAdjusted || 0);
            const grandTotal = Math.max(0, +(rounded - adjustments).toFixed(2));
            const amountReceived = this.payments.reduce((sum, payment) => sum + Math.max(0, Number(payment.amount || 0)), 0);
            return {
                subtotal, makingTotal, gstTotal, cgst, sgst, igst, roundOff, grandTotal,
                balanceDue: Math.max(0, +(grandTotal - amountReceived).toFixed(2)),
                changeReturn: Math.max(0, +(amountReceived - grandTotal).toFixed(2))
            };
        },

        fillBalance() {
            if (this.payments.length === 0) this.payments.push({ method: 'cash', amount: 0, reference_no: '' });
            const alreadyEntered = this.payments.slice(1).reduce((sum, payment) => sum + Math.max(0, Number(payment.amount || 0)), 0);
            this.payments[0].amount = Math.max(0, +(this.totals().grandTotal - alreadyEntered).toFixed(2));
        },

        prepareSubmit(event) {
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
            if (this.oldGold.enabled && (!this.oldGold.acknowledgement || !this.oldGold.description || !this.oldGold.purity)) {
                event.preventDefault();
                alert('Complete the old-gold details and confirm the customer acknowledgement.');
            }
        }
    };
}
</script>
@endpush

