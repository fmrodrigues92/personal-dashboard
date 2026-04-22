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
        Schema::create('das_calculation_tax_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('das_calculation_id')->constrained()->cascadeOnDelete();
            $table->string('tax_component_code');
            $table->unsignedInteger('annex_used')->nullable();
            $table->string('invoice_type')->nullable();
            $table->decimal('calculated_amount_brl', 15, 2);
            $table->decimal('adjusted_amount_brl', 15, 2)->nullable();
            $table->decimal('rate_percentage', 10, 6)->nullable();
            $table->timestamps();

            $table->index(['das_calculation_id', 'tax_component_code'], 'das_tax_breakdowns_calculation_component_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('das_calculation_tax_breakdowns');
    }
};
