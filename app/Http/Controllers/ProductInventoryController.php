<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\Counter;
use App\Models\DiamondStone;
use App\Models\HallmarkRecord;
use App\Models\InventoryItem;
use App\Models\LabelPrintLog;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\JewelleryIdentifierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ProductInventoryController extends Controller
{
    public function products(Request $request)
    {
        $products = Product::with(['category', 'supplier'])
            ->withCount(['inventoryItems as available_count' => fn ($q) => $q->where('status', 'available')])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20);

        $categories = Category::all();
        $suppliers = Supplier::where('is_active', true)->get();

        return view('inventory.products', compact('products', 'categories', 'suppliers'));
    }

    public function categories()
    {
        $categories = Category::withCount('products')->orderBy('name')->get();

        return view('inventory.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $this->validateCategory($request);
        $category = Category::create($data);
        AuditService::log('Categories', 'create', "Created category {$category->name} ({$category->code})", $category);

        return back()->with('success', "Category {$category->name} created. It is now available in Product Master and POS.");
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $this->validateCategory($request, $category);
        $category->update($data);
        AuditService::log('Categories', 'update', "Updated category {$category->name} ({$category->code})", $category);

        return back()->with('success', "Category {$category->name} updated.");
    }

    public function destroyCategory(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->withErrors([
                'category' => "Cannot delete {$category->name} because products are using it. Reassign or remove those products first.",
            ]);
        }

        $name = $category->name;
        $category->delete();
        AuditService::log('Categories', 'delete', "Deleted category {$name} (#{$category->id})");

        return back()->with('success', "Category {$name} deleted.");
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'code' => Str::upper(trim((string) $request->input('code'))),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category?->id)],
            'code' => ['required', 'string', 'max:20', Rule::unique('categories', 'code')->ignore($category?->id)],
            'default_making_charge_type' => 'required|in:per_gram,percentage,fixed',
            'default_making_charge_value' => 'required|numeric|min:0|max:100000000',
            'default_wastage_pct' => 'required|numeric|min:0|max:100',
            'gst_rate' => 'required|numeric|min:0|max:100',
        ]);
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'sku' => 'required|string|unique:products,sku',
            'name' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'product_type' => 'required|string',
            'metal_type' => 'required|string',
            'purity' => 'required|string',
            'making_charge_type' => 'required|in:per_gram,percentage,fixed',
            'making_charge_value' => 'required|numeric|min:0',
            'wastage_pct' => 'nullable|numeric|min:0',
            'gst_rate' => 'nullable|numeric|min:0',
            'hsn_code' => 'nullable|string|max:20',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'description' => 'nullable|string',
            'next_step' => 'nullable|in:inventory',
        ]);

        $product = Product::create([
            'sku' => $data['sku'],
            'name' => $data['name'],
            'category_id' => $data['category_id'],
            'product_type' => $data['product_type'],
            'metal_type' => $data['metal_type'],
            'purity' => $data['purity'],
            'making_charge_type' => $data['making_charge_type'],
            'making_charge_value' => (float) $data['making_charge_value'],
            'wastage_pct' => (float) ($data['wastage_pct'] ?? 0),
            'gst_rate' => (float) ($data['gst_rate'] ?? Category::findOrFail($data['category_id'])->gst_rate),
            'hsn_code' => $data['hsn_code'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'active',
        ]);
        AuditService::log('Products', 'create', "Created product {$product->sku} - {$product->name}", $product);

        if (($data['next_step'] ?? null) === 'inventory') {
            return redirect()->route('inventory.index', ['product_id' => $product->id])
                ->with('success', 'Product saved. Register each physical piece below to assign its own barcode.');
        }

        return back()->with('success', 'Product master created.');
    }

    public function destroyProduct(Product $product)
    {
        $info = "{$product->sku} ({$product->name})";
        $product->delete();
        AuditService::log('Products', 'delete', "Deleted product {$info} (#{$product->id})");

        return back()->with('success', "Product {$info} deleted successfully.");
    }

    public function inventory(Request $request)
    {
        $items = InventoryItem::with(['product', 'branch', 'counter', 'supplier'])
            ->when($request->branch_id, fn ($q, $b) => $q->where('branch_id', $b))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->metal_type, fn ($q, $m) => $q->whereHas('product', fn ($p) => $p->where('metal_type', $m)))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('item_code', 'like', "%{$s}%")->orWhere('barcode', 'like', "%{$s}%")->orWhere('huid', 'like', "%{$s}%")))
            ->latest()
            ->paginate(25);

        $products = Product::where('status', 'active')->get();
        $branches = Branch::where('is_active', true)->get();
        $counters = Counter::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $transfers = StockTransfer::with(['fromBranch', 'toBranch', 'items.inventoryItem.product'])->latest()->take(10)->get();
        $consumables = Consumable::with('branch')->get();

        return view('inventory.items', compact('items', 'products', 'branches', 'counters', 'suppliers', 'transfers', 'consumables'));
    }

    public function storeItem(Request $request, JewelleryIdentifierService $identifierService)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'counter_id' => 'nullable|exists:counters,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'quantity' => 'nullable|integer|min:1|max:100',
            'gross_weight' => 'required|numeric|min:0.001',
            'stone_weight' => 'nullable|numeric|min:0',
            'diamond_weight' => 'nullable|numeric|min:0',
            'other_weight_deductions' => 'nullable|numeric|min:0',
            'purity' => 'required|string',
            'huid' => 'nullable|string|unique:inventory_items,huid',
            'barcode' => 'nullable|string|max:60|unique:inventory_items,barcode',
            'barcode_format' => 'nullable|in:code128,ean13',
            'hallmark_center' => 'nullable|string',
            'hallmark_charge' => 'nullable|numeric|min:0',
            'purchase_cost' => 'nullable|numeric|min:0',
            'stone_charge' => 'nullable|numeric|min:0',
            'diamond_charge' => 'nullable|numeric|min:0',
            'other_charge' => 'nullable|numeric|min:0',
            'stock_entry_date' => 'nullable|date',
            'purchase_reference' => 'nullable|string|max:100',
        ]);

        $quantity = (int) ($data['quantity'] ?? 1);
        if ($quantity > 1 && ! empty($data['huid'])) {
            throw ValidationException::withMessages([
                'huid' => 'Enter a HUID only when registering one piece; each piece must have its own HUID.',
            ]);
        }
        if ($quantity > 1 && ! empty($data['barcode'])) {
            throw ValidationException::withMessages([
                'barcode' => 'Enter a manual barcode only for one piece. Barcodes are generated individually for multiple pieces.',
            ]);
        }

        $stoneWt = (float) ($data['stone_weight'] ?? 0);
        $diaWt = (float) ($data['diamond_weight'] ?? 0);
        $otherDeductions = (float) ($data['other_weight_deductions'] ?? 0);
        $grossWt = (float) $data['gross_weight'];
        if ($stoneWt + $otherDeductions > $grossWt) {
            throw ValidationException::withMessages([
                'other_weight_deductions' => 'Stone weight and other deductions cannot exceed gross weight.',
            ]);
        }
        $netWt = $grossWt - $stoneWt - $otherDeductions;
        $purchaseCost = (float) ($data['purchase_cost'] ?? 0);
        $stoneCharge = (float) ($data['stone_charge'] ?? 0);
        $diamondCharge = (float) ($data['diamond_charge'] ?? 0);
        $otherCharge = (float) ($data['other_charge'] ?? 0);
        $hallmarkCharge = (float) ($data['hallmark_charge'] ?? 45);

        $format = $data['barcode_format'] ?? 'code128';
        $prefix = Setting::get('barcode_prefix', '890') ?? '890';
        if (($data['barcode'] ?? null) === null) {
            $this->assertBarcodePrefix($format, $prefix);
        }
        if (($data['barcode'] ?? null) !== null && $format === 'ean13' && !$identifierService->isValidEan13($data['barcode'])) {
            throw ValidationException::withMessages(['barcode' => 'Enter a valid 13-digit EAN-13 barcode, including its check digit.']);
        }

        $createdItems = DB::transaction(function () use ($data, $quantity, $grossWt, $stoneWt, $diaWt, $otherDeductions, $netWt, $purchaseCost, $stoneCharge, $diamondCharge, $otherCharge, $hallmarkCharge, $format, $prefix, $identifierService) {
            $nextNum = (InventoryItem::max('id') ?? 0) + 1;
            $created = [];

            for ($index = 0; $index < $quantity; $index++) {
                $itemCode = 'JW-' . str_pad($nextNum + $index, 5, '0', STR_PAD_LEFT);
                $barcode = $index === 0 && ! empty($data['barcode'])
                    ? $data['barcode']
                    : $identifierService->generateBarcode($format, $prefix);

                $item = InventoryItem::create([
                    'product_id' => $data['product_id'],
                    'branch_id' => $data['branch_id'],
                    'counter_id' => $data['counter_id'] ?? null,
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'item_code' => $itemCode,
                    'barcode' => $barcode,
                    'barcode_format' => $format,
                    'barcode_generated_at' => now(),
                    'qr_token' => $identifierService->generateQrToken(),
                    'qr_generated_at' => now(),
                    'gross_weight' => round($grossWt, 3),
                    'stone_weight' => round($stoneWt, 3),
                    'other_weight_deductions' => round($otherDeductions, 3),
                    'diamond_weight' => round($diaWt, 3),
                    'net_weight' => round($netWt, 3),
                    'purity' => $data['purity'],
                    'huid' => ! empty($data['huid']) ? $data['huid'] : null,
                    'hallmark_center' => $data['hallmark_center'] ?? null,
                    'hallmark_charge' => $hallmarkCharge,
                    'purchase_cost' => $purchaseCost,
                    'stone_charge' => $stoneCharge,
                    'diamond_charge' => $diamondCharge,
                    'other_charge' => $otherCharge,
                    'stock_entry_date' => $data['stock_entry_date'] ?? now()->toDateString(),
                    'purchase_reference' => $data['purchase_reference'] ?? null,
                    'status' => 'available',
                ]);

                StockMovement::create([
                    'inventory_item_id' => $item->id,
                    'branch_id' => $item->branch_id,
                    'type' => 'stock_in',
                    'reference' => $data['purchase_reference'] ?? $itemCode,
                    'notes' => 'Unique jewellery piece registered',
                    'user_id' => Auth::id(),
                ]);

                $created[] = $item;
            }

            return $created;
        });

        $itemCodes = collect($createdItems)->pluck('item_code');
        AuditService::log('Inventory', 'create', 'Registered ' . count($createdItems) . ' piece(s): ' . $itemCodes->join(', '), $createdItems[0]);

        return redirect()->route('inventory.index', ['product_id' => $data['product_id']])
            ->with('success', count($createdItems) . ' jewellery piece(s) registered. Each piece has its own barcode and QR code.')
            ->with('created_inventory_ids', collect($createdItems)->pluck('id')->all());
    }

    public function destroyItem(InventoryItem $inventoryItem)
    {
        $info = "{$inventoryItem->item_code} ({$inventoryItem->net_weight}g)";
        $inventoryItem->delete();
        AuditService::log('Inventory', 'delete', "Deleted inventory piece {$info} (#{$inventoryItem->id})");

        return back()->with('success', "Inventory piece {$info} deleted.");
    }

    public function transferStock(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'to_branch_id' => 'required|exists:branches,id',
            'to_counter_id' => 'nullable|exists:counters,id',
            'notes' => 'nullable|string',
        ]);

        $item = InventoryItem::findOrFail($data['inventory_item_id']);
        $transfer = StockTransfer::create([
            'transfer_no' => 'TRF-' . strtoupper(Str::random(6)),
            'from_branch_id' => $item->branch_id,
            'to_branch_id' => $data['to_branch_id'],
            'from_counter_id' => $item->counter_id,
            'to_counter_id' => $data['to_counter_id'] ?? null,
            'initiated_by' => Auth::id(),
            'approved_by' => Auth::id(),
            'status' => 'completed',
            'notes' => $data['notes'] ?? null,
            'transferred_at' => now(),
        ]);

        StockTransferItem::create(['stock_transfer_id' => $transfer->id, 'inventory_item_id' => $item->id]);
        $item->update(['branch_id' => $data['to_branch_id'], 'counter_id' => $data['to_counter_id'] ?? null]);

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'branch_id' => $data['to_branch_id'],
            'type' => 'transfer_in',
            'reference' => $transfer->transfer_no,
            'notes' => 'Branch/counter transfer',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', "Item {$item->item_code} transferred ({$transfer->transfer_no}).");
    }

    public function adjustStock(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'type' => 'required|in:damaged,lost,found,weight_correction,verification',
            'new_weight' => 'nullable|numeric|min:0',
            'reason' => 'required|string',
        ]);

        $item = InventoryItem::findOrFail($data['inventory_item_id']);
        $adj = StockAdjustment::create([
            'adjustment_no' => 'ADJ-' . strtoupper(Str::random(6)),
            'branch_id' => $item->branch_id,
            'inventory_item_id' => $item->id,
            'type' => $data['type'],
            'previous_weight' => $item->net_weight,
            'new_weight' => $data['new_weight'] ?? $item->net_weight,
            'reason' => $data['reason'],
            'status' => 'approved',
            'requested_by' => Auth::id(),
            'approved_by' => Auth::id(),
        ]);

        if (in_array($data['type'], ['damaged', 'lost'], true)) {
            $item->update(['status' => $data['type']]);
        } elseif ($data['type'] === 'verification') {
            $item->update(['verified_at' => now()]);
        } elseif ($data['type'] === 'weight_correction' && isset($data['new_weight'])) {
            $item->update(['net_weight' => $data['new_weight']]);
        }

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'branch_id' => $item->branch_id,
            'type' => 'adjustment',
            'reference' => $adj->adjustment_no,
            'notes' => "{$data['type']}: {$data['reason']}",
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', "Stock adjustment {$adj->adjustment_no} recorded.");
    }

    public function labels(Request $request)
    {
        $filters = $this->validateLabelFilters($request);
        $inventoryQuery = $this->labelInventoryQuery($filters);
        $items = (clone $inventoryQuery)->latest('inventory_items.id')->paginate(25)->withQueryString();
        $totalItems = InventoryItem::count();
        $withBarcode = InventoryItem::whereNotNull('barcode')->where('barcode', '<>', '')->count();
        $withQr = InventoryItem::whereNotNull('qr_token')->count();
        $pendingLabels = InventoryItem::whereNotExists(function ($query) {
            $query->selectRaw('1')->from('label_print_logs')
                ->whereColumn('label_print_logs.inventory_item_id', 'inventory_items.id')
                ->where('label_print_logs.label_type', 'tag');
        })->count();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $purities = InventoryItem::query()->whereNotNull('purity')->distinct()->orderBy('purity')->pluck('purity');
        $recentGenerated = (clone $inventoryQuery)->whereNotNull('barcode_generated_at')->latest('barcode_generated_at')->take(6)->get();
        $recentPrinted = LabelPrintLog::with(['inventoryItem.product', 'user'])
            ->where('label_type', 'tag')
            ->whereIn('inventory_item_id', (clone $inventoryQuery)->select('inventory_items.id'))
            ->latest()
            ->take(6)
            ->get();

        return view('inventory.labels', compact(
            'items', 'filters', 'totalItems', 'withBarcode', 'withQr', 'pendingLabels',
            'branches', 'categories', 'purities', 'recentGenerated', 'recentPrinted'
        ));
    }

    public function generateMissingBarcodes(Request $request, JewelleryIdentifierService $identifierService)
    {
        $validated = $request->validate([
            'ids' => 'nullable|array|max:5000',
            'ids.*' => 'integer|distinct|exists:inventory_items,id',
            'all_filtered' => 'nullable|boolean',
        ]);
        $filters = $this->validateLabelFilters($request);
        $query = $this->labelInventoryQuery($filters)
            ->where(function ($query) {
                $query->whereNull('barcode')->orWhere('barcode', '');
            });

        if (empty($validated['all_filtered'])) {
            if (empty($validated['ids'])) {
                throw ValidationException::withMessages(['ids' => 'Select inventory items or choose all filtered missing barcodes.']);
            }
            $query->whereIn('inventory_items.id', $validated['ids']);
        }

        $prefix = Setting::get('barcode_prefix', '890') ?? '890';
        $generated = DB::transaction(function () use ($query, $identifierService, $prefix) {
            $items = $query->lockForUpdate()->get();
            foreach ($items as $item) {
                $format = $item->barcode_format ?: 'code128';
                $this->assertBarcodePrefix($format, $prefix);
                $item->update([
                    'barcode' => $identifierService->generateBarcode($format, $prefix),
                    'barcode_generated_at' => now(),
                ]);
            }

            return $items->count();
        });

        AuditService::log('Inventory Labels', 'generate', "Generated {$generated} missing inventory barcodes");

        return redirect()->route('inventory.labels', $filters)->with('success', "{$generated} missing barcode(s) generated.");
    }

    public function generateMissingQrCodes(Request $request, JewelleryIdentifierService $identifierService)
    {
        $validated = $request->validate([
            'ids' => 'nullable|array|max:5000',
            'ids.*' => 'integer|distinct|exists:inventory_items,id',
            'all_filtered' => 'nullable|boolean',
        ]);
        $filters = $this->validateLabelFilters($request);
        $query = $this->labelInventoryQuery($filters)->whereNull('qr_token');

        if (empty($validated['all_filtered'])) {
            if (empty($validated['ids'])) {
                throw ValidationException::withMessages(['ids' => 'Select inventory items or choose all filtered missing QR codes.']);
            }
            $query->whereIn('inventory_items.id', $validated['ids']);
        }

        $generated = DB::transaction(function () use ($query, $identifierService) {
            $items = $query->lockForUpdate()->get();
            foreach ($items as $item) {
                $item->update([
                    'qr_token' => $identifierService->generateQrToken(),
                    'qr_generated_at' => now(),
                ]);
            }

            return $items->count();
        });

        AuditService::log('Inventory Labels', 'generate', "Generated {$generated} missing inventory QR codes");

        return redirect()->route('inventory.labels', $filters)->with('success', "{$generated} missing QR code(s) generated.");
    }

    public function printLabels(Request $request)
    {
        $data = $this->labelPrintData($request);
        $jobId = (string) Str::uuid();

        DB::transaction(function () use ($data, $jobId) {
            foreach ($data['items'] as $item) {
                LabelPrintLog::create([
                    'inventory_item_id' => $item->id,
                    'user_id' => Auth::id(),
                    'label_type' => 'tag',
                    'copies' => $data['copies'],
                    'print_job_id' => $jobId,
                    'template' => $data['template'],
                    'paper_type' => $data['paper_type'],
                    'label_width_mm' => $data['width_mm'],
                    'label_height_mm' => $data['height_mm'],
                    'label_margin_mm' => $data['margin_mm'],
                    'label_gap_mm' => $data['gap_mm'],
                ]);
            }
        });

        return view('inventory.label-print', array_merge($data, [
            'businessName' => Setting::get('business_name', 'Abharan ERP'),
            'logoPath' => Setting::get('business_logo_path'),
            'printJobId' => $jobId,
        ]));
    }

    public function previewLabels(Request $request)
    {
        $data = $this->labelPrintData($request, false);

        return view('inventory.label-print', array_merge($data, [
            'businessName' => Setting::get('business_name', 'Abharan ERP'),
            'logoPath' => Setting::get('business_logo_path'),
            'printJobId' => null,
        ]));
    }

    public function updateBarcode(Request $request, InventoryItem $inventoryItem, JewelleryIdentifierService $identifierService)
    {
        $data = $request->validate([
            'barcode' => 'required|string|max:60|unique:inventory_items,barcode,' . $inventoryItem->id,
            'barcode_format' => 'required|in:code128,ean13',
        ]);

        if ($data['barcode_format'] === 'ean13' && !$identifierService->isValidEan13($data['barcode'])) {
            throw ValidationException::withMessages(['barcode' => 'Enter a valid 13-digit EAN-13 barcode, including its check digit.']);
        }

        $oldBarcode = $inventoryItem->barcode;
        $inventoryItem->update([
            'barcode' => $data['barcode'],
            'barcode_format' => $data['barcode_format'],
            'barcode_generated_at' => now(),
        ]);
        AuditService::log('Inventory Labels', 'update', "Changed barcode for {$inventoryItem->item_code} from {$oldBarcode} to {$data['barcode']}", $inventoryItem);

        return back()->with('success', "Barcode updated for {$inventoryItem->item_code}.");
    }

    public function regenerateBarcode(InventoryItem $inventoryItem, JewelleryIdentifierService $identifierService)
    {
        $format = $inventoryItem->barcode_format ?: 'code128';
        $prefix = Setting::get('barcode_prefix', '890') ?? '890';
        $this->assertBarcodePrefix($format, $prefix);
        $barcode = $identifierService->generateBarcode(
            $format,
            $prefix
        );
        $inventoryItem->update(['barcode' => $barcode, 'barcode_generated_at' => now()]);
        AuditService::log('Inventory Labels', 'regenerate', "Regenerated barcode for {$inventoryItem->item_code}", $inventoryItem);

        return back()->with('success', "Barcode regenerated for {$inventoryItem->item_code}. Reprint its tag.");
    }

    private function validateLabelFilters(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:120',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'metal_type' => 'nullable|string|max:30',
            'purity' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:30',
            'missing' => 'nullable|in:barcode,qr,either',
        ]);
    }

    private function assertBarcodePrefix(string $format, string $prefix): void
    {
        if ($format === 'ean13' && ($prefix === '' || !ctype_digit($prefix) || strlen($prefix) > 11)) {
            throw ValidationException::withMessages([
                'barcode_prefix' => 'The configured barcode prefix must contain 1 to 11 digits to generate EAN-13 codes.',
            ]);
        }
    }

    private function labelInventoryQuery(array $filters)
    {
        return InventoryItem::with(['product.category', 'branch'])
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('item_code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            }))
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->whereHas('product', fn ($product) => $product->where('category_id', $categoryId)))
            ->when($filters['metal_type'] ?? null, fn ($query, $metalType) => $query->whereHas('product', fn ($product) => $product->where('metal_type', $metalType)))
            ->when($filters['purity'] ?? null, fn ($query, $purity) => $query->where('purity', $purity))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['missing'] ?? null) === 'barcode', fn ($query) => $query->where(fn ($query) => $query->whereNull('barcode')->orWhere('barcode', '')))
            ->when(($filters['missing'] ?? null) === 'qr', fn ($query) => $query->whereNull('qr_token'))
            ->when(($filters['missing'] ?? null) === 'either', fn ($query) => $query->where(fn ($query) => $query->whereNull('barcode')->orWhere('barcode', '')->orWhereNull('qr_token')));
    }

    private function labelPrintData(Request $request, bool $isPrint = true): array
    {
        $validated = $request->validate([
            'ids' => 'nullable|array|max:5000',
            'ids.*' => 'integer|distinct|exists:inventory_items,id',
            'all_filtered' => 'nullable|boolean',
            'template' => 'nullable|in:gold,silver,diamond,minimal,qr',
            'paper_type' => 'nullable|in:a4,roll',
            'width_mm' => 'nullable|numeric|min:15|max:100',
            'height_mm' => 'nullable|numeric|min:15|max:100',
            'margin_mm' => 'nullable|numeric|min:0|max:20',
            'gap_mm' => 'nullable|numeric|min:0|max:10',
            'orientation' => 'nullable|in:portrait,landscape',
            'copies' => 'nullable|integer|min:1|max:50',
            'include_qr' => 'nullable|boolean',
            'fields' => 'nullable|array',
            'fields.*' => 'in:category,weights,huid,metal,purity',
        ]);
        $filters = $this->validateLabelFilters($request);

        if (!empty($validated['all_filtered'])) {
            $query = $this->labelInventoryQuery($filters)->orderBy('inventory_items.id');
            $total = (clone $query)->count();
            $items = $query->limit(5000)->get();
        } else {
            if (empty($validated['ids'])) {
                throw ValidationException::withMessages(['ids' => 'Select at least one jewellery item to preview or print.']);
            }
            $items = InventoryItem::with(['product.category', 'branch'])->whereIn('id', $validated['ids'])->orderBy('id')->get();
            $total = $items->count();
        }

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['ids' => 'No inventory items matched the label selection.']);
        }

        return [
            'items' => $items,
            'copies' => (int) ($validated['copies'] ?? 1),
            'template' => $validated['template'] ?? 'gold',
            'paper_type' => $validated['paper_type'] ?? 'a4',
            'width_mm' => (float) ($validated['width_mm'] ?? 50),
            'height_mm' => (float) ($validated['height_mm'] ?? 25),
            'margin_mm' => (float) ($validated['margin_mm'] ?? 6),
            'gap_mm' => (float) ($validated['gap_mm'] ?? 3),
            'orientation' => $validated['orientation'] ?? 'portrait',
            'include_qr' => ($validated['template'] ?? '') === 'qr' || !empty($validated['include_qr']),
            'fields' => $validated['fields'] ?? ['weights', 'huid', 'metal', 'purity'],
            'truncated' => $total > $items->count(),
            'totalLabels' => $items->count() * (int) ($validated['copies'] ?? 1),
            'matchedItems' => $total,
            'isPrint' => $isPrint,
        ];
    }

    public function stonesAndHallmarking()
    {
        $stones = DiamondStone::with(['supplier', 'inventoryItem.product'])->latest()->get();
        $hallmarks = HallmarkRecord::with('inventoryItem.product')->latest()->get();
        $items = InventoryItem::with('product')->where('status', 'available')->get();
        $suppliers = Supplier::where('is_active', true)->get();

        return view('inventory.stones_hallmark', compact('stones', 'hallmarks', 'items', 'suppliers'));
    }

    public function storeStone(Request $request)
    {
        $data = $request->validate([
            'stone_type' => 'required|string',
            'shape' => 'nullable|string',
            'cut' => 'nullable|string',
            'color' => 'nullable|string',
            'clarity' => 'nullable|string',
            'carat_weight' => 'required|numeric|min:0.001',
            'certificate_no' => 'nullable|string',
            'certification_lab' => 'nullable|string',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
        ]);

        $data['stone_code'] = 'STN-' . strtoupper(Str::random(6));
        DiamondStone::create($data);

        return back()->with('success', "Stone {$data['stone_code']} added to inventory.");
    }

    public function storeHallmark(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'center_name' => 'required|string',
            'dispatch_date' => 'required|date',
            'huid' => 'nullable|string',
            'purity_verified' => 'nullable|string',
            'charges' => 'nullable|numeric|min:0',
            'status' => 'required|in:dispatched,received,verified',
        ]);

        $record = HallmarkRecord::create($data + ['receipt_date' => $data['status'] !== 'dispatched' ? now() : null]);

        if (!empty($data['huid'])) {
            InventoryItem::where('id', $data['inventory_item_id'])->update([
                'huid' => $data['huid'],
                'hallmark_center' => $data['center_name'],
                'hallmark_date' => now(),
                'hallmark_charge' => $data['charges'] ?? 45,
            ]);
        }

        return back()->with('success', 'Hallmark / HUID record saved.');
    }

    public function destroyStone(DiamondStone $stone)
    {
        $code = $stone->stone_code;
        $stone->delete();
        AuditService::log('Stones', 'delete', "Deleted stone {$code}");

        return back()->with('success', "Stone {$code} deleted.");
    }

    public function destroyHallmark(HallmarkRecord $hallmark)
    {
        $info = $hallmark->huid ?: "#{$hallmark->id}";
        $hallmark->delete();
        AuditService::log('Hallmark', 'delete', "Deleted hallmark record {$info}");

        return back()->with('success', "Hallmark record {$info} deleted.");
    }
}

