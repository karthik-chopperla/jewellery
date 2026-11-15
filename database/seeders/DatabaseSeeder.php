<?php

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\CustomOrder;
use App\Models\DiamondRateConfig;
use App\Models\DiamondStone;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\GoldScheme;
use App\Models\HallmarkRecord;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\ManufacturingOrder;
use App\Models\MarketingCampaign;
use App\Models\MetalRate;
use App\Models\OldGoldPurchase;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\RepairJob;
use App\Models\RolePermission;
use App\Models\SchemeInstallment;
use App\Models\StockMovement;
use App\Models\Subcategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Branches & Counters
        $hq = Branch::create([
            'name' => 'Zaveri Bazaar Flagship',
            'code' => 'MUM-ZB',
            'phone' => '+91 22 2345 6789',
            'email' => 'zb@jewelerp.in',
            'address' => '142 Sheikh Memon Street, Zaveri Bazaar',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'gstin' => '27AABCS1429B1Z5',
            'is_head_office' => true,
        ]);

        $bandra = Branch::create([
            'name' => 'Bandra West Boutique',
            'code' => 'MUM-BW',
            'phone' => '+91 22 2640 8899',
            'email' => 'bandra@jewelerp.in',
            'address' => 'Turner Road, Bandra West',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'gstin' => '27AABCS1429B1Z5',
            'is_head_office' => false,
        ]);

        $cGold = Counter::create(['branch_id' => $hq->id, 'name' => 'Gold Bridal Counter', 'code' => 'ZB-G1']);
        $cDia = Counter::create(['branch_id' => $hq->id, 'name' => 'Solitaire Lounge', 'code' => 'ZB-D1']);
        $cSilver = Counter::create(['branch_id' => $hq->id, 'name' => 'Silver & Coins', 'code' => 'ZB-S1']);
        $cBandra = Counter::create(['branch_id' => $bandra->id, 'name' => 'Boutique Main', 'code' => 'BW-M1']);

        // 2. Users & Roles
        $admin = User::create([
            'branch_id' => $hq->id,
            'name' => 'Rajesh Zaveri',
            'email' => 'admin@jewelerp.in',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '9820011223',
            'employee_code' => 'EMP-001',
            'salary' => 120000,
            'mfa_enabled' => true,
        ]);

        $manager = User::create([
            'branch_id' => $hq->id,
            'name' => 'MeeraDeshmukh',
            'email' => 'manager@jewelerp.in',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'phone' => '9820011224',
            'employee_code' => 'EMP-002',
            'salary' => 75000,
        ]);

        $cashier = User::create([
            'branch_id' => $hq->id,
            'name' => 'Suresh Kulkarni',
            'email' => 'cashier@jewelerp.in',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'phone' => '9820011225',
            'employee_code' => 'EMP-003',
            'salary' => 38000,
        ]);

        $sales1 = User::create([
            'branch_id' => $hq->id,
            'name' => 'Ananya Sharma',
            'email' => 'ananya@jewelerp.in',
            'password' => Hash::make('password'),
            'role' => 'salesperson',
            'phone' => '9820011226',
            'employee_code' => 'EMP-004',
            'commission_rate' => 0.75,
            'monthly_target' => 2500000,
            'salary' => 32000,
        ]);

        $artisan = User::create([
            'branch_id' => $hq->id,
            'name' => 'Karigar Rahim Bhai',
            'email' => 'rahim@jewelerp.in',
            'password' => Hash::make('password'),
            'role' => 'artisan',
            'phone' => '9820011227',
            'employee_code' => 'EMP-005',
            'salary' => 45000,
        ]);

        User::create([
            'branch_id' => $bandra->id,
            'name' => 'Vikram Kapoor',
            'email' => 'vikram@jewelerp.in',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'phone' => '9820011228',
            'employee_code' => 'EMP-006',
            'salary' => 70000,
        ]);

        // Role Permissions
        foreach ([
            'admin' => ['*'],
            'manager' => ['pos', 'inventory', 'rates', 'approvals', 'customers', 'reports', 'staff'],
            'cashier' => ['pos', 'customers', 'schemes', 'repairs'],
            'salesperson' => ['pos', 'customers', 'custom_orders'],
            'artisan' => ['manufacturing', 'repairs'],
            'accountant' => ['accounts', 'reports', 'purchases'],
        ] as $role => $perms) {
            RolePermission::create(['role' => $role, 'permissions' => $perms]);
        }

        // 3. Metal Rates (7-day history + today)
        $baseGold24 = 7850;
        for ($d = 6; $d >= 0; $d--) {
            $delta = (6 - $d) * 25 - 75;
            $g24 = $baseGold24 + $delta;
            $eff = now()->subDays($d)->setTime(9, 30);
            foreach ([
                ['Gold', '24K', $g24],
                ['Gold', '22K', round($g24 * 0.9167, 2)],
                ['Gold', '20K', round($g24 * 0.8333, 2)],
                ['Gold', '18K', round($g24 * 0.75, 2)],
                ['Gold', '14K', round($g24 * 0.5833, 2)],
                ['Silver', '999', 95 + ($d % 3)],
                ['Platinum', '950', 3150],
            ] as [$m, $p, $r]) {
                MetalRate::create([
                    'metal_type' => $m,
                    'purity' => $p,
                    'rate_per_gram' => $r,
                    'effective_at' => $eff,
                    'status' => 'approved',
                    'created_by' => $admin->id,
                    'approved_by' => $admin->id,
                ]);
            }
        }

        DiamondRateConfig::create(['shape' => 'Round', 'cut' => 'Ideal', 'color' => 'EF', 'clarity' => 'VVS1', 'rate_per_carat' => 85000]);
        DiamondRateConfig::create(['shape' => 'Princess', 'cut' => 'Excellent', 'color' => 'GH', 'clarity' => 'VS1', 'rate_per_carat' => 65000]);

        // 4. Categories & Subcategories
        $catRing = Category::create(['name' => 'Rings', 'code' => 'RNG', 'default_making_charge_type' => 'per_gram', 'default_making_charge_value' => 650, 'default_wastage_pct' => 2.0, 'gst_rate' => 3.0]);
        $catNeck = Category::create(['name' => 'Necklaces', 'code' => 'NCK', 'default_making_charge_type' => 'percentage', 'default_making_charge_value' => 12.0, 'default_wastage_pct' => 3.0, 'gst_rate' => 3.0]);
        $catBang = Category::create(['name' => 'Bangles', 'code' => 'BNG', 'default_making_charge_type' => 'per_gram', 'default_making_charge_value' => 550, 'default_wastage_pct' => 2.5, 'gst_rate' => 3.0]);
        $catEar  = Category::create(['name' => 'Earrings', 'code' => 'EAR', 'default_making_charge_type' => 'per_gram', 'default_making_charge_value' => 700, 'default_wastage_pct' => 2.0, 'gst_rate' => 3.0]);
        $catCoin = Category::create(['name' => 'Coins & Bars', 'code' => 'COIN', 'default_making_charge_type' => 'fixed', 'default_making_charge_value' => 250, 'default_wastage_pct' => 0, 'gst_rate' => 3.0]);

        Subcategory::create(['category_id' => $catRing->id, 'name' => 'Solitaire Rings']);
        Subcategory::create(['category_id' => $catNeck->id, 'name' => 'Bridal Haar']);
        Subcategory::create(['category_id' => $catBang->id, 'name' => 'Kadas']);

        // 5. Suppliers
        $sup1 = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Shree Ganesh Bullion & Jewels',
            'phone' => '022-40012233',
            'email' => 'orders@ganeshbullion.in',
            'gstin' => '27AAACG1234F1Z2',
            'address' => 'Kalbadevi, Mumbai',
            'category' => 'gold_supplier',
            'credit_days' => 30,
            'credit_limit' => 5000000,
        ]);

        $sup2 = Supplier::create([
            'code' => 'SUP-002',
            'name' => 'Surat Solitaires Pvt Ltd',
            'phone' => '0261-2554433',
            'email' => 'sales@suratsolitaires.in',
            'gstin' => '24AAACS5678G1Z9',
            'address' => 'Varachha Diamond Market, Surat',
            'category' => 'diamond_supplier',
            'credit_days' => 45,
            'credit_limit' => 3000000,
        ]);

        // 6. Products & Individual Inventory Items
        $productsData = [
            ['SKU-RNG-001', 'Maharani Kundan Gold Ring', $catRing->id, 'Ring', 'Gold', '22K', 'per_gram', 650, 2.0, $sup1->id, 12.450, 1.200, 0, 'HUID882101', 1500],
            ['SKU-NCK-001', 'Royal Temple Bridal Necklace', $catNeck->id, 'Necklace', 'Gold', '22K', 'percentage', 12.0, 3.0, $sup1->id, 48.800, 3.500, 0, 'HUID882102', 4500],
            ['SKU-BNG-001', 'Patla Traditional Gold Bangles (Pair)', $catBang->id, 'Bangles', 'Gold', '22K', 'per_gram', 550, 2.0, $sup1->id, 32.100, 0, 0, 'HUID882103', 0],
            ['SKU-RNG-002', 'Forever Solitaire Diamond Ring', $catRing->id, 'Ring', 'Gold', '18K', 'fixed', 4500, 1.0, $sup2->id, 5.200, 0.150, 0.750, 'HUID882104', 0],
            ['SKU-EAR-001', 'Jhumka Heritage Gold Earrings', $catEar->id, 'Earrings', 'Gold', '22K', 'per_gram', 700, 2.5, $sup1->id, 14.600, 0.400, 0, 'HUID882105', 800],
            ['SKU-COIN-001', 'Lakshmi 24K Gold Coin 10g', $catCoin->id, 'Coins', 'Gold', '24K', 'fixed', 300, 0, $sup1->id, 10.000, 0, 0, 'HUID882106', 0],
            ['SKU-SLV-001', 'Pure Silver Pooja Thali Set', $catCoin->id, 'Silverware', 'Silver', '999', 'per_gram', 35, 0, $sup1->id, 250.000, 0, 0, null, 0],
        ];

        $items = [];
        foreach ($productsData as $idx => [$sku, $name, $catId, $type, $metal, $purity, $mcType, $mcVal, $wastage, $supId, $gross, $stoneWt, $diaWt, $huid, $stoneChg]) {
            $prod = Product::create([
                'sku' => $sku,
                'name' => $name,
                'description' => "Crafted {$purity} {$metal} {$type}",
                'category_id' => $catId,
                'product_type' => $type,
                'metal_type' => $metal,
                'purity' => $purity,
                'making_charge_type' => $mcType,
                'making_charge_value' => $mcVal,
                'wastage_pct' => $wastage,
                'gst_rate' => 3.0,
                'supplier_id' => $supId,
            ]);

            $net = round($gross - $stoneWt, 3);
            $item = InventoryItem::create([
                'item_code' => 'JW-' . str_pad($idx + 1, 5, '0', STR_PAD_LEFT),
                'barcode' => '8901000' . str_pad($idx + 1, 5, '0', STR_PAD_LEFT),
                'product_id' => $prod->id,
                'branch_id' => $idx === 4 ? $bandra->id : $hq->id,
                'counter_id' => $metal === 'Silver' ? $cSilver->id : ($diaWt > 0 ? $cDia->id : ($idx === 4 ? $cBandra->id : $cGold->id)),
                'supplier_id' => $supId,
                'gross_weight' => $gross,
                'stone_weight' => $stoneWt,
                'diamond_weight' => $diaWt,
                'net_weight' => $net,
                'purity' => $purity,
                'huid' => $huid,
                'hallmark_center' => $huid ? 'BIS Mumbai Assaying Center' : null,
                'hallmark_date' => $huid ? now()->subDays(10) : null,
                'hallmark_charge' => $huid ? 45 : 0,
                'purchase_cost' => round($net * 6800, 2),
                'stone_charge' => $stoneChg,
                'diamond_charge' => $diaWt > 0 ? round($diaWt * 75000, 2) : 0,
                'status' => 'available',
            ]);

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'branch_id' => $item->branch_id,
                'type' => 'stock_in',
                'reference' => 'OPENING-STOCK',
                'notes' => 'Initial showroom stock registration',
                'user_id' => $admin->id,
            ]);

            if ($huid) {
                HallmarkRecord::create([
                    'inventory_item_id' => $item->id,
                    'center_name' => 'BIS Mumbai Assaying Center',
                    'dispatch_date' => now()->subDays(14),
                    'receipt_date' => now()->subDays(10),
                    'huid' => $huid,
                    'purity_verified' => $purity,
                    'charges' => 45,
                    'status' => 'verified',
                ]);
            }

            $items[] = $item;
        }

        // 7. Diamond & Precious Stones
        DiamondStone::create([
            'stone_code' => 'DIA-2026-001',
            'stone_type' => 'Diamond',
            'shape' => 'Round',
            'cut' => 'Ideal',
            'color' => 'E',
            'clarity' => 'VVS1',
            'carat_weight' => 0.750,
            'certificate_no' => 'IGI-55443322',
            'certification_lab' => 'IGI',
            'supplier_id' => $sup2->id,
            'inventory_item_id' => $items[3]->id,
            'cost_price' => 45000,
            'selling_price' => 56250,
            'status' => 'set_in_jewellery',
        ]);

        DiamondStone::create([
            'stone_code' => 'GEM-2026-002',
            'stone_type' => 'Emerald',
            'shape' => 'Oval',
            'cut' => 'Good',
            'color' => 'Vivid Green',
            'clarity' => 'Eye Clean',
            'carat_weight' => 2.150,
            'certificate_no' => 'GIA-99887766',
            'certification_lab' => 'GIA',
            'supplier_id' => $sup2->id,
            'cost_price' => 28000,
            'selling_price' => 38000,
            'status' => 'in_stock',
        ]);

        // 8. Customers
        $cust1 = Customer::create([
            'customer_code' => 'CUST-001',
            'name' => 'Priya Singhania',
            'phone' => '9820112233',
            'email' => 'priya.singhania@example.com',
            'address' => 'Malabar Hill, Mumbai',
            'dob' => '1988-11-18',
            'anniversary' => '2015-12-05',
            'pan_number' => 'ABCPS1234D',
            'group_tier' => 'vip',
            'credit_limit' => 500000,
            'loyalty_points' => 1450,
            'preferences' => 'Prefers 22K antique temple jewellery',
        ]);

        $cust2 = Customer::create([
            'customer_code' => 'CUST-002',
            'name' => 'Rohan Mehta',
            'phone' => '9820445566',
            'email' => 'rohan.mehta@example.com',
            'address' => 'Pali Hill, Bandra West, Mumbai',
            'dob' => '1992-05-14',
            'group_tier' => 'gold',
            'credit_limit' => 200000,
            'loyalty_points' => 420,
        ]);

        // 9. Sample Invoice
        $inv = Invoice::create([
            'invoice_no' => 'INV-2026-0001',
            'branch_id' => $hq->id,
            'customer_id' => $cust1->id,
            'salesperson_id' => $sales1->id,
            'cashier_id' => $cashier->id,
            'invoice_type' => 'sale',
            'gst_type' => 'intra',
            'subtotal' => 75200,
            'making_total' => 6500,
            'stone_total' => 0,
            'discount_amount' => 0,
            'cgst_amount' => 1128,
            'sgst_amount' => 1128,
            'igst_amount' => 0,
            'round_off' => 0,
            'grand_total' => 77456,
            'paid_amount' => 77456,
            'balance_due' => 0,
            'status' => 'paid',
            'billed_at' => now()->subHours(2),
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_name' => 'Classic 22K Gold Chain 10g',
            'metal_type' => 'Gold',
            'purity' => '22K',
            'gross_weight' => 10.000,
            'stone_weight' => 0,
            'net_weight' => 10.000,
            'metal_rate' => 6870,
            'metal_value' => 68700,
            'making_charge_type' => 'per_gram',
            'making_charge_rate' => 650,
            'making_charge_amount' => 6500,
            'taxable_value' => 75200,
            'gst_rate' => 3.0,
            'gst_amount' => 2256,
            'line_total' => 77456,
        ]);

        InvoicePayment::create([
            'invoice_id' => $inv->id,
            'method' => 'upi',
            'amount' => 77456,
            'reference_no' => 'UPI-631728991',
            'paid_at' => now()->subHours(2),
            'received_by' => $cashier->id,
        ]);

        // 10. Old Gold Purchase
        OldGoldPurchase::create([
            'receipt_no' => 'OGP-2026-001',
            'branch_id' => $hq->id,
            'customer_id' => $cust1->id,
            'item_description' => 'Old 22K Gold Broken Bangle',
            'gross_weight' => 15.200,
            'stone_weight' => 0.400,
            'net_weight' => 14.800,
            'tested_purity' => '22K',
            'purity_pct' => 91.60,
            'fine_gold_weight' => 13.557,
            'applied_rate' => 7850,
            'deduction_pct' => 2.0,
            'melting_loss_pct' => 1.0,
            'valuation_amount' => 103226,
            'settlement_type' => 'exchange',
            'status' => 'approved',
            'approved_by' => $manager->id,
        ]);

        // 11. Gold Scheme & Installments
        $scheme = GoldScheme::create([
            'scheme_no' => 'SCH-2026-001',
            'branch_id' => $hq->id,
            'customer_id' => $cust2->id,
            'scheme_name' => 'Jewel Dhan 11+1 Plan',
            'monthly_amount' => 10000,
            'duration_months' => 11,
            'bonus_amount' => 10000,
            'start_date' => now()->subMonths(2)->startOfMonth(),
            'maturity_date' => now()->addMonths(9)->startOfMonth(),
            'total_paid' => 20000,
            'status' => 'active',
        ]);

        for ($i = 1; $i <= 11; $i++) {
            $paid = $i <= 2;
            SchemeInstallment::create([
                'gold_scheme_id' => $scheme->id,
                'installment_no' => $i,
                'amount' => 10000,
                'due_date' => now()->subMonths(3 - $i)->startOfMonth(),
                'paid_date' => $paid ? now()->subMonths(3 - $i)->startOfMonth() : null,
                'method' => $paid ? 'upi' : null,
                'receipt_no' => $paid ? 'SCH-RCP-' . $i : null,
                'status' => $paid ? 'paid' : 'pending',
            ]);
        }

        // 12. Manufacturing, Custom Order & Repair Job
        ManufacturingOrder::create([
            'job_no' => 'MFG-2026-001',
            'branch_id' => $hq->id,
            'artisan_id' => $artisan->id,
            'design_name' => 'Peacock Kundan Choker',
            'metal_type' => 'Gold',
            'purity' => '22K',
            'issued_weight' => 45.000,
            'expected_weight' => 43.800,
            'labour_charge' => 18000,
            'stage' => 'stone_setting',
            'expected_date' => now()->addDays(7),
            'status' => 'in_progress',
        ]);

        CustomOrder::create([
            'order_no' => 'ORD-2026-001',
            'branch_id' => $hq->id,
            'customer_id' => $cust1->id,
            'title' => 'Custom Mangalsutra with Solitaire Pendant',
            'design_notes' => '22K black-bead chain with 0.50ct VVS1 solitaire center',
            'metal_type' => 'Gold',
            'purity' => '22K',
            'estimated_weight' => 18.500,
            'locked_rate' => 7196,
            'estimated_amount' => 185000,
            'advance_paid' => 50000,
            'expected_delivery' => now()->addDays(10),
            'status' => 'in_production',
        ]);

        RepairJob::create([
            'job_no' => 'REP-2026-001',
            'branch_id' => $hq->id,
            'customer_id' => $cust2->id,
            'item_description' => '22K Gold Bracelet Clasp Soldering & Polish',
            'receiving_weight' => 18.250,
            'artisan_id' => $artisan->id,
            'repair_charges' => 850,
            'paid_amount' => 0,
            'expected_delivery' => now()->addDays(2),
            'status' => 'in_progress',
        ]);

        // 13. Purchases, Accounts, Vouchers, Consumables, Campaigns, Approvals
        $po = PurchaseOrder::create([
            'po_no' => 'PO-2026-001',
            'supplier_id' => $sup1->id,
            'branch_id' => $hq->id,
            'order_date' => now()->subDays(5),
            'expected_date' => now()->addDays(2),
            'total_weight' => 150.000,
            'total_amount' => 1125000,
            'status' => 'ordered',
        ]);

        PurchaseInvoice::create([
            'bill_no' => 'PUR-2026-001',
            'supplier_id' => $sup1->id,
            'branch_id' => $hq->id,
            'purchase_order_id' => $po->id,
            'bill_date' => now()->subDays(3),
            'metal_type' => 'Gold',
            'purity' => '22K',
            'metal_weight' => 100.000,
            'making_amount' => 25000,
            'gst_amount' => 22350,
            'total_amount' => 767350,
            'paid_amount' => 500000,
            'status' => 'partial',
        ]);

        $cashAcc = FinancialAccount::create(['code' => '1001', 'name' => 'Showroom Cash Counter', 'type' => 'cash', 'balance' => 185000]);
        $bankAcc = FinancialAccount::create(['code' => '1002', 'name' => 'HDFC Current Account', 'type' => 'bank', 'balance' => 2450000]);
        $salesAcc = FinancialAccount::create(['code' => '4001', 'name' => 'Jewellery Sales Account', 'type' => 'income', 'balance' => 77456]);
        FinancialAccount::create(['code' => '5001', 'name' => 'Bullion Purchase Account', 'type' => 'expense', 'balance' => 767350]);

        Voucher::create([
            'voucher_no' => 'VCH-2026-001',
            'branch_id' => $hq->id,
            'type' => 'receipt',
            'account_id' => $bankAcc->id,
            'contra_account_id' => $salesAcc->id,
            'amount' => 77456,
            'voucher_date' => now(),
            'narration' => 'Sales receipt for INV-2026-0001 via UPI',
            'created_by' => $cashier->id,
        ]);

        Expense::create([
            'branch_id' => $hq->id,
            'category' => 'Showroom Security & Insurance',
            'amount' => 12500,
            'expense_date' => now()->subDays(1),
            'paid_via' => 'bank',
            'description' => 'Monthly vault security & transit insurance',
            'created_by' => $manager->id,
        ]);

        Consumable::create(['branch_id' => $hq->id, 'sku' => 'CON-BOX-01', 'name' => 'Velvet Ring Boxes', 'quantity' => 8, 'reorder_level' => 20, 'unit' => 'pcs']);
        Consumable::create(['branch_id' => $hq->id, 'sku' => 'CON-TAG-01', 'name' => 'Thermal Jewellery Tags', 'quantity' => 450, 'reorder_level' => 100, 'unit' => 'rolls']);

        MarketingCampaign::create([
            'title' => 'Dhanteras & Diwali Gold Muhurat Offer',
            'channel' => 'whatsapp',
            'occasion' => 'dhanteras',
            'target_group' => 'vip',
            'message' => 'Flat 25% off on making charges this Dhanteras! Book your 22K BIS HUID jewellery today.',
            'status' => 'sent',
            'sent_count' => 148,
        ]);

        ApprovalRequest::create([
            'branch_id' => $hq->id,
            'type' => 'discount',
            'requested_by' => $sales1->id,
            'status' => 'pending',
            'reason' => 'VIP customer requesting 5% special bridal making discount',
            'payload' => ['discount_pct' => 5, 'amount' => 8500],
        ]);
    }
}
