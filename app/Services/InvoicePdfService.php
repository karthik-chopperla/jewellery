<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SalesReturn;
use App\Models\Setting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function render(Invoice $invoice, string $format = 'a4'): string
    {
        abort_unless(in_array($format, ['a4', 'thermal'], true), 404);

        $invoice->loadMissing([
            'branch', 'customer', 'salesperson', 'cashier', 'items.inventoryItem',
            'payments', 'oldGoldPurchases',
        ]);
        $logoDataUri = $this->logoDataUri($invoice->business_logo_snapshot);
        $profile = [
            'name' => $invoice->business_name_snapshot ?: $invoice->branch?->name,
            'address' => $invoice->business_address_snapshot ?: $invoice->branch?->address,
            'phone' => $invoice->business_phone_snapshot ?: $invoice->branch?->phone,
            'gstin' => $invoice->business_gstin_snapshot ?: $invoice->branch?->gstin,
            'state' => $invoice->business_state_snapshot ?: $invoice->branch?->state,
            'state_code' => $invoice->business_state_code_snapshot,
        ];

        return $this->renderView('pos.invoice-pdf', [
            'invoice' => $invoice,
            'profile' => $profile,
            'logoDataUri' => $logoDataUri,
            'format' => $format,
            'includeShopCopy' => Setting::get('invoice_shop_copy', '0') === '1',
            'amountInWords' => CurrencyAmountInWords::inr((float) $invoice->grand_total),
        ], $format);
    }

    public function renderCreditNote(SalesReturn $salesReturn): string
    {
        $salesReturn->loadMissing(['invoice.branch', 'invoice.customer', 'invoiceItem']);
        $invoice = $salesReturn->invoice;
        $profile = [
            'name' => $invoice->business_name_snapshot ?: $invoice->branch?->name,
            'address' => $invoice->business_address_snapshot ?: $invoice->branch?->address,
            'phone' => $invoice->business_phone_snapshot ?: $invoice->branch?->phone,
            'gstin' => $invoice->business_gstin_snapshot ?: $invoice->branch?->gstin,
            'state' => $invoice->business_state_snapshot ?: $invoice->branch?->state,
            'state_code' => $invoice->business_state_code_snapshot,
        ];

        return $this->renderView('pos.credit-note-pdf', [
            'return' => $salesReturn,
            'invoice' => $invoice,
            'profile' => $profile,
            'logoDataUri' => $this->logoDataUri($invoice->business_logo_snapshot),
            'amountInWords' => CurrencyAmountInWords::inr((float) $salesReturn->return_value),
        ], 'a4');
    }

    private function renderView(string $viewName, array $data, string $format): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($viewName, $data)->render(), 'UTF-8');

        if ($format === 'thermal') {
            $dompdf->setPaper([0, 0, 226.77, 841.89], 'portrait');
        } else {
            $dompdf->setPaper('A4', 'portrait');
        }

        $dompdf->render();

        return $dompdf->output();
    }

    private function logoDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mimeType = Storage::disk('public')->mimeType($path);
        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode(Storage::disk('public')->get($path));
    }
}
