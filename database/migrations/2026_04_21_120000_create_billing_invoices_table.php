<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->dateTime('billing_date');
            $table->string('type');
            $table->string('cnae')->nullable();
            $table->unsignedInteger('cnae_annex')->nullable();
            $table->unsignedInteger('cnae_calculation')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_external_id')->nullable();
            $table->decimal('amount_brl', 15, 2);
            $table->decimal('amount_usd', 15, 2)->nullable();
            $table->decimal('usd_brl_exchange_rate', 15, 6)->nullable();
            $table->boolean('is_simulation')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_simulation');
            $table->index(['type', 'billing_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
