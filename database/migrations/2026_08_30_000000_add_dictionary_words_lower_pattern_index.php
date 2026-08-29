<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL btree indexes need text_pattern_ops for LIKE 'prefix%'
     * to use an expression index on lower(word). Without it the planner
     * falls back to a sequential scan (~86k rows).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'CREATE INDEX IF NOT EXISTS dictionary_words_word_lower_pattern_idx
             ON dictionary_words (lower(word) text_pattern_ops)'
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS dictionary_words_word_lower_pattern_idx');
    }
};
