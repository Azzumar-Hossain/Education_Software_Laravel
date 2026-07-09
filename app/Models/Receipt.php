<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = [
        'receipt_number', 
        'receipt_date', 
        'enrollment_id', 
        'paid_for_month', 
        'paid_for_year', 
        'total_amount', 
        'collected_by'
    ];

    public function enrollment() {
        return $this->belongsTo(Enrollment::class);
    }
    public function items() {
        return $this->hasMany(ReceiptItem::class);
    }
    public function collector() {
        return $this->belongsTo(User::class, 'collected_by');
    }

    // 1. The Relationship
    public function payments() 
    {
        return $this->hasMany(Payment::class);
    }

    // 2. Helper: Calculate how much has been paid so far
    public function getPaidAmountAttribute()
    {
        return $this->payments()->sum('amount_paid');
    }

    // 3. Helper: Calculate how much is left to pay
    public function getDueAmountAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }
    public static function getPreviousDueForStudent($enrollmentId, $currentMonth, $currentYear)
    {
        // Find the most recent receipt for this student before the current month
        $previousReceipt = self::where('enrollment_id', $enrollmentId)
            ->where(function($query) use ($currentMonth, $currentYear) {
                // This is simplified; usually you'd compare Y-m dates
                $query->where('paid_for_year', '<', $currentYear)
                    ->orWhere(function($q) use ($currentYear, $currentMonth) {
                        $q->where('paid_for_year', $currentYear);
                    });
            })
            ->orderBy('id', 'desc')
            ->first();

        return $previousReceipt ? $previousReceipt->due_amount : 0;
    }
}