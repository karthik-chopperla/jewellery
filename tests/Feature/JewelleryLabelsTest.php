<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use App\Services\JewelleryIdentifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JewelleryLabelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_inventory_piece_gets_unique_persistent_barcode_and_qr_identifier(): void
    {
        $this->actingAs($this->admin());
        $product = Product::firstOrFail();
        $branch = Branch::firstOrFail();
        $input = [
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'gross_weight' => 3.125,
            'purity' => '22K',
        ];

        $this->post(route('inventory.store'), $input)->assertRedirect();
        $first = InventoryItem::latest('id')->firstOrFail();
        $this->post(route('inventory.store'), $input)->assertRedirect();
        $second = InventoryItem::latest('id')->firstOrFail();

        $this->assertNotSame('', $first->barcode);
        $this->assertNotSame($first->barcode, $second->barcode);
        $this->assertNotSame('', $first->qr_token);
        $this->assertNotSame($first->qr_token, $second->qr_token);
        $this->assertNotNull($first->barcode_generated_at);
        $this->assertNotNull($first->qr_generated_at);

        $originalBarcode = $first->barcode;
        $this->get(route('inventory.labels'))->assertOk();
        $this->assertSame($originalBarcode, $first->fresh()->barcode);
    }

    public function test_ean13_generation_and_duplicate_manual_barcode_are_validated(): void
    {
        $identifiers = new JewelleryIdentifierService();
        $ean13 = $identifiers->generateBarcode('ean13', '890');
        $this->assertTrue($identifiers->isValidEan13($ean13));

        $this->actingAs($this->admin());
        $existing = InventoryItem::firstOrFail();
        $response = $this->post(route('inventory.store'), [
            'product_id' => $existing->product_id,
            'branch_id' => $existing->branch_id,
            'gross_weight' => 2.5,
            'purity' => '22K',
            'barcode' => $existing->barcode,
            'barcode_format' => 'code128',
        ]);

        $response->assertSessionHasErrors('barcode');
    }

    public function test_public_qr_lookup_shows_item_details_without_purchase_cost(): void
    {
        $item = InventoryItem::firstOrFail();
        $item->update(['purchase_cost' => 987654.32, 'stone_charge' => 45678.90]);

        $response = $this->get(route('inventory.qr.lookup', ['token' => $item->qr_token]));

        $response->assertOk()
            ->assertSee($item->item_code)
            ->assertSee($item->product->name)
            ->assertDontSee('987654')
            ->assertDontSee('45678');
        $this->get(route('inventory.qr.lookup', ['token' => str_repeat('x', 64)]))->assertNotFound();
    }

    public function test_missing_identifiers_can_be_generated_in_bulk_without_changing_existing_codes(): void
    {
        $this->actingAs($this->admin());
        $item = InventoryItem::firstOrFail();
        $item->update(['barcode' => '', 'qr_token' => null]);

        $this->post(route('inventory.labels.barcodes.generate'), [
            'all_filtered' => 1,
            'branch_id' => $item->branch_id,
        ])->assertRedirect();
        $item->refresh();
        $this->assertNotSame('', $item->barcode);

        $existingBarcode = $item->barcode;
        $this->post(route('inventory.labels.qr.generate'), [
            'all_filtered' => 1,
            'branch_id' => $item->branch_id,
        ])->assertRedirect();
        $item->refresh();
        $this->assertNotSame('', $item->qr_token);
        $this->assertSame($existingBarcode, $item->barcode);
    }

    public function test_label_print_logs_selected_items_and_renders_barcode_and_qr(): void
    {
        $this->actingAs($this->admin());
        $item = InventoryItem::firstOrFail();

        $response = $this->post(route('inventory.labels.print'), [
            'ids' => [$item->id],
            'template' => 'qr',
            'paper_type' => 'a4',
            'width_mm' => 50,
            'height_mm' => 25,
            'include_qr' => 1,
            'fields' => ['weights', 'huid', 'metal', 'purity'],
        ]);

        $response->assertOk()
            ->assertSee('Print / Save as PDF')
            ->assertSee('data-barcode-canvas', false)
            ->assertSee('data-qr-canvas', false)
            ->assertSee($item->barcode);
        $this->assertDatabaseHas('label_print_logs', [
            'inventory_item_id' => $item->id,
            'label_type' => 'tag',
            'template' => 'qr',
            'paper_type' => 'a4',
            'copies' => 1,
        ]);
    }

    public function test_pos_barcode_lookup_resolves_available_items_and_rejects_sold_stock(): void
    {
        $this->actingAs($this->admin());
        $item = InventoryItem::where('status', 'available')->firstOrFail();
        $url = route('pos.barcode.lookup', ['code' => $item->barcode, 'branch_id' => $item->branch_id]);

        $this->getJson($url)->assertOk()->assertJsonPath('item.id', $item->id);

        $item->update(['status' => 'sold']);
        $this->getJson($url)->assertStatus(409)->assertJsonPath('status', 'sold');
    }

    public function test_labels_can_be_filtered_by_branch(): void
    {
        $this->actingAs($this->admin());
        $firstBranchItem = InventoryItem::firstOrFail();
        $otherBranch = Branch::where('id', '!=', $firstBranchItem->branch_id)->firstOrFail();
        $otherBranchItem = InventoryItem::where('branch_id', $otherBranch->id)->firstOrFail();

        $this->get(route('inventory.labels', ['branch_id' => $otherBranch->id]))
            ->assertOk()
            ->assertSee($otherBranchItem->item_code)
            ->assertDontSee($firstBranchItem->item_code);
    }

    public function test_barcode_edit_and_regeneration_require_inventory_approval_permission(): void
    {
        $item = InventoryItem::firstOrFail();
        $cashier = User::where('role', 'cashier')->firstOrFail();
        $this->actingAs($cashier)->put(route('inventory.barcode.update', $item), [
            'barcode' => 'MANUAL-UNAUTHORIZED',
            'barcode_format' => 'code128',
        ])->assertForbidden();

        $manager = User::where('role', 'manager')->firstOrFail();
        $this->actingAs($manager)->put(route('inventory.barcode.update', $item), [
            'barcode' => 'MANUAL-AUTHORIZED',
            'barcode_format' => 'code128',
        ])->assertRedirect();
        $item->refresh();
        $this->assertSame('MANUAL-AUTHORIZED', $item->barcode);

        $this->post(route('inventory.barcode.regenerate', $item))->assertRedirect();
        $this->assertNotSame('MANUAL-AUTHORIZED', $item->fresh()->barcode);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@jewelerp.in')->firstOrFail();
    }
}
