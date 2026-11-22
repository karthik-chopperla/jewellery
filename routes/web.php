<?php

use App\Http\Controllers\AdminBranchController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerCrmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InvoiceExchangeController;
use App\Http\Controllers\InvoiceShareController;
use App\Http\Controllers\GoldSchemeController;
use App\Http\Controllers\MetalRateController;
use App\Http\Controllers\OldGoldController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductInventoryController;
use App\Http\Controllers\PurchaseSupplierController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WorkshopController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/shared/invoices/{share}/pdf', [InvoiceShareController::class, 'access'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('invoices.shared-pdf');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->middleware('permission:dashboard')->name('dashboard');

    // Module 4: Metal Rates
    Route::middleware('permission:rates')->group(function () {
        Route::get('/rates', [MetalRateController::class, 'index'])->name('rates.index');
        Route::post('/rates', [MetalRateController::class, 'store'])->name('rates.store');
        Route::post('/rates/sync', [MetalRateController::class, 'syncLiveRates'])->name('rates.sync');
        Route::post('/rates/{metalRate}/approve', [MetalRateController::class, 'approve'])->name('rates.approve');
        Route::delete('/rates/{metalRate}', [MetalRateController::class, 'destroy'])->name('rates.destroy');
    });

    // Modules 2, 3, 13, 14, 15: Products, Inventory, Labels, Stones, Hallmarking
    Route::middleware('permission:inventory')->group(function () {
        Route::get('/products', [ProductInventoryController::class, 'products'])->name('products.index');
        Route::post('/products', [ProductInventoryController::class, 'storeProduct'])->name('products.store');
        Route::delete('/products/{product}', [ProductInventoryController::class, 'destroyProduct'])->name('products.destroy');
        Route::get('/inventory', [ProductInventoryController::class, 'inventory'])->name('inventory.index');
        Route::post('/inventory', [ProductInventoryController::class, 'storeItem'])->name('inventory.store');
        Route::delete('/inventory/{inventoryItem}', [ProductInventoryController::class, 'destroyItem'])->name('inventory.destroy');
        Route::post('/inventory/transfer', [ProductInventoryController::class, 'transferStock'])->name('inventory.transfer');
        Route::post('/inventory/adjust', [ProductInventoryController::class, 'adjustStock'])->name('inventory.adjust');
        Route::get('/inventory/labels', [ProductInventoryController::class, 'labels'])->name('inventory.labels');
        Route::get('/stones-hallmark', [ProductInventoryController::class, 'stonesAndHallmarking'])->name('stones.hallmark');
        Route::post('/stones', [ProductInventoryController::class, 'storeStone'])->name('stones.store');
        Route::delete('/stones/{stone}', [ProductInventoryController::class, 'destroyStone'])->name('stones.destroy');
        Route::post('/hallmark', [ProductInventoryController::class, 'storeHallmark'])->name('hallmark.store');
        Route::delete('/hallmark/{hallmark}', [ProductInventoryController::class, 'destroyHallmark'])->name('hallmark.destroy');
    });

    // Modules 5, 6, 17: POS, Billing, Price Engine, Returns
    Route::middleware('permission:pos')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/calculate', [PosController::class, 'calculatePreview'])->name('pos.calculate');
        Route::post('/pos/checkout', [PosController::class, 'store'])->name('pos.store');
        Route::get('/invoices', [PosController::class, 'invoices'])->name('pos.invoices');
        Route::get('/invoices/{invoice}', [PosController::class, 'showInvoice'])->name('pos.invoice');
        Route::get('/invoices/{invoice}/pdf', [PosController::class, 'invoicePdf'])->name('pos.invoice.pdf');
        Route::post('/invoices/{invoice}/whatsapp', [InvoiceShareController::class, 'share'])->name('pos.invoice.whatsapp');
        Route::get('/invoices/{invoice}/exchange', [InvoiceExchangeController::class, 'create'])->name('pos.invoice.exchange');
        Route::get('/sales-returns/{salesReturn}/credit-note.pdf', [PosController::class, 'creditNotePdf'])->name('pos.returns.credit-note');
        Route::post('/sales-returns', [PosController::class, 'storeReturn'])->name('pos.returns.store');
    });
    Route::middleware('permission:pos_manage')->group(function () {
        Route::delete('/invoices/{invoice}', [PosController::class, 'destroyInvoice'])->name('pos.invoices.destroy');
        Route::post('/invoices/{invoice}/cancel', [PosController::class, 'cancelInvoice'])->name('pos.cancel');
        Route::post('/sales-returns/{salesReturn}/approve', [PosController::class, 'approveReturn'])->name('pos.returns.approve');
        Route::delete('/sales-returns/{salesReturn}', [PosController::class, 'destroyReturn'])->name('pos.returns.destroy');
        Route::post('/invoice-shares/{share}/revoke', [InvoiceShareController::class, 'revoke'])->name('pos.invoice-shares.revoke');
        Route::post('/invoices/{invoice}/exchange', [InvoiceExchangeController::class, 'store'])->name('pos.invoice.exchange.store');
    });

    // Modules 7, 18, 24: CRM, Loyalty, Marketing
    Route::middleware('permission:customers')->group(function () {
        Route::get('/customers', [CustomerCrmController::class, 'index'])->name('crm.index');
        Route::post('/customers', [CustomerCrmController::class, 'store'])->name('crm.store');
        Route::get('/customers/{customer}', [CustomerCrmController::class, 'show'])->name('crm.show');
        Route::delete('/customers/{customer}', [CustomerCrmController::class, 'destroyCustomer'])->name('crm.customers.destroy');
        Route::post('/customers/{customer}/loyalty', [CustomerCrmController::class, 'adjustLoyalty'])->name('crm.loyalty');
        Route::post('/campaigns', [CustomerCrmController::class, 'storeCampaign'])->name('crm.campaigns.store');
        Route::post('/campaigns/direct-greeting', [CustomerCrmController::class, 'sendDirectGreeting'])->name('crm.campaigns.direct');
        Route::delete('/campaigns/{campaign}', [CustomerCrmController::class, 'destroyCampaign'])->name('crm.campaigns.destroy');
    });

    // Module 8: Old Gold Exchange & Purchase
    Route::middleware('permission:old_gold')->group(function () {
        Route::get('/old-gold', [OldGoldController::class, 'index'])->name('old_gold.index');
        Route::post('/old-gold', [OldGoldController::class, 'store'])->name('old_gold.store');
        Route::delete('/old-gold/{oldGoldPurchase}', [OldGoldController::class, 'destroy'])->name('old_gold.destroy');
        Route::post('/old-gold/{oldGoldPurchase}/refinery', [OldGoldController::class, 'transferRefinery'])->name('old_gold.refinery');
    });

    // Module 9: Purchases & Suppliers
    Route::middleware('permission:purchases')->group(function () {
        Route::get('/purchases', [PurchaseSupplierController::class, 'index'])->name('purchases.index');
        Route::post('/suppliers', [PurchaseSupplierController::class, 'storeSupplier'])->name('suppliers.store');
        Route::delete('/suppliers/{supplier}', [PurchaseSupplierController::class, 'destroySupplier'])->name('suppliers.destroy');
        Route::post('/purchase-invoices', [PurchaseSupplierController::class, 'storePurchaseInvoice'])->name('purchases.store');
        Route::delete('/purchase-invoices/{purchaseInvoice}', [PurchaseSupplierController::class, 'destroyPurchaseInvoice'])->name('purchases.destroy');
        Route::post('/supplier-payments', [PurchaseSupplierController::class, 'paySupplier'])->name('suppliers.pay');
    });

    // Modules 10, 11, 16: Manufacturing, Custom Orders, Repairs
    Route::get('/workshop', [WorkshopController::class, 'index'])
        ->middleware('permission:manufacturing,custom_orders,repairs')
        ->name('workshop.index');
    Route::middleware('permission:manufacturing')->group(function () {
        Route::post('/manufacturing', [WorkshopController::class, 'storeManufacturing'])->name('workshop.mfg.store');
        Route::post('/manufacturing/{order}/stage', [WorkshopController::class, 'updateManufacturingStage'])->name('workshop.mfg.stage');
    });
    Route::middleware('permission:custom_orders')->group(function () {
        Route::post('/custom-orders', [WorkshopController::class, 'storeCustomOrder'])->name('workshop.custom.store');
        Route::post('/custom-orders/{order}/status', [WorkshopController::class, 'updateCustomOrderStatus'])->name('workshop.custom.status');
    });
    Route::middleware('permission:repairs')->group(function () {
        Route::post('/repairs', [WorkshopController::class, 'storeRepair'])->name('workshop.repair.store');
        Route::post('/repairs/{repairJob}/status', [WorkshopController::class, 'updateRepairStatus'])->name('workshop.repair.status');
    });
    Route::middleware('permission:workshop_manage')->group(function () {
        Route::delete('/manufacturing/{order}', [WorkshopController::class, 'destroyManufacturing'])->name('workshop.mfg.destroy');
        Route::delete('/custom-orders/{order}', [WorkshopController::class, 'destroyCustomOrder'])->name('workshop.custom.destroy');
        Route::delete('/repairs/{repairJob}', [WorkshopController::class, 'destroyRepair'])->name('workshop.repair.destroy');
    });

    // Module 12: Gold Savings Schemes
    Route::middleware('permission:schemes')->group(function () {
        Route::get('/schemes', [GoldSchemeController::class, 'index'])->name('schemes.index');
        Route::post('/schemes', [GoldSchemeController::class, 'store'])->name('schemes.store');
        Route::delete('/schemes/{scheme}', [GoldSchemeController::class, 'destroy'])->name('schemes.destroy');
        Route::post('/schemes/installments/{installment}/pay', [GoldSchemeController::class, 'payInstallment'])->name('schemes.pay');
    });

    // Module 19: Accounts & Finance
    Route::middleware('permission:accounts')->group(function () {
        Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::post('/finance/vouchers', [FinanceController::class, 'storeVoucher'])->name('finance.vouchers.store');
        Route::delete('/finance/vouchers/{voucher}', [FinanceController::class, 'destroyVoucher'])->name('finance.vouchers.destroy');
        Route::post('/finance/expenses', [FinanceController::class, 'storeExpense'])->name('finance.expenses.store');
        Route::delete('/finance/expenses/{expense}', [FinanceController::class, 'destroyExpense'])->name('finance.expenses.destroy');
    });

    // Modules 20, 21, 22, 23: Staff, Branches, Security/Approvals, BI Reports
    Route::middleware('permission:staff')->group(function () {
        Route::get('/staff', [AdminBranchController::class, 'staff'])->name('admin.staff');
        Route::post('/staff/attendance', [AdminBranchController::class, 'markAttendance'])->name('admin.attendance');
    });
    Route::middleware('permission:staff_manage')->group(function () {
        Route::post('/staff', [AdminBranchController::class, 'storeStaff'])->name('admin.staff.store');
        Route::delete('/staff/{user}', [AdminBranchController::class, 'destroyStaff'])->name('admin.staff.destroy');
    });
    Route::middleware('permission:branches')->group(function () {
        Route::get('/branches', [AdminBranchController::class, 'branches'])->name('admin.branches');
        Route::post('/branches', [AdminBranchController::class, 'storeBranch'])->name('admin.branches.store');
        Route::delete('/branches/{branch}', [AdminBranchController::class, 'destroyBranch'])->name('admin.branches.destroy');
    });
    Route::middleware('permission:security,approvals')->group(function () {
        Route::get('/security', [AdminBranchController::class, 'securityAndApprovals'])->name('admin.security');
    });
    Route::middleware('permission:approvals')->group(function () {
        Route::post('/approvals/{approval}/decide', [AdminBranchController::class, 'decideApproval'])->name('admin.approvals.decide');
    });
    Route::middleware('permission:reports')->group(function () {
        Route::get('/reports', [AdminBranchController::class, 'reports'])->name('admin.reports');
    });

    // Showroom & Business Settings
    Route::middleware('permission:settings')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
