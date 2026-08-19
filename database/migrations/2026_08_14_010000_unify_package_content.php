<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('packages', 'name')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->string('name', 180)->default('')->after('slug');
                $table->string('description', 1000)->default('')->after('name');
            });

            DB::table('packages')->orderBy('id')->eachById(function ($package) {
                DB::table('packages')->where('id', $package->id)->update([
                    'name' => trim((string) $package->name_en) !== '' ? $package->name_en : $package->name_mm,
                    'description' => trim((string) $package->description_en) !== '' ? $package->description_en : $package->description_mm,
                ]);
            });

            Schema::table('packages', function (Blueprint $table) {
                $table->dropColumn(['name_en', 'name_mm', 'description_en', 'description_mm']);
            });
        }

        if (! Schema::hasColumn('orders', 'package_name')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('package_name', 180)->default('')->after('package_id');
            });

            DB::table('orders')->orderBy('id')->eachById(function ($order) {
                DB::table('orders')->where('id', $order->id)->update([
                    'package_name' => trim((string) $order->package_name_en) !== '' ? $order->package_name_en : $order->package_name_mm,
                ]);
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn(['package_name_en', 'package_name_mm']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('packages', 'name_en')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->string('name_en', 120)->default('')->after('slug');
                $table->string('name_mm', 180)->default('')->after('name_en');
                $table->string('description_en', 500)->default('')->after('name_mm');
                $table->string('description_mm', 800)->default('')->after('description_en');
            });
            DB::table('packages')->update([
                'name_en' => DB::raw('name'),
                'name_mm' => DB::raw('name'),
                'description_en' => DB::raw('description'),
                'description_mm' => DB::raw('description'),
            ]);
            Schema::table('packages', fn (Blueprint $table) => $table->dropColumn(['name', 'description']));
        }

        if (! Schema::hasColumn('orders', 'package_name_en')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('package_name_en', 120)->default('')->after('package_id');
                $table->string('package_name_mm', 180)->default('')->after('package_name_en');
            });
            DB::table('orders')->update([
                'package_name_en' => DB::raw('package_name'),
                'package_name_mm' => DB::raw('package_name'),
            ]);
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('package_name'));
        }
    }
};
