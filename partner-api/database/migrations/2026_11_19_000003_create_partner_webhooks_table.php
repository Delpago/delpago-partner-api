<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('partner_webhooks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->string('url');
            $t->string('secret');          // per-webhook signing secret (whsec_...)
            $t->boolean('active')->default(true);
            $t->integer('fail_count')->default(0);
            $t->timestamp('disabled_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('partner_webhooks'); }
};
