<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete()->index();
        });

        Schema::table('das_calculations', function (Blueprint $table) {
            $table->dropUnique('das_calculations_reference_rule_projection_unique');
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete()->index();
        });

        $singleUserId = $this->singleUserId();

        if ($singleUserId !== null) {
            DB::table('billing_invoices')
                ->whereNull('user_id')
                ->update(['user_id' => $singleUserId]);

            DB::table('das_calculations')
                ->whereNull('user_id')
                ->update(['user_id' => $singleUserId]);
        }

        Schema::table('das_calculations', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'reference_month', 'rule_version', 'is_projection'],
                'das_calculations_user_reference_rule_projection_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('das_calculations', function (Blueprint $table) {
            $table->dropUnique('das_calculations_user_reference_rule_projection_unique');
            $table->dropConstrainedForeignId('user_id');
            $table->unique(
                ['reference_month', 'rule_version', 'is_projection'],
                'das_calculations_reference_rule_projection_unique',
            );
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }

    private function singleUserId(): ?int
    {
        if (DB::table('users')->count() !== 1) {
            return null;
        }

        return (int) DB::table('users')->value('id');
    }
};
