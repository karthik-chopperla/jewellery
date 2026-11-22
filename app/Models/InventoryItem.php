<?php

namespace App\Models;

use App\Services\JewelleryIdentifierService;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (InventoryItem $item) {
            $identifiers = app(JewelleryIdentifierService::class);
            $item->barcode_format = $item->barcode_format ?: 'code128';
            $item->barcode = $item->barcode ?: $identifiers->generateBarcode(
                $item->barcode_format,
                Setting::get('barcode_prefix', '890') ?? '890'
            );
            $item->barcode_generated_at = $item->barcode_generated_at ?? now();
            $item->qr_token = $item->qr_token ?: $identifiers->generateQrToken();
            $item->qr_generated_at = $item->qr_generated_at ?? now();
        });
    }

    protected function casts(): array
    {
        return [
            'hallmark_date' => 'date',
            'verified_at' => 'datetime',
            'barcode_generated_at' => 'datetime',
            'qr_generated_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function hallmarkRecords()
    {
        return $this->hasMany(HallmarkRecord::class);
    }
}

