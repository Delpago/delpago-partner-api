<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('partner_id');
            $t->string('idem_key');
            $t->string('request_hash', 64);
            $t->json('response')->nullable();
            $t->integer('status_code')->default(200);
            $t->timestamps();
            $t->unique(['partner_id', 'idem_key']);
        });
    }
    public function down(): void { Schema::dropIfExists('idempotency_keys'); }
};
