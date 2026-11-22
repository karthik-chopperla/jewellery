<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceExchange extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'return_value' => 'decimal:2',
            'replacement_taxable_value' => 'decimal:2',
            'replacement_cgst' => 'decimal:2',
            'replacement_sgst' => 'decimal:2',
            'replacement_igst' => 'decimal:2',
            'replacement_total' => 'decimal:2',
            'credit_applied' => 'decimal:2',
            'additional_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'refund_amount' => 'decimal:2',
        ];
    }

    public function originalInvoice()
    {
        return $this->belongsTo(Invoice::class, 'original_invoice_id');
    }

    public function replacementInvoice()
    {
        return $this->belongsTo(Invoice::class, 'replacement_invoice_id');
    }

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
