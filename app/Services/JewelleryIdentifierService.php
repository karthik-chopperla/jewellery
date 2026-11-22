<?php

namespace App\Services;

use App\Models\InventoryItem;
use Illuminate\Support\Str;
use InvalidArgumentException;

class JewelleryIdentifierService
{
    public function generateBarcode(string $format, string $prefix = '890'): string
    {
        if (!in_array($format, ['code128', 'ean13'], true)) {
            throw new InvalidArgumentException('Unsupported barcode format.');
        }

        if ($format === 'ean13' && (!ctype_digit($prefix) || strlen($prefix) > 11)) {
            throw new InvalidArgumentException('EAN-13 prefixes must contain 1 to 11 digits.');
        }

        for ($attempt = 0; $attempt < 50; $attempt++) {
            if ($format === 'ean13') {
                $data = $prefix;
                while (strlen($data) < 12) {
                    $data .= (string) random_int(0, 9);
                }
                $barcode = $data . $this->ean13CheckDigit($data);
            } else {
                $barcode = $prefix . Str::upper(Str::random(12));
            }

            if (!InventoryItem::where('barcode', $barcode)->exists()) {
                return $barcode;
            }
        }

        throw new InvalidArgumentException('Could not generate a unique barcode. Please try again.');
    }

    public function generateQrToken(): string
    {
        do {
            $token = Str::random(64);
        } while (InventoryItem::where('qr_token', $token)->exists());

        return $token;
    }

    public function isValidEan13(string $barcode): bool
    {
        if (strlen($barcode) !== 13 || !ctype_digit($barcode)) {
            return false;
        }

        return $barcode[12] === $this->ean13CheckDigit(substr($barcode, 0, 12));
    }

    private function ean13CheckDigit(string $data): string
    {
        $sum = 0;
        foreach (str_split($data) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }
}
