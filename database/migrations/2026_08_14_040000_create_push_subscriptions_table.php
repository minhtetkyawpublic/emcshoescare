<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->index();
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('user_agent', 500)->default('');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'updated_at']);
            $table->index(['admin_id', 'updated_at']);
            $table->unique(['customer_id', 'endpoint_hash'], 'push_customer_endpoint_unique');
            $table->unique(['admin_id', 'endpoint_hash'], 'push_admin_endpoint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
