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
        DB::table('words')->delete();

        Schema::table('words', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('words', 'user_id')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS words_user_id_english_japanese_unique');

        Schema::table('words', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
