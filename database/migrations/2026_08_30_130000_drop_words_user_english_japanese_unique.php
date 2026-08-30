<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes a legacy per-user unique index that was briefly added during
     * development. Fresh installs never create it; this is safe when absent.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('words', 'user_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS words_user_id_english_japanese_unique');
        } else {
            DB::statement('DROP INDEX IF EXISTS words_user_id_english_japanese_unique');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
