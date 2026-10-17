<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'service_id',
        'service_name_snapshot',
        'pricing_unit_snapshot',
        'unit_price_snapshot',
        'minimum_charge_snapshot',
        'quantity_estimated',
        'quantity_actual',
        'line_estimated_amount',
        'line_final_amount',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'integer',
            'minimum_charge_snapshot' => 'integer',
            'quantity_estimated' => 'float',
            'quantity_actual' => 'float',
            'line_estimated_amount' => 'integer',
            'line_final_amount' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(StoreService::class, 'service_id');
    }
}
