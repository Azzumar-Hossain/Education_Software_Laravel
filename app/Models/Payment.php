<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'receipt_id', 'amount_paid', 'payment_method', 
        'transaction_id', 'payment_date', 'collected_by'
    ];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }
}
