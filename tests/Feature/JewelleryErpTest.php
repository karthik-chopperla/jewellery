<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\JewelleryPriceCalculator;
use Database\Seeders\DemoAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JewelleryErpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_jewellery_price_calculator_matches_example_and_gst_rules(): void
    {
        $calc = new JewelleryPriceCalculator();

        // Example from todo.txt: 10g @ ₹10,000/g + ₹10,000 making + ₹5,000 stone = ₹1,15,000 + 3% GST = ₹1,18,450
        $res = $calc->calculateInvoice([[
            'gross_weight' => 10,
            'stone_weight' => 0,
            'metal_rate' => 10000,
            'making_charge_type' => 'fixed',
            'making_charge_rate' => 10000,
            'stone_charge' => 5000,
            'gst_rate' => 3,
        ]]);

        $this->assertEquals(115000.00, $res['subtotal']);
        $this->assertEquals(1725.00, $res['cgst_amount']);
        $this->assertEquals(1725.00, $res['sgst_amount']);
        $this->assertEquals(118450.00, $res['grand_total']);
    }

    public function test_price_engine_calculates_quantity_deductions_and_allocates_invoice_discount_by_tax_rate(): void
    {
        $calc = new JewelleryPriceCalculator();
        $result = $calc->calculateInvoice([
            [
                'quantity' => 3,
                'gross_weight' => 2,
                'stone_weight' => 0.1,
                'other_deductions' => 0.1,
                'metal_rate' => 1000,
                'wastage_pct' => 10,
                'making_charge_type' => 'per_gram',
                'making_charge_rate' => 100,
                'stone_charge' => 20,
                'discount' => 10,
                'gst_rate' => 3,
            ],
            [
                'quantity' => 1,
                'gross_weight' => 1,
                'metal_rate' => 500,
                'making_charge_type' => 'fixed',
                'making_charge_rate' => 0,
                'gst_rate' => 5,
            ],
        ], [
            'discount_amount' => 150,
            'gst_type' => 'intra',
            'rounding_mode' => 'none',
        ]);

        $this->assertSame(3, $result['items'][0]['quantity']);
        $this->assertEquals(1.8, $result['items'][0]['net_weight']);
        $this->assertEquals(6370.70, $result['items'][0]['taxable_value']);
        $this->assertEquals(0.18, $result['items'][0]['wastage_weight']);
        $this->assertEquals(191.12, $result['items'][0]['gst_amount']);
        $this->assertEquals(7010.00, $result['subtotal']);
        $this->assertEquals(215.59, $result['cgst_amount'] + $result['sgst_amount']);
        $this->assertEquals(0.00, $result['igst_amount']);
    }

    public function test_empty_manual_wastage_weight_uses_configured_wastage_percentage(): void
    {
        $item = (new JewelleryPriceCalculator())->calculateItem([
            'gross_weight' => 2,
            'metal_rate' => 1000,
            'making_charge_type' => 'fixed',
            'making_charge_rate' => 0,
            'wastage_pct' => 10,
            'wastage_weight' => '',
        ]);

        $this->assertEquals(0.2, $item['wastage_weight']);
        $this->assertEquals(200, $item['wastage_value']);
    }

    public function test_pos_price_preview_rejects_negative_item_discounts(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin)
            ->postJson(route('pos.calculate'), [
                'gst_type' => 'intra',
                'items' => [[
                    'gross_weight' => 1,
                    'making_charge_type' => 'fixed',
                    'making_charge_rate' => 0,
                    'discount' => -1,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.discount');
    }

    public function test_old_gold_valuation_calculator(): void
    {
        $calc = new JewelleryPriceCalculator();
        $res = $calc->calculateOldGold([
            'gross_weight' => 10.5,
            'stone_weight' => 0.5,
            'purity_pct' => 91.6,
            'deduction_pct' => 2.0,
            'melting_loss_pct' => 1.0,
            'applied_rate' => 8000,
        ]);

        $this->assertEquals(10.0, $res['net_weight']);
        $this->assertEquals(9.16, $res['fine_gold_weight']);
        $this->assertGreaterThan(70000, $res['valuation_amount']);
    }

    public function test_demo_admin_seeder_creates_and_updates_the_configured_password(): void
    {
        config(['demo.admin_password' => 'first-demo-password']);
        $this->seed(DemoAdminSeeder::class);

        config(['demo.admin_password' => 'updated-demo-password']);
        $this->seed(DemoAdminSeeder::class);

        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();

        $this->assertSame(1, User::where('email', 'admin@jewelerp.in')->count());
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('updated-demo-password', $admin->password));
    }

    public function test_login_page_uses_abharan_erp_brand(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Abharan ERP')
            ->assertDontSee('JewelERP');
    }

    public function test_all_modules_load_for_authenticated_admin(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        $routes = [
            '/',
            '/rates',
            '/products',
            '/inventory',
            '/inventory/labels',
            '/stones-hallmark',
            '/pos',
            '/invoices',
            '/customers',
            '/old-gold',
            '/purchases',
            '/workshop',
            '/schemes',
            '/finance',
            '/staff',
            '/branches',
            '/security',
            '/reports',
        ];

        foreach ($routes as $uri) {
            $response = $this->get($uri);
            $response->assertStatus(200, "Failed on route: {$uri}");
        }
    }

    public function test_pos_checkout_creates_invoice_and_marks_item_sold(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        $item = InventoryItem::with('product')->where('status', 'available')->firstOrFail();
        $customer = Customer::firstOrFail();
        $branch = Branch::firstOrFail();

        $response = $this->post(route('pos.store'), [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'invoice_type' => 'sale',
            'gst_type' => 'intra',
            'bill_date' => now()->toDateString(),
            'customer_state' => 'Maharashtra',
            'order_reference' => 'ORDER-TEST-01',
            'other_adjusted' => 25,
            'payment_method' => 'upi',
            'paid_amount' => 100000,
            'items' => [[
                'inventory_item_id' => $item->id,
                'product_name' => $item->product->name,
                'metal_type' => $item->product->metal_type,
                'purity' => $item->purity,
                'gross_weight' => $item->gross_weight,
                'stone_weight' => $item->stone_weight,
                'net_weight' => $item->net_weight,
                'metal_rate' => 7200,
                'making_charge_type' => 'per_gram',
                'making_charge_rate' => 600,
                'wastage_pct' => 2,
                'stone_charge' => 0,
                'diamond_charge' => 0,
                'hallmark_charge' => 45,
                'other_charge' => 0,
                'discount' => 0,
                'gst_rate' => 3,
            ]],
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals('sold', $item->fresh()->status);
        $invoice = \App\Models\Invoice::latest('id')->firstOrFail();
        $this->assertSame('Maharashtra', $invoice->customer_state);
        $this->assertSame('ORDER-TEST-01', $invoice->order_reference);
        $this->assertEquals(25, (float) $invoice->other_adjusted);
    }

    public function test_pos_checkout_persists_multiple_items_server_rates_gst_adjustments_and_split_payments(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);
        $customer = Customer::firstOrFail();
        $customer->update(['advance_balance' => 50]);

        $branch = Branch::firstOrFail();
        $stockItems = InventoryItem::with('product.category')
            ->where('branch_id', $branch->id)
            ->where('status', 'available')
            ->take(2)
            ->get();
        $this->assertCount(2, $stockItems);

        $items = $stockItems->map(fn (InventoryItem $item) => [
            'inventory_item_id' => $item->id,
            'product_name' => $item->product->name,
            'metal_type' => $item->product->metal_type,
            'purity' => $item->purity,
            'gross_weight' => $item->gross_weight,
            'stone_weight' => $item->stone_weight,
            'net_weight' => $item->net_weight,
            'metal_rate' => 1,
            'making_charge_type' => $item->product->making_charge_type,
            'making_charge_rate' => $item->product->making_charge_value,
            'wastage_pct' => $item->product->wastage_pct,
            'stone_charge' => $item->stone_charge,
            'diamond_charge' => $item->diamond_charge,
            'hallmark_charge' => $item->hallmark_charge,
            'other_charge' => $item->other_charge,
            'discount' => 10,
            'gst_rate' => $item->product->gst_rate,
            'sku' => $item->product->sku,
            'barcode' => $item->barcode,
            'huid' => $item->huid,
            'hsn_code' => $item->product->hsn_code,
        ])->all();

        $this->post(route('pos.store'), [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'invoice_type' => 'sale',
            'gst_type' => 'inter',
            'discount_amount' => 20,
            'advance_adjusted' => 50,
            'scheme_adjusted' => 25,
            'other_adjusted' => 10,
            'old_gold' => [
                'item_description' => 'Old gold test chain',
                'metal_type' => 'Gold',
                'purity' => '22K',
                'purity_pct' => 91.67,
                'gross_weight' => 0.1,
                'stone_weight' => 0,
                'other_deductions' => 0,
                'deduction_pct' => 0,
                'melting_loss_pct' => 0,
                'acknowledgement' => '1',
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 1000],
                ['method' => 'upi', 'amount' => 2500, 'reference_no' => 'POS-UPI-TEST'],
            ],
            'items' => $items,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $invoice = \App\Models\Invoice::latest('id')->firstOrFail();
        $invoice->load('items', 'payments', 'oldGoldPurchases');
        $this->assertCount(2, $invoice->items);
        $this->assertCount(2, $invoice->payments);
        $this->assertCount(1, $invoice->oldGoldPurchases);
        $this->assertGreaterThan(1, (float) $invoice->items->first()->metal_rate);
        $this->assertGreaterThan(0, (float) $invoice->igst_amount);
        $this->assertEquals(0, (float) $invoice->cgst_amount);
        $this->assertEquals(0, (float) $invoice->sgst_amount);
        $this->assertEquals(3500, (float) $invoice->amount_received);
        $this->assertEquals(50, (float) $invoice->advance_adjusted);
        $this->assertEquals(25, (float) $invoice->scheme_adjusted);
        $this->assertEquals(10, (float) $invoice->other_adjusted);
        $this->assertGreaterThan(0, (float) $invoice->old_gold_deduction);
        $this->assertEqualsWithDelta(
            (float) $invoice->items->sum('gst_amount'),
            (float) $invoice->igst_amount,
            0.01
        );
        $this->assertEquals('sold', $stockItems[0]->fresh()->status);
        $this->assertEquals('sold', $stockItems[1]->fresh()->status);
        $this->assertEquals(0, (float) $customer->fresh()->advance_balance);
        $this->assertDatabaseHas('customer_advance_transactions', [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'type' => 'applied',
            'amount' => 50,
        ]);
    }

    public function test_pos_quick_customer_creation_returns_the_customer_for_inline_selection(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin)
            ->postJson(route('pos.customers.quick-store'), [
                'name' => 'POS Walk-in Test',
                'phone' => '9000012345',
                'address' => 'Test billing address',
                'gstin' => '27AAACT1234A1Z5',
                'state' => 'Maharashtra',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'POS Walk-in Test')
            ->assertJsonPath('phone', '9000012345')
            ->assertJsonPath('state', 'Maharashtra')
            ->assertJsonPath('advance_balance', 0);

        $this->assertDatabaseHas('customers', [
            'name' => 'POS Walk-in Test',
            'phone' => '9000012345',
            'gstin' => '27AAACT1234A1Z5',
            'state' => 'Maharashtra',
        ]);
    }

    public function test_pos_exposes_structured_jewellery_entry_adjustment_and_payment_sections(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Bill Header')
            ->assertSee('Sale Items')
            ->assertSee('Customer Adjustments')
            ->assertSee('Payment Details')
            ->assertSee('Gross metal value')
            ->assertSee('Save and Print')
            ->assertSee('Generate Invoice')
            ->assertSee('+ Add Jewellery Item')
            ->assertSee('Billing steps')
            ->assertSee('Practice grand total')
            ->assertSeeInOrder(['Payment Details', 'Customer Adjustments (Optional)'])
            ->assertSee("activeTab: 'payments'", false)
            ->assertSee('name="gst_type"', false);
    }

    public function test_inventory_stock_entry_creates_unique_pieces_and_pos_uses_other_weight_deductions(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);
        $product = \App\Models\Product::firstOrFail();
        $branch = Branch::firstOrFail();

        $this->post(route('inventory.store'), [
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity' => 2,
            'gross_weight' => 3,
            'stone_weight' => 0.1,
            'other_weight_deductions' => 0.05,
            'diamond_weight' => 0.2,
            'purity' => $product->purity,
            'barcode_format' => 'code128',
            'stock_entry_date' => '2026-01-15',
            'purchase_reference' => 'PO-POS-001',
        ])->assertRedirect(route('inventory.index', ['product_id' => $product->id]));

        $pieces = InventoryItem::where('purchase_reference', 'PO-POS-001')->orderBy('id')->get();
        $this->assertCount(2, $pieces);
        $this->assertCount(2, $pieces->pluck('barcode')->unique());
        $this->assertCount(2, $pieces->pluck('qr_token')->unique());
        $this->assertEquals(2.85, (float) $pieces[0]->net_weight);
        $this->assertEquals('2026-01-15', $pieces[0]->stock_entry_date->toDateString());
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $pieces[0]->id,
            'reference' => 'PO-POS-001',
        ]);

        $item = $pieces[0]->load('product.category');
        $this->post(route('pos.store'), [
            'branch_id' => $branch->id,
            'invoice_type' => 'sale',
            'gst_type' => 'intra',
            'items' => [[
                'inventory_item_id' => $item->id,
                'product_name' => $item->product->name,
                'metal_type' => $item->product->metal_type,
                'purity' => $item->purity,
                'gross_weight' => $item->gross_weight,
                'stone_weight' => $item->stone_weight,
                'other_deductions' => $item->other_weight_deductions,
                'making_charge_type' => $item->product->making_charge_type,
                'making_charge_rate' => $item->product->making_charge_value,
            ]],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $invoiceItem = \App\Models\InvoiceItem::latest('id')->firstOrFail();
        $this->assertEquals(0.05, (float) $invoiceItem->other_deductions);
        $this->assertEquals(0.2, (float) $invoiceItem->diamond_weight);
        $this->assertEquals(2.85, (float) $invoiceItem->net_weight);
        $this->assertSame('sold', $item->fresh()->status);
    }

    public function test_customer_advance_receipts_are_ledgered_and_cannot_exceed_available_balance(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);
        $customer = Customer::firstOrFail();

        $this->post(route('crm.advance', $customer), [
            'amount' => 1000,
            'payment_method' => 'upi',
            'reference_no' => 'ADV-UPI-01',
            'description' => 'Wedding advance',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertEquals(1000, (float) $customer->fresh()->advance_balance);
        $this->assertDatabaseHas('customer_advance_transactions', [
            'customer_id' => $customer->id,
            'type' => 'received',
            'amount' => 1000,
            'balance_after' => 1000,
            'reference_no' => 'ADV-UPI-01',
        ]);

        $branch = Branch::firstOrFail();
        $item = InventoryItem::with('product.category')
            ->where('branch_id', $branch->id)
            ->where('status', 'available')
            ->firstOrFail();
        $this->from(route('pos.index'))->post(route('pos.store'), [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'invoice_type' => 'sale',
            'gst_type' => 'intra',
            'advance_adjusted' => 1500,
            'items' => [[
                'inventory_item_id' => $item->id,
                'product_name' => $item->product->name,
                'metal_type' => $item->product->metal_type,
                'purity' => $item->purity,
                'gross_weight' => $item->gross_weight,
                'stone_weight' => $item->stone_weight,
                'making_charge_type' => $item->product->making_charge_type,
                'making_charge_rate' => $item->product->making_charge_value,
            ]],
        ])->assertRedirect(route('pos.index'))->assertSessionHasErrors('advance_adjusted');

        $this->assertSame('available', $item->fresh()->status);
        $this->assertEquals(1000, (float) $customer->fresh()->advance_balance);
    }

    public function test_partial_payment_is_not_saved_as_fully_paid(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);
        $branch = Branch::firstOrFail();

        $this->post(route('pos.store'), [
            'branch_id' => $branch->id,
            'invoice_type' => 'sale',
            'gst_type' => 'intra',
            'payments' => [['method' => 'cash', 'amount' => 1]],
            'items' => [[
                'product_name' => 'Manual Gold Ring',
                'metal_type' => 'Gold',
                'purity' => '22K',
                'gross_weight' => 1,
                'making_charge_type' => 'fixed',
                'making_charge_rate' => 0,
                'gst_rate' => 3,
            ]],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $invoice = \App\Models\Invoice::latest('id')->firstOrFail();
        $this->assertSame('partial', $invoice->status);
        $this->assertEquals(1, (float) $invoice->paid_amount);
        $this->assertGreaterThan(0, (float) $invoice->balance_due);
    }

    public function test_pos_preview_uses_server_calculator_for_adjustments_and_tax_totals(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin)
            ->postJson(route('pos.calculate'), [
                'branch_id' => Branch::firstOrFail()->id,
                'gst_type' => 'intra',
                'rounding_mode' => 'none',
                'other_adjusted' => 10,
                'items' => [[
                    'gross_weight' => 1,
                    'metal_rate' => 1000,
                    'making_charge_type' => 'fixed',
                    'making_charge_rate' => 0,
                    'gst_rate' => 3,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('other_adjusted', 10)
            ->assertJsonPath('cgst_amount', 15)
            ->assertJsonPath('sgst_amount', 15)
            ->assertJsonPath('grand_total', 1020);
    }

    public function test_guests_cannot_access_any_protected_module(): void
    {
        $routes = [
            '/', '/rates', '/products', '/inventory', '/inventory/labels',
            '/stones-hallmark', '/pos', '/invoices', '/customers', '/old-gold',
            '/purchases', '/workshop', '/schemes', '/finance', '/staff',
            '/branches', '/security', '/reports',
        ];

        foreach ($routes as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }

        $this->post('/pos/checkout', [])->assertRedirect('/login');
        $this->post('/quick-login', ['email' => 'admin@jewelerp.in'])->assertStatus(404);
    }

    public function test_login_requires_valid_password_and_active_account(): void
    {
        $this->post('/login', [
            'email' => 'admin@jewelerp.in',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $admin->update(['is_active' => false]);

        $this->post('/login', [
            'email' => 'admin@jewelerp.in',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin->update(['is_active' => true]);

        $this->post('/login', [
            'email' => 'admin@jewelerp.in',
            'password' => 'password',
        ])->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_rates_sync_endpoint(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->post(route('rates.sync'));
        $response->assertRedirect();
    }

    public function test_rate_deletion(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        $rate = \App\Models\MetalRate::firstOrFail();
        $rateId = $rate->id;

        $response = $this->delete(route('rates.destroy', $rate));
        $response->assertRedirect();
        $this->assertDatabaseMissing('metal_rates', ['id' => $rateId]);
    }

    public function test_product_deletion(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        $product = \App\Models\Product::firstOrFail();
        $prodId = $product->id;

        $response = $this->delete(route('products.destroy', $product));
        $response->assertRedirect();
        $this->assertDatabaseMissing('products', ['id' => $prodId]);
    }

    public function test_inventory_item_deletion(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        $item = \App\Models\InventoryItem::firstOrFail();
        $itemId = $item->id;

        $response = $this->delete(route('inventory.destroy', $item));
        $response->assertRedirect();
        $this->assertDatabaseMissing('inventory_items', ['id' => $itemId]);
    }

    public function test_inventory_item_creation_with_empty_optional_fields(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        $product = \App\Models\Product::firstOrFail();
        $branch = \App\Models\Branch::firstOrFail();

        $response = $this->post(route('inventory.store'), [
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'gross_weight' => 222,
            'purity' => '22K',
            'purchase_cost' => null,
            'stone_weight' => null,
            'diamond_weight' => null,
            'huid' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_items', [
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'gross_weight' => 222,
            'purchase_cost' => 0,
            'status' => 'available',
        ]);
    }

    public function test_product_master_can_continue_directly_to_piece_registration(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);
        $category = \App\Models\Category::firstOrFail();

        $this->post(route('products.store'), [
            'sku' => 'TEST-POS-PRODUCT-01',
            'name' => 'POS Workflow Gold Necklace',
            'category_id' => $category->id,
            'product_type' => 'Necklace',
            'metal_type' => 'Gold',
            'purity' => '22K',
            'making_charge_type' => 'per_gram',
            'making_charge_value' => 500,
            'wastage_pct' => 2,
            'hsn_code' => '7113',
            'next_step' => 'inventory',
        ])->assertRedirect(route('inventory.index', [
            'product_id' => \App\Models\Product::where('sku', 'TEST-POS-PRODUCT-01')->value('id'),
        ]))->assertSessionHas('success');

        $product = \App\Models\Product::where('sku', 'TEST-POS-PRODUCT-01')->firstOrFail();
        $this->assertSame('POS Workflow Gold Necklace', $product->name);
        $this->get(route('inventory.index', ['product_id' => $product->id]))
            ->assertOk()
            ->assertSee('Add Jewellery Stock')
            ->assertSee('Register each physical piece', false);
    }

    public function test_settings_page_and_business_name_update(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        $getRes = $this->get('/settings');
        $getRes->assertStatus(200);

        $postRes = $this->post('/settings', [
            'business_name' => 'Royal Heritage Jewellers',
            'tagline' => 'Handcrafted Elegance Since 1985',
            'phone' => '+91 98765 43210',
            'gstin' => '27AABCR9999Z1Z5',
            'address' => '45 Jewellers Lane, Mumbai',
        ]);

        $postRes->assertRedirect();
        $this->assertEquals('Royal Heritage Jewellers', \App\Models\Setting::get('business_name'));

        $dashRes = $this->get('/');
        $dashRes->assertSee('Royal Heritage Jewellers');
    }

    public function test_role_and_module_permissions_restrict_access(): void
    {
        $sales = User::where('role', 'salesperson')->firstOrFail();
        $this->assertTrue($sales->hasRole('salesperson'));
        $this->assertFalse($sales->hasRole('admin'));
        $this->assertFalse($sales->hasRole(['admin', 'manager']));
        $this->assertTrue($sales->hasPermission('pos'));
        $this->assertFalse($sales->hasPermission('reports'));
        $unknownRole = User::make(['role' => 'unrecognized']);
        $this->assertFalse($unknownRole->hasRole('admin'));
        $this->assertFalse($unknownRole->hasPermission('pos'));

        $this->actingAs($sales);
        $this->get('/pos')->assertOk();
        $this->get('/security')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->get('/rates')->assertForbidden();

        $manager = User::where('role', 'manager')->firstOrFail();
        $this->actingAs($manager);
        $this->get('/rates')->assertOk();
        $this->get('/staff')->assertOk();
        $this->get('/finance')->assertForbidden();
        $this->post('/staff', [])->assertForbidden();

        $artisan = User::where('role', 'artisan')->firstOrFail();
        $this->actingAs($artisan);
        $this->get('/workshop')
            ->assertOk()
            ->assertDontSee('href="'.route('dashboard').'"')
            ->assertDontSee('Delete manufacturing order')
            ->assertDontSee('Delete repair job');
        $this->get('/')->assertForbidden();
        $this->post('/custom-orders', [])->assertForbidden();
    }

    public function test_salesperson_login_redirects_to_an_allowed_module(): void
    {
        $this->post(route('login.post'), [
            'email' => 'ananya@jewelerp.in',
            'password' => 'password',
        ])->assertRedirect(route('pos.index'));
    }

    public function test_destructive_pos_and_workshop_actions_are_admin_only(): void
    {
        $invoice = \App\Models\Invoice::firstOrFail();
        $return = \App\Models\SalesReturn::create([
            'return_no' => 'RET-ROLE-TEST',
            'invoice_id' => $invoice->id,
            'branch_id' => $invoice->branch_id,
            'return_type' => 'refund',
            'reason' => 'Permission test',
        ]);

        $manager = User::where('role', 'manager')->firstOrFail();
        $this->actingAs($manager);
        $this->get('/pos')->assertOk();
        $this->get('/invoices')->assertOk()->assertDontSee('Cancel')->assertDontSee('Delete invoice');
        $this->post(route('pos.cancel', $invoice))->assertForbidden();
        $this->delete(route('pos.invoices.destroy', $invoice))->assertForbidden();
        $this->delete(route('pos.returns.destroy', $return))->assertForbidden();

        $salesperson = User::where('role', 'salesperson')->firstOrFail();
        $this->actingAs($salesperson);
        $this->delete(route('workshop.custom.destroy', \App\Models\CustomOrder::firstOrFail()))->assertForbidden();

        $artisan = User::where('role', 'artisan')->firstOrFail();
        $this->actingAs($artisan);
        $this->delete(route('workshop.mfg.destroy', \App\Models\ManufacturingOrder::firstOrFail()))->assertForbidden();
        $this->delete(route('workshop.repair.destroy', \App\Models\RepairJob::firstOrFail()))->assertForbidden();
    }

    public function test_delete_operations_across_all_modules(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        // Delete Customer
        $customer = Customer::create([
            'customer_code' => 'TEST-CUST',
            'name' => 'Delete Me Customer',
            'phone' => '9999900000',
            'group_tier' => 'standard',
        ]);
        $this->delete(route('crm.customers.destroy', $customer))->assertRedirect();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);

        // Delete Old Gold Purchase
        $branch = Branch::firstOrFail();
        $og = \App\Models\OldGoldPurchase::create([
            'receipt_no' => 'OG-DEL-1',
            'branch_id' => $branch->id,
            'item_description' => 'Scrap Gold Ring',
            'gross_weight' => 5.0,
            'net_weight' => 5.0,
            'tested_purity' => '22K',
            'purity_pct' => 91.6,
            'fine_gold_weight' => 4.58,
            'applied_rate' => 7500,
            'valuation_amount' => 34350,
            'settlement_type' => 'cash',
            'customer_declaration' => true,
            'status' => 'pending_approval',
        ]);
        $this->delete(route('old_gold.destroy', $og))->assertRedirect();
        $this->assertDatabaseMissing('old_gold_purchases', ['id' => $og->id]);

        // Delete Gold Scheme
        $scheme = \App\Models\GoldScheme::create([
            'scheme_no' => 'SCH-DEL-1',
            'branch_id' => $branch->id,
            'customer_id' => Customer::first()->id,
            'scheme_name' => 'Test Scheme',
            'monthly_amount' => 1000,
            'duration_months' => 6,
            'bonus_amount' => 1000,
            'total_paid' => 0,
            'start_date' => now(),
            'maturity_date' => now()->addMonths(6),
            'status' => 'active',
        ]);
        $this->delete(route('schemes.destroy', $scheme))->assertRedirect();
        $this->assertDatabaseMissing('gold_schemes', ['id' => $scheme->id]);

        // Delete Diamond Stone
        $stone = \App\Models\DiamondStone::create([
            'stone_code' => 'STN-TEST-1',
            'stone_type' => 'Diamond',
            'carat_weight' => 0.5,
            'cost_price' => 20000,
            'selling_price' => 30000,
            'status' => 'available',
        ]);
        $this->delete(route('stones.destroy', $stone))->assertRedirect();
        $this->assertDatabaseMissing('diamond_stones', ['id' => $stone->id]);

        // Delete Hallmark Record
        $item = InventoryItem::first();
        $hallmark = \App\Models\HallmarkRecord::create([
            'inventory_item_id' => $item->id,
            'center_name' => 'Test Center',
            'dispatch_date' => now(),
            'status' => 'dispatched',
        ]);
        $this->delete(route('hallmark.destroy', $hallmark))->assertRedirect();
        $this->assertDatabaseMissing('hallmark_records', ['id' => $hallmark->id]);

        // Delete Supplier and Purchase Invoice
        $supplier = \App\Models\Supplier::create([
            'name' => 'Delete Me Supplier',
            'code' => 'SUP-TEST-1',
            'category' => 'gold_supplier',
        ]);
        $pi = \App\Models\PurchaseInvoice::create([
            'bill_no' => 'PUR-TEST-1',
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'bill_date' => now(),
            'metal_type' => 'Gold',
            'purity' => '22K',
            'metal_weight' => 50,
            'total_amount' => 350000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
        $this->delete(route('purchases.destroy', $pi))->assertRedirect();
        $this->assertDatabaseMissing('purchase_invoices', ['id' => $pi->id]);
        $this->delete(route('suppliers.destroy', $supplier))->assertRedirect();
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);

        // Delete Workshop Orders
        $mfg = \App\Models\ManufacturingOrder::create([
            'job_no' => 'MFG-TEST-1',
            'branch_id' => $branch->id,
            'design_name' => 'Kada',
            'metal_type' => 'Gold',
            'purity' => '22K',
            'issued_weight' => 25.0,
            'stage' => 'metal_issued',
            'status' => 'in_progress',
        ]);
        $this->delete(route('workshop.mfg.destroy', $mfg))->assertRedirect();
        $this->assertDatabaseMissing('manufacturing_orders', ['id' => $mfg->id]);

        $custom = \App\Models\CustomOrder::create([
            'order_no' => 'ORD-TEST-1',
            'branch_id' => $branch->id,
            'customer_id' => Customer::first()->id,
            'title' => 'Custom Necklace',
            'metal_type' => 'Gold',
            'purity' => '22K',
            'estimated_weight' => 30.0,
            'estimated_amount' => 240000,
            'status' => 'in_production',
        ]);
        $this->delete(route('workshop.custom.destroy', $custom))->assertRedirect();
        $this->assertDatabaseMissing('custom_orders', ['id' => $custom->id]);

        $repair = \App\Models\RepairJob::create([
            'job_no' => 'REP-TEST-1',
            'branch_id' => $branch->id,
            'customer_id' => Customer::first()->id,
            'item_description' => 'Chain Soldering',
            'receiving_weight' => 12.0,
            'repair_charges' => 500,
            'status' => 'received',
        ]);
        $this->delete(route('workshop.repair.destroy', $repair))->assertRedirect();
        $this->assertDatabaseMissing('repair_jobs', ['id' => $repair->id]);

        // Delete Staff
        $staff = User::create([
            'branch_id' => $branch->id,
            'name' => 'Temporary Staff',
            'email' => 'tempstaff@test.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'role' => 'salesperson',
            'employee_code' => 'EMP-999',
        ]);
        $this->delete(route('admin.staff.destroy', $staff))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_live_metal_rates_sync_domestic_rates(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->post(route('rates.sync'), ['city' => 'guwahati']);
        $response->assertRedirect();

        $gold24 = \App\Models\MetalRate::latestRate('Gold', '24K');
        $this->assertGreaterThan(12000, $gold24);

        $silver = \App\Models\MetalRate::latestRate('Silver', '999');
        $this->assertGreaterThan(150, $silver);
    }

    public function test_crm_birthday_and_anniversary_campaign_timing_windows(): void
    {
        $admin = User::where('email', 'admin@jewelerp.in')->firstOrFail();
        $this->actingAs($admin);

        // Create a customer with birthday in 3 days and anniversary in 20 days
        $cust = Customer::create([
            'customer_code' => 'CUST-BDAY',
            'name' => 'Kavita Verma',
            'phone' => '9811122334',
            'dob' => now()->addDays(3)->subYears(28),
            'anniversary' => now()->addDays(20)->subYears(4),
            'group_tier' => 'vip',
        ]);

        // Test CRM page filters: 7 days window
        $res = $this->get('/customers?window=7days');
        $res->assertStatus(200);
        $res->assertSee('Kavita Verma');

        // Test Launching Campaign targeted to Birthday in Next 7 Days
        $campRes = $this->post(route('crm.campaigns.store'), [
            'title' => 'Special 7-Day Birthday Blast',
            'channel' => 'whatsapp',
            'occasion' => 'birthday',
            'target_group' => 'birthday_7days',
            'message' => 'Happy Birthday from our showroom!',
        ]);
        $campRes->assertRedirect();
        $this->assertDatabaseHas('marketing_campaigns', [
            'title' => 'Special 7-Day Birthday Blast',
            'target_group' => 'birthday_7days',
            'channel' => 'whatsapp',
        ]);

        // Test Direct Greeting
        $directRes = $this->post(route('crm.campaigns.direct'), [
            'customer_id' => $cust->id,
            'occasion' => 'birthday',
            'channel' => 'whatsapp',
            'message' => 'Happy Birthday Kavita!',
        ]);
        $directRes->assertRedirect();
    }
}
