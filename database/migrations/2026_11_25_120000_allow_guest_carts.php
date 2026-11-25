<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cart may belong to a signed-in user or to an anonymous guest token.
     * Exactly one of the two is set at runtime; the service owns that rule.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->string('guest_token', 64)->nullable()->after('user_id');
            $table->unique('guest_token');
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('carts')->whereNull('user_id')->delete();

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropUnique(['guest_token']);
            $table->dropColumn('guest_token');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
