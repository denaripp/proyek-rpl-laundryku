<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintAttachment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'complaint_attachments';

    protected $fillable = [
        'complaint_id',
        'storage_key',
        'file_name',
        'file_size',
        'mime_type',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class, 'complaint_id');
    }
}
