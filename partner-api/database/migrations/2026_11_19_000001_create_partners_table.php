<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('partners', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('status')->default('active'); // active | suspended
            $t->integer('fee_bps')->default(90); // DelPago's cut for this partner (basis points; 90 = 0.9%)
            $t->string('markup_mode')->default('passthrough'); // passthrough | platform (partner adds own markup)
            // The DelPago merchant whose connectors collect when no sub-merchant is named.
            $t->unsignedBigInteger('default_merchant_id')->nullable();
            $t->string('contact_email')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('partners'); }
};
