<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceShare;
use App\Services\AuditService;
use App\Services\InvoicePdfService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class InvoiceShareController extends Controller
{
    public function share(Request $request, Invoice $invoice)
    {
        $invoice->loadMissing('customer');
        $rawPhone = $invoice->customer_phone_snapshot ?: $invoice->customer?->phone;
        $phone = $this->normalizePhone($rawPhone);
        if (! $phone) {
            return back()->withErrors(['whatsapp' => 'The customer mobile number is missing or invalid. Add a valid number with country code and try again.']);
        }

        $expiresAt = now()->addHours((int) config('services.whatsapp.link_expiry_hours', 72));
        $token = Str::random(64);
        $share = InvoiceShare::create([
            'invoice_id' => $invoice->id,
            'created_by' => Auth::id(),
            'channel' => 'whatsapp',
            'phone_snapshot' => $phone,
            'token_hash' => hash('sha256', $token),
            'status' => 'link_prepared',
            'expires_at' => $expiresAt,
        ]);
        $pdfUrl = URL::temporarySignedRoute(
            'invoices.shared-pdf',
            $expiresAt,
            ['share' => $share->id, 'token' => $token]
        );
        $name = $invoice->customer_name_snapshot ?: $invoice->customer?->name ?: 'Customer';
        $shop = $invoice->business_name_snapshot ?: config('app.name');
        $message = "Hello {$name},\n\nThank you for shopping with {$shop}.\n\nYour jewellery purchase invoice is ready.\n\nInvoice Number: {$invoice->invoice_no}\nInvoice Amount: ₹" .
            number_format((float) $invoice->grand_total, 2) . "\n\nSecure invoice PDF: {$pdfUrl}\n\nThank you for choosing us!";

        $accessToken = config('services.whatsapp.access_token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        if ($accessToken && $phoneNumberId) {
            return $this->sendDocument($share, $phone, $pdfUrl, $message, $invoice);
        }

        AuditService::log('InvoiceShare', 'link_prepared', "Prepared secure WhatsApp invoice link for {$invoice->invoice_no}", $share);
        session()->flash('success', 'WhatsApp opened with a secure invoice link. The message has not been sent until you confirm it in WhatsApp.');

        return redirect()->away('https://wa.me/' . $phone . '?text=' . urlencode($message));
    }

    public function access(Request $request, InvoiceShare $share, InvoicePdfService $pdfService)
    {
        abort_if($share->status === 'revoked', 410, 'This invoice link has been revoked.');
        abort_if($share->expires_at->isPast(), 410, 'This invoice link has expired.');
        $token = (string) $request->query('token', '');
        abort_unless($token !== '' && hash_equals($share->token_hash, hash('sha256', $token)), 403);

        $pdf = $pdfService->render($share->invoice, 'a4');
        $share->increment('access_count', 1, ['accessed_at' => now()]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->filename($share->invoice) . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function revoke(InvoiceShare $share)
    {
        $share->update(['status' => 'revoked']);
        AuditService::log('InvoiceShare', 'revoked', "Revoked invoice share link for {$share->invoice->invoice_no}", $share);

        return back()->with('success', 'Invoice access link revoked.');
    }

    private function sendDocument(InvoiceShare $share, string $phone, string $pdfUrl, string $message, Invoice $invoice)
    {
        $version = trim((string) config('services.whatsapp.graph_api_version', 'v23.0'), '/');
        $endpoint = 'https://graph.facebook.com/' . $version . '/' . config('services.whatsapp.phone_number_id') . '/messages';

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))
                ->acceptJson()
                ->timeout(15)
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'document',
                    'document' => [
                        'link' => $pdfUrl,
                        'filename' => $this->filename($invoice),
                        'caption' => $message,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $share->update(['status' => 'failed', 'error_message' => Str::limit($exception->getMessage(), 500)]);
            AuditService::log('InvoiceShare', 'failed', "WhatsApp connection failed for {$invoice->invoice_no}", $share);

            return back()->withErrors(['whatsapp' => 'WhatsApp could not be reached. The share attempt was recorded; please retry.']);
        }

        if (! $response->successful()) {
            $share->update([
                'status' => 'failed',
                'error_message' => Str::limit((string) $response->json('error.message', 'WhatsApp API rejected the request.'), 500),
            ]);
            AuditService::log('InvoiceShare', 'failed', "WhatsApp rejected invoice share for {$invoice->invoice_no}", $share);

            return back()->withErrors(['whatsapp' => 'WhatsApp did not accept the invoice share. Check the configuration and retry.']);
        }

        $share->update([
            'status' => 'api_accepted',
            'provider_message_id' => $response->json('messages.0.id'),
        ]);
        AuditService::log('InvoiceShare', 'api_accepted', "WhatsApp accepted invoice share for {$invoice->invoice_no}; delivery is not confirmed.", $share);

        return back()->with('success', 'WhatsApp accepted the invoice message. Delivery to the customer is not confirmed.');
    }

    private function normalizePhone(?string $value): ?string
    {
        if (! $value || ! preg_match('/^[+0-9().\-\s]+$/', $value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10) {
            $countryCode = preg_replace('/\D+/', '', (string) config('services.whatsapp.default_country_code', '91'));
            $digits = $countryCode . $digits;
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) ? $digits : null;
    }

    private function filename(Invoice $invoice): string
    {
        return Str::slug($invoice->invoice_no) . '.pdf';
    }
}
