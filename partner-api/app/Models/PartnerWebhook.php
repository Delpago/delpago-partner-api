<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerWebhook extends Model
{
    protected $fillable = [
        'partner_id', 'url', 'secret', 'active', 'fail_count', 'disabled_at',
    ];

    protected $casts = ['active' => 'boolean', 'disabled_at' => 'datetime'];

    public function partner(): BelongsTo { return $this->belongsTo(Partner::class); }
}
