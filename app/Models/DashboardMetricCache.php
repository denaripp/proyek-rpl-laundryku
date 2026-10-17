<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardMetricCache extends Model
{
    protected $fillable = ['scope', 'metric_key', 'metric_value', 'computed_at'];

    protected $casts = [
        'metric_value' => 'array',
        'computed_at' => 'datetime',
    ];
}
