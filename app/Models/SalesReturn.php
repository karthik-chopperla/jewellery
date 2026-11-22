<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    protected $guarded = ['id'];
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function exchange() { return $this->hasOne(InvoiceExchange::class); }
    public function invoiceItem() { return $this->belongsTo(InvoiceItem::class); }
}

