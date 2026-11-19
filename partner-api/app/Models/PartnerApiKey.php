<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class PartnerApiKey extends Model
{
    protected $fillable = [
        'partner_id', 'prefix', 'key_hash', 'mode', 'scopes', 'label',
        'last_used_at', 'revoked_at',
    ];

    protected $casts = [
        'scopes'       => 'array',
        'last_used_at' => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    public function partner(): BelongsTo { return $this->belongsTo(Partner::class); }

    public function isLive(): bool { return $this->mode === 'live'; }
    public function isRevoked(): bool { return $this->revoked_at !== null; }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }

    /**
     * Mint a new key. Returns [model, plaintext]. The plaintext is shown ONCE
     * and never stored. Format: dp_{live|test}_{16 url-safe chars}.
     */
    public static function mint(Partner $partner, string $mode = 'test', array $scopes = ['*'], ?string $label = null): array
    {
        $mode   = $mode === 'live' ? 'live' : 'test';
        $random = Str::random(24);
        $plain  = 'dp_' . $mode . '_' . $random;
        // prefix = the stable lookup handle (safe to store/log): dp_live_ + first 8 of random
        $prefix = 'dp_' . $mode . '_' . substr($random, 0, 8);

        $key = static::create([
            'partner_id' => $partner->id,
            'prefix'     => $prefix,
            'key_hash'   => Hash::make($plain),
            'mode'       => $mode,
            'scopes'     => $scopes,
            'label'      => $label,
        ]);

        return [$key, $plain];
    }

    /** Resolve a presented bearer token to its key row (prefix lookup + hash check). */
    public static function resolve(string $plain): ?self
    {
        if (! str_starts_with($plain, 'dp_')) return null;
        $parts = explode('_', $plain); // dp, mode, random
        if (count($parts) < 3) return null;
        $mode   = $parts[1];
        $random = $parts[2];
        $prefix = 'dp_' . $mode . '_' . substr($random, 0, 8);

        $candidates = static::where('prefix', $prefix)->whereNull('revoked_at')->get();
        foreach ($candidates as $k) {
            if (Hash::check($plain, $k->key_hash)) return $k;
        }
        return null;
    }
}
