<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['payment_date' => 'date']; }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function purchaseInvoice() { return $this->belongsTo(PurchaseInvoice::class); }
}

