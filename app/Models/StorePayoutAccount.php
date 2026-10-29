<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePayoutAccount extends Model
{
    use HasFactory;

    protected $table = 'store_payout_accounts';

    protected $fillable = [
        'store_id',
        'bank_code',
        'account_number',
        'account_holder_name',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
