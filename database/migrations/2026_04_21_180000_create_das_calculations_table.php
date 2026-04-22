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
        Schema::create('das_calculations', function (Blueprint $table) {
            $table->id();
            $table->date('reference_month');
            $table->string('rule_version');
            $table->boolean('factor_r_applied');
            $table->decimal('monthly_revenue_brl', 15, 2);
            $table->decimal('das_total_brl', 15, 2);
            $table->boolean('is_projection')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['reference_month', 'rule_version', 'is_projection'], 'das_calculations_reference_rule_projection_unique');
            $table->index(['reference_month', 'is_projection']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('das_calculations');
    }
};
