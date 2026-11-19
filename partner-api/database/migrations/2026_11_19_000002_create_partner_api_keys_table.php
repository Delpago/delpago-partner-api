<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('partner_api_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->string('prefix', 32)->index();      // e.g. dp_live_ab12cd34 (lookup handle, not secret)
            $t->string('key_hash');                  // hash of the full secret
            $t->string('mode', 8)->default('test');  // live | test
            $t->json('scopes')->nullable();          // ["charges.write","charges.read",...]
            $t->string('label')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('partner_api_keys'); }
};
