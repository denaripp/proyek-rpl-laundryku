<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreService extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'store_services';

    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'description',
        'pricing_unit',
        'price_per_unit',
        'minimum_charge',
        'estimated_duration_hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_per_unit' => 'integer',
            'minimum_charge' => 'integer',
            'estimated_duration_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }
}
