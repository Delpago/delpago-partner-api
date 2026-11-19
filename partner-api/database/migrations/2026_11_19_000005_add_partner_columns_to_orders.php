<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $t) {
            if (! Schema::hasColumn('orders', 'partner_id')) $t->unsignedBigInteger('partner_id')->nullable()->index();
            if (! Schema::hasColumn('orders', 'sub_merchant_ref')) $t->string('sub_merchant_ref')->nullable();
            if (! Schema::hasColumn('orders', 'partner_fee_bps')) $t->integer('partner_fee_bps')->nullable(); // partner's own markup for this charge
        });
    }
    public function down(): void {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn(['partner_id', 'sub_merchant_ref', 'partner_fee_bps']);
        });
    }
};
