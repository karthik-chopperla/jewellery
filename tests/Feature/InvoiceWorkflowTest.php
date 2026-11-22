<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceExchange;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InvoiceShare;
use App\Models\InventoryItem;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\InvoiceItemWriter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@jewelerp.in')->firstOrFail());
    }

    public function test_invoice_pdf_supports_a4_and_thermal_downloads(): void
    {
        $invoice = Invoice::with('items')->firstOrFail();

        foreach (['a4', 'thermal'] as $format) {
            $this->get(route('pos.invoice.pdf', ['invoice' => $invoice, 'format' => $format, 'download' => 1]))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', 'attachment; filename="' . Str::slug($invoice->invoice_no) . '-' . $format . '.pdf"')
                ->assertSee('%PDF-', false);
        }
    }

    public function test_whatsapp_fallback_uses_a_secure_revocable_pdf_link(): void
    {
        $invoice = Invoice::firstOrFail();
        $response = $this->post(route('pos.invoice.whatsapp', $invoice));
        $response->assertRedirect();

        $share = InvoiceShare::firstOrFail();
        $this->assertSame('link_prepared', $share->status);
        $this->assertSame('919820112233', $share->phone_snapshot);

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertStringContainsString('Secure invoice PDF:', $query['text']);
        preg_match('/Secure invoice PDF: (https?:\/\/\S+)/', $query['text'], $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $signedUrl = parse_url($matches[1]);
        $pdfPath = $signedUrl['path'] . '?' . $signedUrl['query'];
        $this->get($pdfPath)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-', false);
        $this->assertSame(1, $share->fresh()->access_count);

        $this->post(route('pos.invoice-shares.revoke', $share));
        $this->get($pdfPath)->assertGone();
    }

    public function test_whatsapp_rejects_invalid_phone_and_records_api_failures(): void
    {
        $invoice = Invoice::firstOrFail();
        $invoice->update(['customer_phone_snapshot' => 'not-a-phone']);
        $this->post(route('pos.invoice.whatsapp', $invoice))
            ->assertSessionHasErrors('whatsapp');
        $this->assertSame(0, InvoiceShare::count());

        $invoice->update(['customer_phone_snapshot' => '9820445566']);
        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Rejected']], 500)]);

        $this->post(route('pos.invoice.whatsapp', $invoice))
            ->assertSessionHasErrors('whatsapp');

        $this->assertSame('failed', InvoiceShare::firstOrFail()->status);
    }

    public function test_whatsapp_api_records_acceptance_without_claiming_delivery(): void
    {
        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $this->post(route('pos.invoice.whatsapp', Invoice::firstOrFail()))
            ->assertSessionHas('success', 'WhatsApp accepted the invoice message. Delivery to the customer is not confirmed.');

        $share = InvoiceShare::firstOrFail();
        $this->assertSame('api_accepted', $share->status);
        $this->assertSame('wamid.test', $share->provider_message_id);
    }

    public function test_exchange_creates_linked_sale_and_supports_multiple_items_and_payments(): void
    {
        $invoice = Invoice::with('items')->firstOrFail();
        $originalTotal = (float) $invoice->grand_total;
        $originalItem = $invoice->items->firstOrFail();
        $replacementItems = InventoryItem::where('branch_id', $invoice->branch_id)
            ->where('status', 'available')
            ->take(2)
            ->get();
        $this->assertCount(2, $replacementItems);

        $payload = [
            'request_token' => (string) Str::uuid(),
            'return_invoice_item_id' => $originalItem->id,
            'return_quantity' => 1,
            'condition' => 'good',
            'reason' => 'Size exchange',
            'gst_type' => 'intra',
            'replacement_items' => $replacementItems->map(fn ($item) => ['inventory_item_id' => $item->id])->all(),
            'payments' => [
                ['method' => 'cash', 'amount' => 25000],
                ['method' => 'upi', 'amount' => 5000, 'reference_no' => 'UPI-TEST'],
            ],
        ];
        $response = $this->post(route('pos.invoice.exchange.store', $invoice), $payload);
        $response->assertRedirect();

        $exchange = InvoiceExchange::firstOrFail();
        $replacementInvoice = Invoice::findOrFail($exchange->replacement_invoice_id);
        $this->assertSame('completed', $exchange->status);
        $this->assertEquals($originalTotal, (float) $invoice->fresh()->grand_total);
        $this->assertSame($invoice->id, $exchange->original_invoice_id);
        $this->assertSame('sale', $replacementInvoice->invoice_type);
        $this->assertCount(2, $replacementInvoice->items);
        $this->assertEquals((float) $replacementInvoice->grand_total, (float) $exchange->replacement_total);
        $this->assertEquals(30000, (float) $exchange->additional_paid);
        $this->assertSame('sold', $replacementItems[0]->fresh()->status);
        $this->assertSame('sold', $replacementItems[1]->fresh()->status);
        $this->assertSame(2, InvoicePayment::where('invoice_id', $replacementInvoice->id)
            ->whereIn('method', ['cash', 'upi'])->count());
        $this->assertSame($invoice->id, SalesReturn::firstOrFail()->invoice_id);

        $this->post(route('pos.invoice.exchange.store', $invoice), $payload)
            ->assertRedirect(route('pos.invoice', $replacementInvoice));
        $this->assertSame(2, Invoice::count());
        $this->assertSame(1, InvoiceExchange::count());
    }

    public function test_exchange_handles_partial_return_and_refund_and_prevents_over_return(): void
    {
        $invoice = Invoice::with('items')->firstOrFail();
        $originalItem = $invoice->items->firstOrFail();
        $originalItem->update(['quantity' => 2]);

        $product = $originalItem->inventoryItem?->product
            ?? \App\Models\Product::where('metal_type', 'Gold')->firstOrFail();
        $replacement = InventoryItem::create([
            'item_code' => 'TEST-EXCHANGE-LOW',
            'barcode' => 'TEST-EXCHANGE-LOW',
            'product_id' => $product->id,
            'branch_id' => $invoice->branch_id,
            'gross_weight' => 0.5,
            'stone_weight' => 0,
            'net_weight' => 0.5,
            'purity' => '22K',
            'status' => 'available',
        ]);

        $payload = [
            'request_token' => (string) Str::uuid(),
            'return_invoice_item_id' => $originalItem->id,
            'return_quantity' => 1,
            'condition' => 'good',
            'reason' => 'Partial exchange',
            'gst_type' => 'intra',
            'replacement_items' => [['inventory_item_id' => $replacement->id]],
            'payments' => [],
            'refund_method' => 'cash',
        ];
        $this->post(route('pos.invoice.exchange.store', $invoice), $payload)->assertRedirect();

        $exchange = InvoiceExchange::firstOrFail();
        $this->assertSame(1, (int) SalesReturn::firstOrFail()->quantity);
        $this->assertGreaterThan(0, (float) $exchange->refund_amount);
        $this->assertEquals(0, (float) $exchange->balance_due);
        $this->assertSame('sold', $replacement->fresh()->status);
        $this->assertSame(1, InvoicePayment::where('invoice_id', $invoice->id)->where('method', 'refund_cash')->count());

        $payload['request_token'] = (string) Str::uuid();
        $payload['return_quantity'] = 2;
        $this->post(route('pos.invoice.exchange.store', $invoice), $payload)
            ->assertSessionHasErrors('return_quantity');
        $this->assertSame(1, InvoiceExchange::count());
    }

    public function test_exchange_rolls_back_all_records_when_replacement_inventory_write_fails(): void
    {
        $invoice = Invoice::with('items')->firstOrFail();
        $replacement = InventoryItem::where('branch_id', $invoice->branch_id)
            ->where('status', 'available')
            ->firstOrFail();
        $writer = $this->mock(InvoiceItemWriter::class);
        $writer->shouldReceive('create')->once()->andThrow(new \RuntimeException('Simulated stock write failure'));

        $this->withoutExceptionHandling();
        try {
            $this->post(route('pos.invoice.exchange.store', $invoice), [
                'request_token' => (string) Str::uuid(),
                'return_invoice_item_id' => $invoice->items->firstOrFail()->id,
                'return_quantity' => 1,
                'condition' => 'good',
                'reason' => 'Rollback test',
                'gst_type' => 'intra',
                'replacement_items' => [['inventory_item_id' => $replacement->id]],
                'payments' => [],
            ]);
            $this->fail('The simulated inventory write failure was not raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated stock write failure', $exception->getMessage());
        }

        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, InvoiceExchange::count());
        $this->assertSame(0, SalesReturn::count());
        $this->assertSame('available', $replacement->fresh()->status);
    }
}
