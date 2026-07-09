<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ReceiptItem extends Model
{
    protected $fillable = ['receipt_id', 'fee_category_id', 'amount', 'related_month',];

    protected $casts = [
        'related_month' => 'array', // Tells Laravel to save multiple months as a JSON list
    ];

    public function receipt() {
        return $this->belongsTo(Receipt::class);
    }
    public function feeCategory() {
        return $this->belongsTo(FeeCategory::class);
    }
}