<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the storefront category used by the live marketplace tabs.
     *
     * "Best Deals" is a virtual view derived from compare_at/min prices, so
     * only the four concrete shelves are stored on the product row.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('category')->default('Games')->after('platform')->index();
        });

        DB::table('products')->update(['category' => 'Games']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
