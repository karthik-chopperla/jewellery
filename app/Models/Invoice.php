<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['billed_at' => 'datetime'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function oldGoldPurchases()
    {
        return $this->hasMany(OldGoldPurchase::class);
    }

    public function shares()
    {
        return $this->hasMany(InvoiceShare::class);
    }

    public function exchangesAsOriginal()
    {
        return $this->hasMany(InvoiceExchange::class, 'original_invoice_id');
    }

    public function exchangeAsReplacement()
    {
        return $this->hasOne(InvoiceExchange::class, 'replacement_invoice_id');
    }

    public function modifications()
    {
        return $this->hasMany(InvoiceModification::class);
    }
}

