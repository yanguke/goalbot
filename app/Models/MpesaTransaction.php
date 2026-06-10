<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    protected $fillable = [
        'phone_number',
        'amount',
        'payment_type',
        'checkout_request_id',
        'merchant_request_id',
        'mpesa_receipt_number',
        'transaction_date',
        'status',
        'result_code',
        'result_desc',
        'account_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, 'phone_number', 'phone_number');
    }
}
