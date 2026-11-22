@extends('layouts.app')

@section('header', 'Jewellery Exchange')

@section('content')
<div class="max-w-6xl mx-auto space-y-5" x-data="invoiceExchange(@json($replacementItems), @json($returnableItems))">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Exchange Against {{ $invoice->invoice_no }}</h2>
            <p class="text-xs text-slate-500 mt-1">{{ $invoice->customer_name_snapshot ?: $invoice->customer?->name ?: 'Walk-in Customer' }} · Original invoice is preserved.</p>
        </div>
        <a href="{{ route('pos.invoice', $invoice) }}" class="px-3 py-2 rounded-lg border border-slate-300 bg-white text-sm">Back to invoice</a>
    </div>

    @if($returnableItems->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No unreturned items remain on this invoice.</div>
    @else
        <form action="{{ route('pos.invoice.exchange.store', $invoice) }}" method="POST" class="space-y-5" onsubmit="return confirm('Complete this exchange and create the linked replacement invoice?');">
            @csrf
            <input type="hidden" name="request_token" value="{{ $requestToken }}">

            <section class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
                <h3 class="font-semibold text-slate-800">1. Select the returned jewellery</h3>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Original invoice item</label>
                        <select name="return_invoice_item_id" x-model="returnItemId" @change="returnQuantity = 1" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Select an item</option>
                            <template x-for="item in returnItems" :key="item.id">
                                <option :value="item.id" x-text="`${item.product_name} · ${item.metal_type} ${item.purity} · ${item.returnable_quantity} available`"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Return quantity</label>
                        <input type="number" name="return_quantity" x-model.number="returnQuantity" min="1" :max="selectedReturn()?.returnable_quantity || 1" step="1" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid md:grid-cols-4 gap-3 rounded-lg bg-slate-50 p-3 text-xs">
                    <div><span class="text-slate-500">Metal / purity</span><div class="font-semibold" x-text="selectedReturn() ? `${selectedReturn().metal_type} ${selectedReturn().purity}` : '-'"></div></div>
                    <div><span class="text-slate-500">Returned weight</span><div class="font-semibold" x-text="`${(Number(selectedReturn()?.gross_weight || 0) * Number(returnQuantity || 0)).toFixed(3)} g gross`"></div></div>
                    <div><span class="text-slate-500">Original taxable / GST</span><div class="font-semibold" x-text="selectedReturn() ? `₹${(selectedReturn().taxable_value / selectedReturn().quantity * returnQuantity).toFixed(2)} / ₹${(selectedReturn().gst_amount / selectedReturn().quantity * returnQuantity).toFixed(2)}` : '-'"></div></div>
                    <div><span class="text-slate-500">Return credit value</span><div class="font-semibold text-amber-700" x-text="`₹${returnValue().toFixed(2)}`"></div></div>
                </div>
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Condition</label>
                        <input name="condition" value="Good / Intact" required maxlength="30" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Exchange reason</label>
                        <input name="reason" required maxlength="255" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Reason for exchange">
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-800">2. Add replacement jewellery</h3>
                        <p class="text-xs text-slate-500 mt-1">Only available stock from this branch is selectable; pricing is recalculated from approved rates at checkout.</p>
                    </div>
                    <button type="button" @click="replacementRows.push({ inventory_item_id: '' })" class="px-3 py-2 rounded-lg bg-amber-600 text-white text-xs font-semibold">+ Add item</button>
                </div>

                <div class="space-y-3">
                    <template x-for="(row, index) in replacementRows" :key="index">
                        <div class="grid md:grid-cols-12 gap-2 items-start border border-slate-200 rounded-lg p-3">
                            <div class="md:col-span-8">
                                <label class="block text-xs font-medium text-slate-600 mb-1">Search / scan SKU, barcode or HUID</label>
                                <input type="search" x-model="row.search" @input="selectScannedItem(row)" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm mb-2" placeholder="Search inventory or scan a code">
                                <select :name="`replacement_items[${index}][inventory_item_id]`" x-model="row.inventory_item_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Select available replacement item</option>
                                    <template x-for="item in filteredCatalog(row)" :key="item.id">
                                        <option :value="item.id" x-text="`${item.name} · ${item.item_code} · ${item.metal_type} ${item.purity}`"></option>
                                    </template>
                                </select>
                                <div class="mt-2 text-xs text-slate-500" x-show="selectedReplacement(row)">
                                    <span x-text="selectedReplacement(row)?.sku || selectedReplacement(row)?.item_code"></span>
                                    · <span x-text="selectedReplacement(row)?.barcode"></span>
                                    · HUID <span x-text="selectedReplacement(row)?.huid || '-'"></span>
                                </div>
                            </div>
                            <div class="md:col-span-3 text-xs text-slate-600" x-show="selectedReplacement(row)">
                                <div class="font-semibold" x-text="`${selectedReplacement(row)?.gross_weight.toFixed(3)} g · ₹${linePrice(row).taxable.toFixed(2)} taxable`"></div>
                                <div x-text="`Making ₹${linePrice(row).making.toFixed(2)} · Wastage ₹${linePrice(row).wastage.toFixed(2)}`"></div>
                                <div x-text="`GST ${selectedReplacement(row)?.gst_rate}% · ₹${linePrice(row).gst.toFixed(2)}`"></div>
                                <details class="mt-1">
                                    <summary class="cursor-pointer text-amber-700">Price breakdown</summary>
                                    <div>Metal ₹<span x-text="linePrice(row).metal.toFixed(2)"></span></div>
                                    <div>Stone / diamond ₹<span x-text="(Number(selectedReplacement(row)?.stone_charge || 0) + Number(selectedReplacement(row)?.diamond_charge || 0)).toFixed(2)"></span></div>
                                </details>
                            </div>
                            <button type="button" @click="replacementRows.splice(index, 1)" :disabled="replacementRows.length === 1" class="md:col-span-1 text-rose-600 text-xs disabled:opacity-40">Remove</button>
                        </div>
                    </template>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
                <h3 class="font-semibold text-slate-800">3. Tax and settlement</h3>
                <div class="grid md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">GST mode for replacement sale</label>
                        <select name="gst_type" x-model="gstType" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="intra">Intra-state (CGST + SGST)</option>
                            <option value="inter">Inter-state (IGST)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Replacement invoice discount (₹)</label>
                        <input type="number" name="discount_amount" x-model.number="discountAmount" min="0" step="0.01" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div x-show="refundAmount() > 0">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Refund method</label>
                        <select name="refund_method" :required="refundAmount() > 0" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Select method</option>
                            <option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option>
                        </select>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="rounded-lg bg-slate-900 p-4 text-sm text-white space-y-2">
                        <div class="flex justify-between"><span>Returned item value</span><span>₹<span x-text="returnValue().toFixed(2)"></span></span></div>
                        <div class="flex justify-between"><span>Replacement taxable value</span><span>₹<span x-text="totals().taxable.toFixed(2)"></span></span></div>
                        <div class="flex justify-between"><span>Replacement GST</span><span>₹<span x-text="totals().gst.toFixed(2)"></span></span></div>
                        <div class="flex justify-between font-semibold border-t border-slate-700 pt-2"><span>Replacement total</span><span>₹<span x-text="totals().total.toFixed(2)"></span></span></div>
                        <div class="flex justify-between text-amber-300"><span>Exchange credit applied</span><span>- ₹<span x-text="creditApplied().toFixed(2)"></span></span></div>
                        <div class="flex justify-between"><span>Additional payable before payments</span><span>₹<span x-text="additionalPayable().toFixed(2)"></span></span></div>
                        <div class="flex justify-between text-rose-300" x-show="refundAmount() > 0"><span>Refund due</span><span>₹<span x-text="refundAmount().toFixed(2)"></span></span></div>
                        <div class="text-xs text-slate-300" x-show="gstType === 'intra'">CGST ₹<span x-text="totals().cgst.toFixed(2)"></span> · SGST ₹<span x-text="totals().sgst.toFixed(2)"></span></div>
                        <div class="text-xs text-slate-300" x-show="gstType === 'inter'">IGST ₹<span x-text="totals().gst.toFixed(2)"></span></div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <label class="text-xs font-medium text-slate-600">Additional payment received</label>
                            <button type="button" @click="payments.push({method:'cash',amount:0,reference_no:''})" class="text-xs text-amber-700 font-semibold">+ Add payment</button>
                        </div>
                        <template x-for="(payment,index) in payments" :key="index">
                            <div class="grid grid-cols-12 gap-1">
                                <select :name="`payments[${index}][method]`" x-model="payment.method" class="col-span-4 border rounded px-1 py-2 text-xs"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank</option></select>
                                <input type="number" :name="`payments[${index}][amount]`" x-model.number="payment.amount" min="0" step="0.01" class="col-span-4 border rounded px-2 py-2 text-xs" placeholder="Amount">
                                <input type="text" :name="`payments[${index}][reference_no]`" x-model="payment.reference_no" class="col-span-3 border rounded px-2 py-2 text-xs" placeholder="Reference">
                                <button type="button" @click="payments.splice(index,1)" :disabled="payments.length === 1" class="col-span-1 text-rose-600 disabled:opacity-40">×</button>
                            </div>
                        </template>
                        <button type="button" @click="fillBalance()" class="text-xs text-emerald-700 font-semibold">Pay remaining balance</button>
                        <div class="flex justify-between text-sm font-semibold text-slate-700 border-t pt-2"><span>Balance due after payment</span><span>₹<span x-text="balanceDue().toFixed(2)"></span></span></div>
                    </div>
                </div>
                <button :disabled="!selectedReturn() || !replacementRows.some(row => row.inventory_item_id) || totals().taxable < discountAmount" class="w-full bg-amber-600 hover:bg-amber-700 disabled:opacity-40 text-white rounded-lg py-3 font-semibold">Complete Exchange & Generate Replacement Invoice</button>
            </section>
        </form>
    @endif
</div>

<script>
function invoiceExchange(catalog, returnItems) {
    const money = value => Math.round((Number(value) + Number.EPSILON) * 100) / 100;
    return {
        catalog,
        returnItems,
        returnItemId: '',
        returnQuantity: 1,
        replacementRows: [{ inventory_item_id: '', search: '' }],
        gstType: 'intra',
        discountAmount: 0,
        payments: [{ method: 'cash', amount: 0, reference_no: '' }],
        selectedReturn() {
            return this.returnItems.find(item => String(item.id) === String(this.returnItemId));
        },
        returnValue() {
            const item = this.selectedReturn();
            return item ? money(item.line_total / item.quantity * Number(this.returnQuantity || 0)) : 0;
        },
        selectedReplacement(row) {
            return this.catalog.find(item => String(item.id) === String(row.inventory_item_id));
        },
        filteredCatalog(row) {
            const term = String(row.search || '').toLowerCase().trim();
            return term ? this.catalog.filter(item => [item.name, item.item_code, item.sku, item.barcode, item.huid].some(value => String(value || '').toLowerCase().includes(term))) : this.catalog;
        },
        selectScannedItem(row) {
            const term = String(row.search || '').toLowerCase().trim();
            if (!term) return;
            const exact = this.catalog.find(item => [item.item_code, item.sku, item.barcode, item.huid].some(value => String(value || '').toLowerCase() === term));
            if (exact) row.inventory_item_id = exact.id;
        },
        linePrice(row) {
            const item = this.selectedReplacement(row);
            if (!item) return { metal: 0, making: 0, wastage: 0, taxable: 0, gst: 0 };
            const net = Math.max(0, Number(item.gross_weight || 0) - Number(item.stone_weight || 0));
            const metal = money(net * Number(item.metal_rate || 0));
            const wastageWeight = net * Number(item.wastage_pct || 0) / 100;
            const wastage = money(wastageWeight * Number(item.metal_rate || 0));
            const rate = Number(item.making_charge_rate || 0);
            const making = item.making_charge_type === 'percentage' ? money(metal * rate / 100)
                : (item.making_charge_type === 'fixed' ? money(rate) : money(net * rate));
            const taxable = money(metal + wastage + making + Number(item.stone_charge || 0) + Number(item.diamond_charge || 0) + Number(item.hallmark_charge || 0) + Number(item.other_charge || 0));
            return { metal, making, wastage, taxable, gst: money(taxable * Number(item.gst_rate || 0) / 100), gstRate: Number(item.gst_rate || 0) };
        },
        totals() {
            const lines = this.replacementRows.map(row => this.linePrice(row)).filter((line, index) => this.selectedReplacement(this.replacementRows[index]));
            const taxable = money(lines.reduce((sum, line) => sum + line.taxable, 0));
            const discount = Math.min(taxable, Math.max(0, Number(this.discountAmount || 0)));
            let remaining = discount;
            const gst = money(lines.reduce((sum, line, index) => {
                const allocated = index === lines.length - 1 ? remaining : money(discount * line.taxable / (taxable || 1));
                remaining = money(remaining - allocated);
                return sum + money((line.taxable - allocated) * line.gstRate / 100);
            }, 0));
            const netTaxable = money(taxable - discount);
            const rawTotal = netTaxable + gst;
            const total = Math.round(rawTotal);
            const cgst = this.gstType === 'intra' ? money(gst / 2) : 0;
            const sgst = this.gstType === 'intra' ? money(gst - cgst) : 0;
            return { taxable: netTaxable, gst, cgst, sgst, total };
        },
        creditApplied() {
            return Math.min(this.returnValue(), this.totals().total);
        },
        additionalPayable() {
            return Math.max(0, this.totals().total - this.creditApplied());
        },
        refundAmount() {
            return Math.max(0, this.returnValue() - this.totals().total);
        },
        balanceDue() {
            const paid = this.payments.reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
            return Math.max(0, this.additionalPayable() - paid);
        },
        fillBalance() {
            const otherPayments = this.payments.slice(1).reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
            if (!this.payments.length) this.payments.push({ method: 'cash', amount: 0, reference_no: '' });
            this.payments[0].amount = money(Math.max(0, this.additionalPayable() - otherPayments));
        }
    };
}
</script>
@endsection
