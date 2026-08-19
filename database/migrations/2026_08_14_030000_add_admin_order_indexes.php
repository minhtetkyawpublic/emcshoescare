<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'orders_created_id_index' => ['created_at', 'id'],
        'orders_package_created_index' => ['package_id', 'created_at'],
        'orders_handover_created_index' => ['fulfillment_method', 'created_at'],
        'orders_customer_phone_index' => ['customer_phone'],
        'orders_customer_name_index' => ['customer_name'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $name => $columns) {
            if (! Schema::hasIndex('orders', $name)) {
                Schema::table('orders', function (Blueprint $table) use ($columns, $name) {
                    $table->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->indexes) as $name) {
            if (Schema::hasIndex('orders', $name)) {
                Schema::table('orders', function (Blueprint $table) use ($name) {
                    $table->dropIndex($name);
                });
            }
        }
    }
};
