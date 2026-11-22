@extends('layouts.app')

@section('header', 'Jewellery Sales Entry')

@section('content')
<div x-data="jewelleryPos()" x-init="barcodeLookupUrl = $el.dataset.barcodeLookupUrl" data-barcode-lookup-url="{{ route('pos.barcode.lookup') }}" class="space-y-5">
    <form action="{{ route('pos.store') }}" method="POST" @submit="prepareSubmit($event)" class="space-y-5">
        @csrf
        <input type="hidden" name="branch_id" :value="branchId">

        <section class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Bill Header</h2>
                    <p class="mt-1 text-xs text-slate-500">Bill number is assigned automatically when this bill is saved.</p>
                </div>
                <div class="rounded-lg bg-amber-50 px-3 py-2 text-right">
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-amber-800">Bill date</div>
                    <input type="date" name="bill_date" value="{{ now()->format('Y-m-d') }}" class="mt-1 border-0 bg-transparent p-0 text-sm font-semibold text-slate-800 focus:ring-0">
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Branch</label>
                    <select x-model="branchId" @change="cart = []; refreshPreview()" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Customer / Party</label>
                    <div class="flex gap-2">
                        <select name="customer_id" x-model="customerId" @change="customerChanged()" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Walk-in Customer</option>
                            @foreach($customers as $customerOption)
                                <option value="{{ $customerOption->id }}">{{ $customerOption->name }} ({{ $customerOption->phone }})</option>
                            @endforeach
                        </select>
                        <button type="button" @click="customerModal = true" class="shrink-0 rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50">+ New</button>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Salesperson</label>
                    <select name="salesperson_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach($salespersons as $salesperson)
                            <option value="{{ $salesperson->id }}" @selected($salesperson->id === auth()->id())>{{ $salesperson->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Bill type</label>
                    <select name="invoice_type" x-model="invoiceType" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="sale">Tax Invoice (Sale)</option>
                        <option value="credit">Credit Sale</option>
                        <option value="estimate">Estimate / Quotation</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Customer name</label>
                    <input name="customer_name" x-model="customer.name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Walk-in customer">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Mobile number</label>
                    <input name="customer_phone" x-model="customer.phone" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Customer mobile">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Customer GSTIN</label>
                    <input name="customer_gstin" x-model="customer.gstin" maxlength="15" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Optional">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Customer state</label>
                    <input name="customer_state" x-model="customer.state" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="State">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Billing address</label>
                    <input name="customer_address" x-model="customer.address" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Billing address">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Order reference</label>
                    <input name="order_reference" x-model="orderReference" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Order / job reference">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">GST mode</label>
                    <select name="gst_type" x-model="gstType" @change="refreshPreview()" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="intra">Intra-state (CGST + SGST)</option>
                        <option value="inter">Inter-state (IGST)</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Sale Items</h2>
                    <p class="mt-1 text-xs text-slate-500">Use Tab to move through cells. Scan a tag/barcode and press Enter to add a matching item.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <input type="search" x-model="search" @input="scanError = ''" @keydown.enter.prevent="addFirstMatch()" class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Scan barcode / search code, HUID, name">
                    <button type="button" @click="addCustomItem()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">+ Manual item</button>
                </div>
            </div>

            <p x-show="scanError" x-text="scanError" role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"></p>
            <div class="max-h-48 overflow-y-auto rounded-lg border border-slate-200" x-show="search.trim().length > 0 && filteredCatalog().length > 0">
                <template x-for="item in filteredCatalog().slice(0, 12)" :key="item.id">
                    <button type="button" @click="addItem(item); search = ''" class="flex w-full items-center justify-between border-b border-slate-100 px-3 py-2 text-left text-xs last:border-0 hover:bg-amber-50">
                        <span><strong x-text="item.name"></strong><span class="ml-2 text-slate-500" x-text="`${item.item_code} · ${item.metal_type} ${item.purity} · ${Number(item.gross_weight).toFixed(3)}g`"></span></span>
                        <span class="text-amber-800" x-text="item.barcode || item.huid || ''"></span>
                    </button>
                </template>
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-[2550px] w-full border-collapse text-xs">
                    <thead class="sticky top-0 z-10 bg-slate-100 text-slate-600">
                        <tr class="text-left">
                            <th class="p-2">#</th><th class="p-2">Barcode / Tag</th><th class="p-2">Item code</th><th class="p-2">Product / HSN</th>
                            <th class="p-2">Metal / purity</th><th class="p-2">Qty</th><th class="p-2">Gross g</th><th class="p-2">Stone g</th>
                            <th class="p-2">Other ded. g</th><th class="p-2">Net g</th><th class="p-2">Rate / g</th><th class="p-2">Metal value</th>
                            <th class="p-2">Making type / value</th><th class="p-2">Wastage % / g</th><th class="p-2">Stone value</th><th class="p-2">Diamond value</th>
                            <th class="p-2">Other charges</th><th class="p-2">Discount</th><th class="p-2">Taxable</th><th class="p-2">GST %</th><th class="p-2">Item total</th><th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(row, index) in cart" :key="row.rowKey">
                            <tr class="align-top" :data-row-key="row.rowKey">
                                <td class="p-2 font-semibold text-slate-500" x-text="index + 1"></td>
                                <td class="p-2"><input x-model="row.barcode" @keydown.enter.prevent="scanIntoRow(row)" class="w-28 rounded border px-2 py-1.5" placeholder="Barcode"><input x-model="row.huid" class="mt-1 w-28 rounded border px-2 py-1.5" placeholder="HUID"></td>
                                <td class="p-2"><input x-model="row.sku" :readonly="row.inventory_item_id" class="w-28 rounded border px-2 py-1.5" placeholder="SKU / code"></td>
                                <td class="p-2"><input x-model="row.product_name" :readonly="row.inventory_item_id" class="w-40 rounded border px-2 py-1.5" placeholder="Product name"><input x-model="row.hsn_code" class="mt-1 w-24 rounded border px-2 py-1.5" placeholder="HSN"></td>
                                <td class="p-2"><select x-model="row.metal_type" @change="updateRate(row); queuePreview()" :disabled="row.inventory_item_id" class="w-24 rounded border px-2 py-1.5"><option>Gold</option><option>Silver</option><option>Platinum</option><option>Diamond</option></select><select x-model="row.purity" @change="updateRate(row); queuePreview()" :disabled="row.inventory_item_id" class="mt-1 w-24 rounded border px-2 py-1.5"><template x-for="purity in puritiesFor(row.metal_type)" :key="purity"><option :value="purity" x-text="purity"></option></template></select></td>
                                <td class="p-2"><input type="number" min="1" step="1" x-model.number="row.quantity" @input="queuePreview()" :readonly="row.inventory_item_id" class="w-16 rounded border px-2 py-1.5"></td>
                                <td class="p-2"><input type="number" min="0" step="0.001" x-model.number="row.gross_weight" @input="queuePreview()" :readonly="row.inventory_item_id" class="w-24 rounded border px-2 py-1.5"></td>
                                <td class="p-2"><input type="number" min="0" step="0.001" x-model.number="row.stone_weight" @input="queuePreview()" :readonly="row.inventory_item_id" class="w-24 rounded border px-2 py-1.5"></td>
                                <td class="p-2"><input type="number" min="0" step="0.001" x-model.number="row.other_deductions" @input="queuePreview()" :readonly="row.inventory_item_id" class="w-24 rounded border px-2 py-1.5"></td>
                                <td class="p-2 pt-3 font-semibold" x-text="money(row.preview?.net_weight)"></td>
                                <td class="p-2"><input type="number" :value="row.metal_rate" readonly class="w-24 rounded border bg-slate-50 px-2 py-1.5"></td>
                                <td class="p-2 pt-3 text-right font-medium" x-text="currency(row.preview?.metal_value)"></td>
                                <td class="p-2"><select x-model="row.making_charge_type" @change="queuePreview()" :disabled="row.inventory_item_id" class="w-28 rounded border px-2 py-1.5"><option value="fixed">Fixed</option><option value="per_gram">Per gram</option><option value="percentage">Percent</option></select><input type="number" min="0" step="0.01" x-model.number="row.making_charge_rate" @input="queuePreview()" :readonly="row.inventory_item_id" class="mt-1 w-28 rounded border px-2 py-1.5" placeholder="Rate / amount"></td>
                                <td class="p-2"><input type="number" min="0" max="100" step="0.01" x-model.number="row.wastage_pct" @input="queuePreview()" :readonly="row.inventory_item_id" class="w-24 rounded border px-2 py-1.5" placeholder="%"><input type="number" min="0" step="0.001" x-model.number="row.wastage_weight" @input="queuePreview()" :readonly="row.inventory_item_id" class="mt-1 w-24 rounded border px-2 py-1.5" placeholder="Weight g"></td>
                                <td class="p-2"><input type="number" min="0" step="0.01" x-model.number="row.stone_charge" @input="queuePreview()" :readonly="row.inventory_item_id" class="w-24 rounded border px-2 py-1.5"><div class="pt-1 text-right text-slate-500" x-text="currency(row.preview?.stone_charge)"></div></td>
                                <td class="p-2"><input type="number" min="0" step="0.01" x-model.number="row.diamond_charge" @input="queuePreview()" :readonly="row.inventory_item_id" class="w-24 rounded border px-2 py-1.5"><div class="pt-1 text-right text-slate-500" x-text="currency(row.preview?.diamond_charge)"></div></td>
                                <td class="p-2"><input type="number" min="0" step="0.01" x-model.number="row.hallmark_charge" @input="queuePreview()" :readonly="row.inventory_item_id" class="w-24 rounded border px-2 py-1.5" placeholder="Hallmark"><input type="number" min="0" step="0.01" x-model.number="row.other_charge" @input="queuePreview()" :readonly="row.inventory_item_id" class="mt-1 w-24 rounded border px-2 py-1.5" placeholder="Other"></td>
                                <td class="p-2"><input type="number" min="0" step="0.01" x-model.number="row.discount" @input="queuePreview()" class="w-24 rounded border px-2 py-1.5"></td>
                                <td class="p-2 pt-3 text-right font-medium" x-text="currency(row.preview?.taxable_value)"></td>
                                <td class="p-2"><input type="number" min="0" max="100" step="0.01" x-model.number="row.gst_rate" @input="queuePreview()" :readonly="row.inventory_item_id" class="w-20 rounded border px-2 py-1.5"></td>
                                <td class="p-2 pt-3 text-right font-bold text-amber-800" x-text="currency(row.preview?.line_total)"></td>
                                <td class="p-2"><button type="button" @click="removeItem(index)" class="rounded px-2 py-1 text-rose-600 hover:bg-rose-50" aria-label="Remove item">×</button></td>
                            </tr>
                        </template>
                        <tr x-show="cart.length === 0"><td colspan="22" class="p-10 text-center text-slate-400">Scan an item or choose a product to begin the bill.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs text-slate-500"><span x-text="cart.length"></span> sale item(s) · Net weight is calculated from gross less stone and other deductions.</div>
                <button type="button" @click="addCustomItem()" class="rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-700">+ Add manual jewellery item</button>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="flex border-b border-slate-200 bg-slate-50">
                <button type="button" @click="activeTab = 'adjustments'" :class="activeTab === 'adjustments' ? 'border-b-2 border-amber-600 bg-white text-amber-800' : 'text-slate-600'" class="px-5 py-3 text-sm font-semibold">Customer Adjustments</button>
                <button type="button" @click="activeTab = 'payments'" :class="activeTab === 'payments' ? 'border-b-2 border-amber-600 bg-white text-amber-800' : 'text-slate-600'" class="px-5 py-3 text-sm font-semibold">Payment Details</button>
            </div>

            <div x-show="activeTab === 'adjustments'" class="grid gap-5 p-5 xl:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-4">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-800"><input type="checkbox" x-model="oldGold.enabled" @change="queuePreview()" class="rounded border-slate-300 text-amber-600"> Old gold / silver / platinum exchange</label>
                    <p class="mt-1 text-xs text-slate-500">Valuation and exchange adjustment are calculated by the server using the current approved metal rate.</p>
                    <div x-show="oldGold.enabled" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <input x-model="oldGold.description" @input="queuePreview()" :disabled="!oldGold.enabled" class="rounded border px-3 py-2 text-sm" placeholder="Old item description">
                        <select x-model="oldGold.metal_type" @change="queuePreview()" :disabled="!oldGold.enabled" class="rounded border px-3 py-2 text-sm"><option>Gold</option><option>Silver</option><option>Platinum</option></select>
                        <input x-model="oldGold.purity" :disabled="!oldGold.enabled" class="rounded border px-3 py-2 text-sm" placeholder="Tested purity (e.g. 22K)">
                        <input type="number" min="0.01" max="100" step="0.01" x-model.number="oldGold.purity_pct" @input="queuePreview()" :disabled="!oldGold.enabled" class="rounded border px-3 py-2 text-sm" placeholder="Assayed purity %">
                        <input type="number" min="0.001" step="0.001" x-model.number="oldGold.gross_weight" @input="queuePreview()" :disabled="!oldGold.enabled" class="rounded border px-3 py-2 text-sm" placeholder="Gross weight (g)">
                        <input type="number" min="0" step="0.001" x-model.number="oldGold.stone_weight" @input="queuePreview()" :disabled="!oldGold.enabled" class="rounded border px-3 py-2 text-sm" placeholder="Stone weight (g)">
                        <input type="number" min="0" step="0.001" x-model.number="oldGold.other_deductions" @input="queuePreview()" :disabled="!oldGold.enabled" class="rounded border px-3 py-2 text-sm" placeholder="Other deductions (g)">
                        <input type="number" min="0" max="50" step="0.01" x-model.number="oldGold.deduction_pct" @input="queuePreview()" :disabled="!oldGold.enabled" class="rounded border px-3 py-2 text-sm" placeholder="Deduction %">
                        <input type="number" min="0" max="25" step="0.01" x-model.number="oldGold.melting_loss_pct" @input="queuePreview()" :disabled="!oldGold.enabled" class="rounded border px-3 py-2 text-sm" placeholder="Melting loss %">
                        <label class="sm:col-span-2 flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" x-model="oldGold.acknowledgement" :disabled="!oldGold.enabled"> Customer acknowledgement recorded</label>
                        <div class="sm:col-span-2 rounded bg-emerald-50 p-3 text-right text-sm font-semibold text-emerald-800">Exchange value: <span x-text="currency(preview?.old_gold_deduction)"></span></div>
                    </div>
                </div>
                <div class="grid content-start gap-3 sm:grid-cols-2">
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Customer advance adjustment (₹)</label><input type="number" min="0" step="0.01" name="advance_adjusted" x-model.number="advanceAdjusted" @input="queuePreview()" class="w-full rounded border px-3 py-2 text-sm"></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Scheme adjustment (₹)</label><input type="number" min="0" step="0.01" name="scheme_adjusted" x-model.number="schemeAdjusted" @input="queuePreview()" class="w-full rounded border px-3 py-2 text-sm"></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Other adjustment (₹)</label><input type="number" min="0" step="0.01" name="other_adjusted" x-model.number="otherAdjusted" @input="queuePreview()" class="w-full rounded border px-3 py-2 text-sm"></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Invoice discount (₹)</label><input type="number" min="0" step="0.01" name="discount_amount" x-model.number="discountAmount" @input="queuePreview()" class="w-full rounded border px-3 py-2 text-sm"></div>
                </div>
            </div>

            <div x-show="activeTab === 'payments'" class="space-y-4 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><h3 class="text-sm font-semibold text-slate-800">Tender / Payment entries</h3><p class="mt-1 text-xs text-slate-500">Split a payment across cash, UPI, card and bank transfer. Enter references where applicable.</p></div>
                    <div class="flex gap-3"><button type="button" @click="fillBalance()" class="text-xs font-semibold text-emerald-700">Pay remaining balance</button><button type="button" @click="payments.push({method:'cash',amount:0,reference_no:''})" class="text-xs font-semibold text-amber-700">+ Add payment</button></div>
                </div>
                <div class="space-y-2">
                    <template x-for="(payment,index) in payments" :key="payment.rowKey">
                        <div class="grid gap-2 sm:grid-cols-12">
                            <select :name="`payments[${index}][method]`" x-model="payment.method" class="rounded border px-3 py-2 text-sm sm:col-span-3"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option></select>
                            <input type="number" min="0" step="0.01" :name="`payments[${index}][amount]`" x-model.number="payment.amount" class="rounded border px-3 py-2 text-sm sm:col-span-3" placeholder="Amount received">
                            <input type="text" maxlength="80" :name="`payments[${index}][reference_no]`" x-model="payment.reference_no" class="rounded border px-3 py-2 text-sm sm:col-span-5" placeholder="Payment reference">
                            <button type="button" @click="payments.splice(index,1)" class="rounded px-3 py-2 text-rose-600 hover:bg-rose-50 sm:col-span-1" aria-label="Remove payment">Remove</button>
                        </div>
                    </template>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">Amount received</div><div class="mt-1 font-semibold text-slate-800" x-text="currency(paymentTotal())"></div></div>
                    <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">Balance due</div><div class="mt-1 font-semibold text-rose-700" x-text="currency(totals().balanceDue)"></div></div>
                    <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">Change return</div><div class="mt-1 font-semibold text-slate-800" x-text="currency(totals().changeReturn)"></div></div>
                </div>
            </div>
        </section>

        <section class="rounded-xl bg-slate-900 p-5 text-white">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-base font-semibold">Bill Calculation & GST Summary</h2><p class="mt-1 text-xs text-slate-400">Preview is calculated by the same server-side jewellery pricing and GST engine used to save the bill.</p></div>
                <div class="text-xs" role="status" x-text="previewLoading ? 'Updating calculation…' : (previewError || '')" :class="previewError ? 'text-rose-300' : 'text-slate-400'"></div>
            </div>
            <div class="grid gap-x-8 gap-y-2 text-xs sm:grid-cols-2 xl:grid-cols-4">
                <div class="flex justify-between gap-2"><span class="text-slate-300">Gross metal value</span><strong x-text="currency(totals().metalValue)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Total making charges</span><strong x-text="currency(preview?.making_total)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Total wastage value</span><strong x-text="currency(totals().wastageValue)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Stone / diamond value</span><strong x-text="currency(preview?.stone_total)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Other charges</span><strong x-text="currency(totals().otherCharges)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Item discounts</span><strong x-text="currency(totals().itemDiscounts)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Invoice discount</span><strong x-text="currency(preview?.discount_amount)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Taxable subtotal</span><strong x-text="currency(totals().taxableSubtotal)"></strong></div>
                <div class="flex justify-between gap-2" x-show="gstType === 'intra'"><span class="text-slate-300">CGST</span><strong x-text="currency(preview?.cgst_amount)"></strong></div>
                <div class="flex justify-between gap-2" x-show="gstType === 'intra'"><span class="text-slate-300">SGST</span><strong x-text="currency(preview?.sgst_amount)"></strong></div>
                <div class="flex justify-between gap-2" x-show="gstType === 'inter'"><span class="text-slate-300">IGST</span><strong x-text="currency(preview?.igst_amount)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Round-off</span><strong x-text="currency(preview?.round_off)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Old metal adjustment</span><strong x-text="currency(preview?.old_gold_deduction)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Advance adjustment</span><strong x-text="currency(preview?.advance_adjusted)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Scheme adjustment</span><strong x-text="currency(preview?.scheme_adjusted)"></strong></div>
                <div class="flex justify-between gap-2"><span class="text-slate-300">Other adjustment</span><strong x-text="currency(preview?.other_adjusted)"></strong></div>
            </div>
            <div class="mt-5 grid gap-3 border-t border-slate-700 pt-4 sm:grid-cols-3">
                <div class="sm:col-span-1"><div class="text-xs text-slate-400">Grand total</div><div class="mt-1 text-2xl font-bold text-amber-300" x-text="currency(preview?.grand_total)"></div></div>
                <div><div class="text-xs text-slate-400">Paid amount</div><div class="mt-1 text-lg font-semibold" x-text="currency(Math.min(paymentTotal(), Number(preview?.grand_total || 0)))"></div></div>
                <div><div class="text-xs text-slate-400">Balance due</div><div class="mt-1 text-lg font-semibold" x-text="currency(totals().balanceDue)"></div></div>
            </div>
            <div x-show="previewError" class="mt-3 rounded bg-rose-950 px-3 py-2 text-xs text-rose-200" x-text="previewError"></div>
        </section>

        <div id="pos-hidden-items"></div>
        <div class="flex flex-wrap justify-end gap-2 rounded-xl border border-slate-200 bg-white p-4">
            <button type="button" @click="cancelBill()" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel Bill</button>
            <button type="submit" name="submit_action" value="save" :disabled="cart.length === 0 || previewLoading" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 disabled:opacity-40">Save Bill</button>
            <button type="submit" name="submit_action" value="save_print" :disabled="cart.length === 0 || previewLoading" class="rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-40">Save and Print</button>
            <button type="submit" name="submit_action" value="generate" :disabled="cart.length === 0 || previewLoading" class="rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-40">Generate Invoice</button>
        </div>
    </form>

    <div x-show="customerModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @keydown.escape.window="customerModal = false">
        <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl" @click.outside="customerModal = false">
            <div class="flex items-start justify-between"><div><h2 class="font-semibold text-slate-900">Create customer</h2><p class="mt-1 text-xs text-slate-500">The new party will be added to the customer list and selected for this bill.</p></div><button type="button" @click="customerModal = false" class="text-xl text-slate-400">×</button></div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <input x-model="newCustomer.name" class="rounded-lg border px-3 py-2 text-sm sm:col-span-2" placeholder="Customer name">
                <input x-model="newCustomer.phone" class="rounded-lg border px-3 py-2 text-sm" placeholder="Mobile number">
                <input x-model="newCustomer.gstin" maxlength="15" class="rounded-lg border px-3 py-2 text-sm" placeholder="GSTIN (optional)">
                <input x-model="newCustomer.address" class="rounded-lg border px-3 py-2 text-sm sm:col-span-2" placeholder="Billing address">
            </div>
            <p x-show="customerError" class="mt-3 text-xs text-rose-600" x-text="customerError"></p>
            <div class="mt-5 flex justify-end gap-2"><button type="button" @click="customerModal = false" class="rounded-lg border px-4 py-2 text-sm">Cancel</button><button type="button" @click="createCustomer()" :disabled="customerSaving" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" x-text="customerSaving ? 'Saving…' : 'Create and select'"></button></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function jewelleryPos() {
    return {
        catalog: @json($availableItems),
        customers: @json($customers),
        rateCatalog: @json($metalRateCatalog),
        defaultGstRate: {{ (float) \App\Models\Setting::get('default_gst_rate', '3') }},
        branchId: '{{ $branchId }}',
        previewUrl: @json(route('pos.calculate')),
        customerUrl: @json(route('pos.customers.quick-store')),
        search: '',
        scanError: '',
        barcodeLookupUrl: '',
        cart: [],
        customerId: '',
        customer: { name: '', phone: '', gstin: '', address: '', state: '' },
        customerModal: false,
        customerSaving: false,
        customerError: '',
        newCustomer: { name: '', phone: '', gstin: '', address: '' },
        invoiceType: 'sale',
        orderReference: '',
        gstType: 'intra',
        activeTab: 'adjustments',
        discountAmount: 0,
        advanceAdjusted: 0,
        schemeAdjusted: 0,
        otherAdjusted: 0,
        payments: [{ rowKey: 1, method: 'cash', amount: 0, reference_no: '' }],
        nextRowKey: 2,
        preview: null,
        previewLoading: false,
        previewError: '',
        previewRequest: 0,
        previewTimer: null,
        oldGold: {
            enabled: false, description: '', metal_type: 'Gold', purity: '22K',
            purity_pct: 91.67, gross_weight: 0, stone_weight: 0, other_deductions: 0,
            deduction_pct: 2, melting_loss_pct: 1, acknowledgement: false
        },

        currency(value) {
            return '₹' + Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        money(value) {
            return Number(value || 0).toFixed(3);
        },
        filteredCatalog() {
            const query = this.search.toLowerCase().trim();
            return this.catalog.filter(item => String(item.branch_id) === String(this.branchId) &&
                (!query || [item.name, item.item_code, item.sku, item.barcode, item.huid]
                    .some(value => String(value || '').toLowerCase().includes(query))));
        },
        customerChanged() {
            const selected = this.customers.find(customer => String(customer.id) === String(this.customerId));
            this.customer = selected
                ? { name: selected.name, phone: selected.phone, gstin: selected.gstin || '', address: selected.address || '', state: selected.state || '' }
                : { name: '', phone: '', gstin: '', address: '', state: '' };
        },
        puritiesFor(metalType) {
            const purities = this.rateCatalog.filter(rate => rate.metal_type === metalType).map(rate => rate.purity);
            return [...new Set(purities)];
        },
        updateRate(row) {
            if (row.metal_type === 'Diamond') {
                row.purity = 'N/A';
                row.metal_rate = 0;
                return;
            }
            const rate = this.rateCatalog.find(entry => entry.metal_type === row.metal_type && entry.purity === row.purity);
            row.metal_rate = rate ? rate.rate : 0;
        },
        async addFirstMatch() {
            const query = this.search.trim();
            if (!query) return;
            const exact = this.catalog.find(item => String(item.branch_id) === String(this.branchId) &&
                [item.barcode, item.item_code, item.huid, item.sku]
                    .some(value => String(value || '').toLowerCase() === query.toLowerCase()));
            if (exact) {
                this.addItem(exact);
                this.search = '';
                return;
            }

            try {
                await this.lookupAndAdd(query);
            } catch (error) {
                if (error.status === 404) {
                    const match = this.filteredCatalog()[0];
                    if (match) {
                        this.addItem(match);
                        this.search = '';
                        return;
                    }
                }
                this.scanError = error.message || 'Barcode lookup failed. Please try again.';
            }
        },
        async lookupAndAdd(query) {
            const url = new URL(this.barcodeLookupUrl, window.location.origin);
            url.searchParams.set('code', query);
            url.searchParams.set('branch_id', this.branchId);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok) {
                const error = new Error(result.message || 'This jewellery item could not be added.');
                error.status = response.status;
                throw error;
            }
            this.addItem(result.item);
            this.search = '';
        },
        async scanIntoRow(row) {
            const code = String(row.barcode || '').trim();
            const scanned = this.catalog.find(item => String(item.branch_id) === String(this.branchId) &&
                [item.barcode, item.huid, item.item_code, item.sku].some(value =>
                    String(value || '').toLowerCase() === code.toLowerCase()));
            if (scanned) {
                this.addItem(scanned);
                this.search = '';
                return;
            }
            if (code) {
                try {
                    await this.lookupAndAdd(code);
                    row.barcode = '';
                } catch (error) {
                    this.scanError = error.message || 'Barcode lookup failed. Please try again.';
                }
            }
        },
        itemRow(item = {}) {
            return {
                rowKey: this.nextRowKey++,
                inventory_item_id: item.id || null,
                product_name: item.name || 'Custom Jewellery Item',
                sku: item.sku || item.item_code || '',
                barcode: item.barcode || '',
                huid: item.huid || '',
                hsn_code: item.hsn_code || '',
                metal_type: item.metal_type || 'Gold',
                purity: item.purity || (this.puritiesFor('Gold').includes('22K') ? '22K' : (this.puritiesFor('Gold')[0] || '')),
                quantity: 1,
                gross_weight: Number(item.gross_weight || 0),
                stone_weight: Number(item.stone_weight || 0),
                other_deductions: 0,
                metal_rate: Number(item.metal_rate || 0),
                making_charge_type: item.making_charge_type || 'per_gram',
                making_charge_rate: Number(item.making_charge_rate || 0),
                wastage_pct: Number(item.wastage_pct || 0),
                wastage_weight: '',
                stone_charge: Number(item.stone_charge || 0),
                diamond_charge: Number(item.diamond_charge || 0),
                hallmark_charge: Number(item.hallmark_charge || 0),
                other_charge: Number(item.other_charge || 0),
                discount: 0,
                gst_rate: Number(item.gst_rate ?? this.defaultGstRate),
                preview: null
            };
        },
        addItem(item) {
            if (this.cart.some(row => String(row.inventory_item_id) === String(item.id))) return;
            this.cart.push(this.itemRow(item));
            this.refreshPreview();
        },
        addCustomItem() {
            const row = this.itemRow();
            this.updateRate(row);
            this.cart.push(row);
            this.$nextTick(() => document.querySelector(`[data-row-key="${row.rowKey}"] input`)?.focus());
            this.refreshPreview();
        },
        removeItem(index) {
            this.cart.splice(index, 1);
            this.refreshPreview();
        },
        queuePreview() {
            clearTimeout(this.previewTimer);
            this.previewRequest++;
            this.previewLoading = this.cart.length > 0;
            this.previewTimer = setTimeout(() => this.refreshPreview(), 250);
        },
        async refreshPreview() {
            clearTimeout(this.previewTimer);
            if (this.cart.length === 0) {
                this.preview = null;
                this.previewError = '';
                this.previewLoading = false;
                return;
            }
            const requestId = ++this.previewRequest;
            this.previewLoading = true;
            this.previewError = '';
            const payload = {
                _token: document.querySelector('input[name="_token"]')?.value,
                branch_id: this.branchId,
                items: this.cart.map(({ rowKey, inventory_item_id, preview, ...item }) => item),
                gst_type: this.gstType,
                discount_amount: Number(this.discountAmount || 0),
                advance_adjusted: Number(this.advanceAdjusted || 0),
                scheme_adjusted: Number(this.schemeAdjusted || 0),
                other_adjusted: Number(this.otherAdjusted || 0),
                rounding_mode: 'nearest_rupee'
            };
            const oldMetalNetWeight = Number(this.oldGold.gross_weight || 0) -
                Number(this.oldGold.stone_weight || 0) - Number(this.oldGold.other_deductions || 0);
            if (this.oldGold.enabled && oldMetalNetWeight > 0 && this.oldGold.description && this.oldGold.purity &&
                Number(this.oldGold.purity_pct) > 0) {
                payload.old_gold = {
                    item_description: this.oldGold.description || 'Customer old metal',
                    metal_type: this.oldGold.metal_type,
                    purity: this.oldGold.purity || this.oldGold.metal_type,
                    purity_pct: Number(this.oldGold.purity_pct || 0),
                    gross_weight: Number(this.oldGold.gross_weight || 0),
                    stone_weight: Number(this.oldGold.stone_weight || 0),
                    other_deductions: Number(this.oldGold.other_deductions || 0),
                    deduction_pct: Number(this.oldGold.deduction_pct || 0),
                    melting_loss_pct: Number(this.oldGold.melting_loss_pct || 0)
                };
            }
            try {
                const response = await fetch(this.previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': payload._token
                    },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!response.ok) {
                    const message = Object.values(result.errors || {}).flat()[0] || result.message || 'Bill preview could not be calculated.';
                    throw new Error(message);
                }
                if (requestId !== this.previewRequest) return;
                this.preview = result;
                this.cart.forEach((row, index) => row.preview = result.items[index] || null);
            } catch (error) {
                if (requestId === this.previewRequest) {
                    this.previewError = error.message || 'Bill preview could not be calculated.';
                    this.preview = null;
                }
            } finally {
                if (requestId === this.previewRequest) this.previewLoading = false;
            }
        },
        totals() {
            const items = this.preview?.items || [];
            const sum = key => items.reduce((total, item) => total + Number(item[key] || 0), 0);
            const grandTotal = Number(this.preview?.grand_total || 0);
            const paid = this.paymentTotal();
            return {
                metalValue: sum('metal_value'),
                wastageValue: sum('wastage_value'),
                otherCharges: sum('hallmark_charge') + sum('other_charge'),
                itemDiscounts: sum('discount'),
                taxableSubtotal: items.reduce((total, item) => total + Number(item.taxable_value || 0), 0),
                balanceDue: Math.max(0, +(grandTotal - paid).toFixed(2)),
                changeReturn: Math.max(0, +(paid - grandTotal).toFixed(2))
            };
        },
        paymentTotal() {
            return this.payments.reduce((total, payment) => total + Math.max(0, Number(payment.amount || 0)), 0);
        },
        fillBalance() {
            if (this.payments.length === 0) this.payments.push({ rowKey: this.nextRowKey++, method: 'cash', amount: 0, reference_no: '' });
            const additional = this.payments.slice(1).reduce((total, payment) => total + Math.max(0, Number(payment.amount || 0)), 0);
            this.payments[0].amount = Math.max(0, +(Number(this.preview?.grand_total || 0) - additional).toFixed(2));
        },
        async createCustomer() {
            this.customerSaving = true;
            this.customerError = '';
            try {
                const response = await fetch(this.customerUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value
                    },
                    body: JSON.stringify({ ...this.newCustomer, _token: document.querySelector('input[name="_token"]')?.value })
                });
                const result = await response.json();
                if (!response.ok) {
                    this.customerError = Object.values(result.errors || {}).flat()[0] || result.message || 'Customer could not be created.';
                    return;
                }
                this.customers.push(result);
                this.customerId = String(result.id);
                this.customer = { name: result.name, phone: result.phone, gstin: result.gstin || '', address: result.address || '', state: '' };
                this.newCustomer = { name: '', phone: '', gstin: '', address: '' };
                this.customerModal = false;
            } catch (error) {
                this.customerError = error.message || 'Customer could not be created.';
            } finally {
                this.customerSaving = false;
            }
        },
        cancelBill() {
            if (this.cart.length === 0 || confirm('Cancel this bill and clear all unsaved entries?')) window.location.reload();
        },
        prepareSubmit(event) {
            if (this.cart.length === 0) {
                event.preventDefault();
                return;
            }
            if (this.previewLoading) {
                event.preventDefault();
                return;
            }
            if (this.oldGold.enabled && (!this.oldGold.acknowledgement || !this.oldGold.description || !this.oldGold.purity ||
                Number(this.oldGold.gross_weight) <= 0 ||
                Number(this.oldGold.gross_weight) <= Number(this.oldGold.stone_weight || 0) + Number(this.oldGold.other_deductions || 0))) {
                event.preventDefault();
                this.activeTab = 'adjustments';
                alert('Complete the old-metal details and record customer acknowledgement.');
                return;
            }
            const container = document.getElementById('pos-hidden-items');
            container.replaceChildren();
            this.cart.forEach((item, index) => {
                Object.entries(item).filter(([key]) => key !== 'rowKey' && key !== 'preview')
                    .forEach(([key, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `items[${index}][${key}]`;
                        input.value = value ?? '';
                        container.appendChild(input);
                    });
            });
            const oldMetalFields = {
                'old_gold[item_description]': this.oldGold.description,
                'old_gold[metal_type]': this.oldGold.metal_type,
                'old_gold[purity]': this.oldGold.purity,
                'old_gold[purity_pct]': this.oldGold.purity_pct,
                'old_gold[gross_weight]': this.oldGold.gross_weight,
                'old_gold[stone_weight]': this.oldGold.stone_weight,
                'old_gold[other_deductions]': this.oldGold.other_deductions,
                'old_gold[deduction_pct]': this.oldGold.deduction_pct,
                'old_gold[melting_loss_pct]': this.oldGold.melting_loss_pct,
                'old_gold[acknowledgement]': this.oldGold.acknowledgement ? '1' : ''
            };
            Object.entries(oldMetalFields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = this.oldGold.enabled ? value : '';
                if (!this.oldGold.enabled) input.disabled = true;
                container.appendChild(input);
            });
        }
    };
}
</script>
@endpush
