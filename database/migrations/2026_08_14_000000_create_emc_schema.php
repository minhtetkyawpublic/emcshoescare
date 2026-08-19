<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('password_hash');
            $table->rememberToken();
            $table->string('full_name', 120);
            $table->string('address', 500)->default('');
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password_hash');
            $table->rememberToken();
            $table->string('display_name', 120);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 180);
            $table->string('description', 1000)->default('');
            $table->unsignedBigInteger('price_ks');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order', 'id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 24)->unique();
            $table->uuid('client_request_id')->nullable();
            $table->char('storage_key', 32)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->string('package_name', 180);
            $table->unsignedBigInteger('package_price_ks');
            $table->unsignedBigInteger('total_price_ks');
            $table->string('fulfillment_method', 20);
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20);
            $table->string('customer_address', 500);
            $table->text('customer_notes');
            $table->string('status', 40)->default('submitted');
            $table->dateTime('customer_seen_at', 6)->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'client_request_id']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['created_at', 'id'], 'orders_created_id_index');
            $table->index(['package_id', 'created_at'], 'orders_package_created_index');
            $table->index(['fulfillment_method', 'created_at'], 'orders_handover_created_index');
            $table->index('customer_phone', 'orders_customer_phone_index');
            $table->index('customer_name', 'orders_customer_name_index');
        });

        Schema::create('order_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('storage_name', 100);
            $table->string('original_name');
            $table->string('mime_type', 40);
            $table->unsignedInteger('size_bytes');
            $table->unsignedInteger('width_px');
            $table->unsignedInteger('height_px');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['order_id', 'storage_name']);
            $table->index(['order_id', 'sort_order', 'id']);
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('note_en', 1000)->default('');
            $table->string('note_mm', 1500)->default('');
            $table->foreignId('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('created_at', 6)->useCurrent();
            $table->index(['order_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_photos');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('customers');
    }
};
