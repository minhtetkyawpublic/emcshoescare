<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'pickup_fee_ks')) {
            DB::table('orders')->update(['total_price_ks' => DB::raw('package_price_ks')]);

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('pickup_fee_ks');
            });
        }

        Schema::dropIfExists('shop_settings');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'pickup_fee_ks')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('pickup_fee_ks')->default(0)->after('package_price_ks');
            });
        }

        if (! Schema::hasTable('shop_settings')) {
            Schema::create('shop_settings', function (Blueprint $table) {
                $table->string('setting_key', 80)->primary();
                $table->string('setting_value', 500);
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }
    }
};
