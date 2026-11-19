<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partner extends Model
{
    protected $fillable = [
        'name', 'status', 'fee_bps', 'markup_mode',
        'default_merchant_id', 'contact_email', 'meta',
    ];

    protected $casts = ['meta' => 'array', 'fee_bps' => 'integer'];

    public function keys(): HasMany { return $this->hasMany(PartnerApiKey::class); }
    public function webhooks(): HasMany { return $this->hasMany(PartnerWebhook::class); }
    public function defaultMerchant(): BelongsTo { return $this->belongsTo(Merchant::class, 'default_merchant_id'); }

    public function isActive(): bool { return $this->status === 'active'; }
}
