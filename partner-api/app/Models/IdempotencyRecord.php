<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyRecord extends Model
{
    protected $table = 'idempotency_keys';
    protected $fillable = ['partner_id', 'idem_key', 'request_hash', 'response', 'status_code'];
    protected $casts = ['response' => 'array'];
}
