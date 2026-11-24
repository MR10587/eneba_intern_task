<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make new offer/order rows default to EUR instead of the scaffold's USD.
     *
     * WHY: this is a non-destructive default change only. It does not rewrite
     * existing rows and does not drop or recreate tables, so it is safe to run
     * with `artisan migrate` on the live database.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->char('currency', 3)->default('EUR')->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->char('currency', 3)->default('EUR')->change();
        });
    }

    /**
     * Restore the original scaffold defaults if this migration is rolled back.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->char('currency', 3)->default('USD')->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->char('currency', 3)->default('USD')->change();
        });
    }
};
